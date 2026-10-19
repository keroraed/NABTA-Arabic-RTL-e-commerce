<?php

namespace App\Services\Pos;

use App\Enums\StockMovementType;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Services\Accounting\PartyLedger;
use App\Services\Accounting\Treasury;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Records a POS sale and every consequence of it, in one transaction.
 *
 * Three properties this class exists to guarantee:
 *
 * 1. ATOMICITY. The sale, its items, the stock movements, the customer ledger
 *    entry and the cash movement all commit together or not at all. A sale that
 *    commits without its stock movement is worse than one that fails outright,
 *    because nothing will ever tell you it happened.
 *
 * 2. IDEMPOTENCY on `uuid`. The offline layer will retry uploads after timeouts,
 *    and a retry must return the existing sale rather than create a second one.
 *    That is enforced by the unique index and by catching its violation, NOT by
 *    a SELECT check alone, because two concurrent requests can both pass one.
 *
 * 3. SERVER-SIDE RECALCULATION. Line totals are recomputed from the product's
 *    stored price. A mismatch against the submitted total is flagged for review
 *    but does NOT reject the sale: the customer already paid and walked out, so
 *    refusing it would only destroy the record of money that was taken.
 */
class SaleRegistrar
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly PartyLedger $ledger,
        private readonly Treasury $treasury,
        private readonly PosPricing $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $data  uuid, receipt_number, items[], payment_method,
     *                                      paid_cash, paid_on_credit, cash_received,
     *                                      party_id, discount, sold_at, claimed_total
     */
    public function register(
        PosTerminal $terminal,
        array $data,
        ?CashShift $shift = null,
        ?int $userId = null,
    ): PosSale {
        $existing = PosSale::where('uuid', $data['uuid'])->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->write($terminal, $data, $shift, $userId));
        } catch (QueryException $e) {
            // Lost a race with a concurrent upload of the same sale. The unique
            // index did its job; return the row the winner wrote.
            if ($this->isDuplicateUuid($e)) {
                return PosSale::where('uuid', $data['uuid'])->firstOrFail();
            }

            throw $e;
        }
    }

    private function write(PosTerminal $terminal, array $data, ?CashShift $shift, ?int $userId): PosSale
    {
        $lines = $this->buildLines($data['items'] ?? []);

        $saleDiscount = $this->pricing->toPiastres((float) ($data['discount'] ?? 0));
        $totals = $this->pricing->totals($lines, $saleDiscount);
        $totalPounds = $this->pricing->toPounds($totals['total']);

        $paidCash = round((float) ($data['paid_cash'] ?? 0), 2);
        $paidCredit = round((float) ($data['paid_on_credit'] ?? 0), 2);
        $cashReceived = round((float) ($data['cash_received'] ?? $paidCash), 2);

        $soldAt = ! empty($data['sold_at']) ? new \DateTimeImmutable($data['sold_at']) : now();

        $sale = PosSale::create([
            'uuid' => $data['uuid'],
            'pos_terminal_id' => $terminal->id,
            'cash_shift_id' => $shift?->id,
            'receipt_number' => $data['receipt_number'],
            'type' => 'sale',
            'party_id' => $data['party_id'] ?? null,
            'subtotal' => $this->pricing->toPounds($totals['subtotal']),
            'discount' => $this->pricing->toPounds($totals['line_discounts'] + $totals['sale_discount']),
            'total' => $totalPounds,
            'payment_method' => $data['payment_method'],
            'paid_cash' => $paidCash,
            'paid_on_credit' => $paidCredit,
            'cash_received' => $cashReceived,
            'change_given' => max(0, round($cashReceived - $paidCash, 2)),
            'sold_by' => $userId ?? auth()->id(),
            'sold_at' => $soldAt,
            'clock_offset_ms' => (int) ($data['clock_offset_ms'] ?? 0),
            'received_at' => now(),
            'synced_at' => now(),
            'has_total_discrepancy' => $this->flagDiscrepancy($data, $totalPounds),
        ]);

        foreach ($lines as $line) {
            $sale->items()->create([
                'product_id' => $line['product']->id,
                'product_name_snapshot' => $line['product']->name_ar,
                'unit_price_snapshot' => $line['unit_price'],
                'cost_at_sale' => $line['product']->average_cost ?? 0,
                'quantity' => $line['quantity'],
                'weight_kg' => $line['weight_kg'],
                'discount' => $this->pricing->toPounds($line['discount_piastres']),
                'line_total' => $this->pricing->toPounds($line['net_piastres']),
            ]);

            // Stock leaves the terminal's own warehouse. Never blocked on the
            // balance: the customer is at the counter holding the goods.
            $this->stock->record(
                product: $line['product'],
                warehouse: $terminal->warehouse_id,
                type: StockMovementType::PosSale,
                quantity: $line['stock_quantity'],
                reference: $sale,
                notes: 'فاتورة '.$sale->receipt_number,
                occurredAt: $soldAt,
                userId: $userId,
            );
        }

        $this->recordPayment($sale, $terminal, $paidCash, $paidCredit);

        return $sale->load('items');
    }

    /**
     * Recomputes every line from the product's stored price, never from what the
     * client sent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id'] ?? null);

            if (! $product) {
                throw new RuntimeException('منتج غير موجود في الفاتورة');
            }

            $unitPrice = $this->pricing->unitPriceFor($product);
            $weightKg = isset($item['weight_kg']) ? (float) $item['weight_kg'] : null;
            $quantity = (float) ($item['quantity'] ?? 1);

            if ($product->is_weighted && $weightKg !== null) {
                $linePiastres = $this->pricing->weightedLinePiastres($unitPrice, (int) round($weightKg * 1000));
                $stockQuantity = $weightKg;
                $quantity = 1;
            } else {
                $linePiastres = $this->pricing->unitLinePiastres($unitPrice, $quantity);
                $stockQuantity = $quantity;
                $weightKg = null;
            }

            $discountPiastres = min($this->pricing->toPiastres((float) ($item['discount'] ?? 0)), $linePiastres);

            $lines[] = [
                'product' => $product,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'weight_kg' => $weightKg,
                'stock_quantity' => $stockQuantity,
                // Gross for the totals maths; the net figure below is what the
                // line actually contributed and is what lands on the item row.
                'gross_piastres' => $linePiastres,
                'discount_piastres' => $discountPiastres,
                'net_piastres' => max(0, $linePiastres - $discountPiastres),
            ];
        }

        return $lines;
    }

    /**
     * Cash goes into the terminal's drawer; credit is debited to the customer.
     * A partial payment does both, which is why these are two separate amounts
     * rather than one amount plus a method name.
     */
    private function recordPayment(PosSale $sale, PosTerminal $terminal, float $paidCash, float $paidCredit): void
    {
        if ($paidCash > 0) {
            $this->treasury->in(
                account: $terminal->cash_account_id,
                amount: $paidCash,
                description: 'بيع كاشير '.$sale->receipt_number,
                reference: $sale,
                occurredAt: $sale->sold_at,
            );
        }

        if ($paidCredit > 0) {
            if (! $sale->party_id) {
                throw new RuntimeException('البيع الآجل يتطلب اختيار عميل');
            }

            // Debit: the customer now owes us more.
            $this->ledger->debit(
                party: $sale->party_id,
                amount: $paidCredit,
                description: 'فاتورة آجل '.$sale->receipt_number,
                reference: $sale,
                date: $sale->sold_at,
            );
        }
    }

    /**
     * Compares the client's total against the recomputed one. Logged and
     * flagged, never used to reject.
     */
    private function flagDiscrepancy(array $data, float $recomputedTotal): bool
    {
        $claimed = $data['claimed_total'] ?? null;

        if ($claimed === null || abs((float) $claimed - $recomputedTotal) < 0.005) {
            return false;
        }

        Log::warning('POS total mismatch', [
            'uuid' => $data['uuid'],
            'claimed' => (float) $claimed,
            'recomputed' => $recomputedTotal,
        ]);

        return true;
    }

    private function isDuplicateUuid(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            && str_contains(strtolower($e->getMessage()), 'uuid');
    }
}
