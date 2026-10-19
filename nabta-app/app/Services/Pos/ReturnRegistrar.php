<?php

namespace App\Services\Pos;

use App\Enums\StockMovementType;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Services\Accounting\PartyLedger;
use App\Services\Accounting\Treasury;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a return against an existing sale.
 *
 * A return is stored as another row in `pos_sales` with `type = 'return'` and
 * **every monetary and quantity field negated**. That is the whole design
 * decision, and it is what keeps the rest of the system simple:
 *
 *   SUM(total)      across sales and returns = net revenue
 *   SUM(paid_cash)  = what should actually be in the drawer
 *   SUM(quantity)   per product = net units sold
 *
 * Storing returns as positive numbers with a type flag would mean every single
 * aggregate in every report had to remember to branch on that flag — and the
 * one that forgot would overstate revenue silently.
 *
 * Stock moves the other way: goods come back in as a `customer_return`.
 */
class ReturnRegistrar
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly PartyLedger $ledger,
        private readonly Treasury $treasury,
        private readonly PosPricing $pricing,
        private readonly ReceiptSequencer $sequencer,
    ) {}

    /**
     * How much of each line of a sale is still returnable.
     *
     * @return array<int, float> pos_sale_items.id => remaining quantity
     */
    public function returnableQuantities(PosSale $sale): array
    {
        $sold = $sale->items->mapWithKeys(
            fn (PosSaleItem $item) => [$item->id => abs($item->isWeighted()
                ? (float) $item->weight_kg
                : (float) $item->quantity)],
        );

        // Returns store negatives, so summing them gives what has gone back.
        $returned = PosSaleItem::query()
            ->whereIn('original_item_id', $sold->keys())
            ->get()
            ->groupBy('original_item_id')
            ->map(fn ($rows) => $rows->sum(fn (PosSaleItem $r) => abs($r->isWeighted()
                ? (float) $r->weight_kg
                : (float) $r->quantity)));

        return $sold
            ->map(fn (float $qty, int $id) => round($qty - (float) ($returned[$id] ?? 0), 3))
            ->all();
    }

    /**
     * @param  array<int, float>  $quantities  pos_sale_items.id => quantity to return
     * @param  string  $refundMethod  cash | credit — credit reduces what the
     *                                customer owes instead of opening the drawer
     */
    public function register(
        PosSale $original,
        array $quantities,
        string $refundMethod,
        string $uuid,
        ?CashShift $shift = null,
        ?int $userId = null,
        ?string $reason = null,
    ): PosSale {
        $existing = PosSale::where('uuid', $uuid)->first();

        if ($existing) {
            return $existing;
        }

        if ($original->type === 'return') {
            throw new RuntimeException(__('pos.cannot_return_a_return'));
        }

        try {
            return DB::transaction(fn () => $this->write(
                $original, $quantities, $refundMethod, $uuid, $shift, $userId, $reason,
            ));
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'], true)
                && str_contains(strtolower($e->getMessage()), 'uuid')) {
                return PosSale::where('uuid', $uuid)->firstOrFail();
            }

            throw $e;
        }
    }

    private function write(
        PosSale $original,
        array $quantities,
        string $refundMethod,
        string $uuid,
        ?CashShift $shift,
        ?int $userId,
        ?string $reason,
    ): PosSale {
        // Re-read inside the transaction and lock, so two cashiers cannot both
        // refund the last remaining unit of the same line.
        $remaining = $this->returnableQuantities(
            $original->load(['items' => fn ($q) => $q->lockForUpdate()]),
        );

        $terminal = $original->terminal;
        $lines = [];
        $refundPiastres = 0;

        foreach ($quantities as $itemId => $requested) {
            $requested = round((float) $requested, 3);

            if ($requested <= 0) {
                continue;
            }

            /** @var PosSaleItem|null $item */
            $item = $original->items->firstWhere('id', (int) $itemId);

            if (! $item) {
                throw new RuntimeException(__('pos.line_not_in_sale'));
            }

            $available = (float) ($remaining[$item->id] ?? 0);

            if ($requested > $available + 0.0005) {
                throw new RuntimeException(__('pos.over_return', [
                    'product' => $item->product_name_snapshot,
                    'available' => rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.'),
                ]));
            }

            // Refund at the price actually charged, not today's price. A price
            // rise between sale and return must not turn a refund into profit
            // for the shop or a loss for the customer.
            $unitPrice = (float) $item->unit_price_snapshot;

            if ($item->isWeighted()) {
                $linePiastres = $this->pricing->weightedLinePiastres($unitPrice, (int) round($requested * 1000));
                $weightKg = $requested;
                $quantity = 1.0;
            } else {
                $linePiastres = $this->pricing->unitLinePiastres($unitPrice, $requested);
                $weightKg = null;
                $quantity = $requested;
            }

            // A discounted line refunds proportionally, otherwise returning one
            // of three discounted items hands back more than was paid.
            $soldQty = abs($item->isWeighted() ? (float) $item->weight_kg : (float) $item->quantity);
            $share = $soldQty > 0 ? $requested / $soldQty : 0;
            $discountPiastres = (int) round($this->pricing->toPiastres((float) $item->discount) * $share);

            $linePiastres = max(0, $linePiastres - $discountPiastres);
            $refundPiastres += $linePiastres;

            $lines[] = [
                'item' => $item,
                'quantity' => $quantity,
                'weight_kg' => $weightKg,
                'stock_quantity' => $requested,
                'unit_price' => $unitPrice,
                'line_total_piastres' => $linePiastres,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException(__('pos.nothing_to_return'));
        }

        $refund = $this->pricing->toPounds($refundPiastres);
        $isCredit = $refundMethod === PosSale::CREDIT;

        $return = PosSale::create([
            'uuid' => $uuid,
            'pos_terminal_id' => $terminal->id,
            'cash_shift_id' => $shift?->id,
            'receipt_number' => $this->sequencer->next($terminal),
            'type' => 'return',
            'original_sale_id' => $original->id,
            'party_id' => $original->party_id,

            // Negated — see the class docblock.
            'subtotal' => -$refund,
            'discount' => 0,
            'total' => -$refund,
            'payment_method' => $isCredit ? PosSale::CREDIT : PosSale::CASH,
            'paid_cash' => $isCredit ? 0 : -$refund,
            'paid_on_credit' => $isCredit ? -$refund : 0,
            'cash_received' => 0,
            'change_given' => 0,

            'sold_by' => $userId ?? auth()->id(),
            'sold_at' => now(),
            'received_at' => now(),
            'synced_at' => now(),
        ]);

        foreach ($lines as $line) {
            /** @var PosSaleItem $item */
            $item = $line['item'];

            $return->items()->create([
                'product_id' => $item->product_id,
                'original_item_id' => $item->id,
                'product_name_snapshot' => $item->product_name_snapshot,
                'unit_price_snapshot' => $line['unit_price'],
                'cost_at_sale' => $item->cost_at_sale,
                'quantity' => -$line['quantity'],
                'weight_kg' => $line['weight_kg'] === null ? null : -$line['weight_kg'],
                'discount' => 0,
                'line_total' => -$this->pricing->toPounds($line['line_total_piastres']),
            ]);

            // The goods physically come back onto the shelf.
            $this->stock->record(
                product: $item->product_id,
                warehouse: $terminal->warehouse_id,
                type: StockMovementType::CustomerReturn,
                quantity: $line['stock_quantity'],
                reference: $return,
                notes: __('pos.return_of', ['receipt' => $original->receipt_number]),
                userId: $userId,
            );
        }

        $this->recordRefund($return, $original, $terminal, $refund, $isCredit, $reason);

        return $return->load('items');
    }

    /**
     * Cash out of the drawer, or a credit against what the customer owes.
     *
     * Refunding to account is not a nicety: if the original sale was on credit,
     * handing over cash would pay the customer money they never gave us.
     */
    private function recordRefund(
        PosSale $return,
        PosSale $original,
        $terminal,
        float $refund,
        bool $isCredit,
        ?string $reason,
    ): void {
        $description = __('pos.refund_for', ['receipt' => $original->receipt_number])
            .($reason ? ' — '.$reason : '');

        if ($isCredit) {
            if (! $return->party_id) {
                throw new RuntimeException(__('pos.credit_refund_needs_customer'));
            }

            // Credit: the customer owes us less.
            $this->ledger->credit(
                party: $return->party_id,
                amount: $refund,
                description: $description,
                reference: $return,
                date: $return->sold_at,
            );

            return;
        }

        $this->treasury->out(
            account: $terminal->cash_account_id,
            amount: $refund,
            description: $description,
            reference: $return,
            occurredAt: $return->sold_at,
        );
    }
}
