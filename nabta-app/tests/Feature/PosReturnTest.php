<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\ReturnRegistrar;
use App\Services\Pos\SaleRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Returns are stored as a negated mirror of the sale, so that every aggregate
 * in the system (revenue, drawer, units sold) nets out with a plain SUM and no
 * branching on a type flag. These tests pin that behaviour down.
 */
class PosReturnTest extends TestCase
{
    use RefreshDatabase;

    private PosTerminal $terminal;
    private CashShift $shift;
    private User $user;
    private CashAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'المحل', 'type' => 'store', 'is_active' => true, 'is_online_fulfilment' => true],
        );

        $this->account = CashAccount::create(['name' => 'خزينة المحل', 'type' => 'cash']);

        $this->terminal = PosTerminal::create([
            'name' => 'كاشير 1',
            'code' => 'T1',
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $this->account->id,
        ]);

        $this->user = User::factory()->admin()->create();

        $this->shift = CashShift::create([
            'pos_terminal_id' => $this->terminal->id,
            'opened_by' => $this->user->id,
            'opened_at' => now(),
            'opening_float' => 0,
            'status' => CashShift::OPEN,
        ]);
    }

    private function sell(array $items, array $overrides = []): PosSale
    {
        return $this->app->make(SaleRegistrar::class)->register(
            $this->terminal,
            array_merge([
                'uuid' => (string) Str::uuid(),
                'receipt_number' => 'T1-'.str_pad((string) (PosSale::count() + 1), 6, '0', STR_PAD_LEFT),
                'payment_method' => PosSale::CASH,
                'items' => $items,
                'paid_cash' => 0,
            ], $overrides),
            $this->shift,
            $this->user->id,
        );
    }

    private function refund(PosSale $sale, array $quantities, string $method = 'cash'): PosSale
    {
        return $this->app->make(ReturnRegistrar::class)->register(
            original: $sale,
            quantities: $quantities,
            refundMethod: $method,
            uuid: (string) Str::uuid(),
            shift: $this->shift,
            userId: $this->user->id,
        );
    }

    /* ------------------------------------------------------------------ */

    public function test_a_full_cash_return_reverses_stock_and_takes_money_out_of_the_drawer(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['paid_cash' => 100, 'cash_received' => 100],
        );

        $this->assertSame(8.0, $product->fresh()->stock);

        $return = $this->refund($sale, [$sale->items->first()->id => 2]);

        // Negated, so SUM(total) across both rows is zero.
        $this->assertSame('return', $return->type);
        $this->assertSame('-100.00', $return->total);
        $this->assertSame('-100.00', $return->paid_cash);
        $this->assertSame($sale->id, $return->original_sale_id);

        // Goods back on the shelf.
        $this->assertSame(10.0, $product->fresh()->stock);
        $this->assertSame(
            1,
            StockMovement::where('type', StockMovementType::CustomerReturn)->count(),
        );

        // Money out of the drawer.
        $out = CashMovement::where('direction', CashMovement::OUT)->sole();
        $this->assertSame('100.00', $out->amount);

        // Net revenue and net drawer both back to zero.
        $this->assertSame(0.0, (float) PosSale::sum('total'));
        $this->assertSame(0.0, $this->account->fresh()->balance());
    }

    public function test_a_partial_return_only_gives_back_what_was_returned(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 3]],
            ['paid_cash' => 150, 'cash_received' => 150],
        );

        $return = $this->refund($sale, [$sale->items->first()->id => 1]);

        $this->assertSame('-50.00', $return->total);
        $this->assertSame(8.0, $product->fresh()->stock);   // 10 - 3 + 1
        $this->assertSame(100.0, (float) PosSale::sum('total'));
    }

    public function test_a_weighted_return_gives_back_the_weight_and_its_value(): void
    {
        $product = Product::factory()->create([
            'is_weighted' => true,
            'price_per_kg' => 240,
            'plu_code' => '1031',
            'stock' => 50,
        ]);

        $sale = $this->sell(
            [['product_id' => $product->id, 'weight_kg' => 0.5]],
            ['paid_cash' => 120, 'cash_received' => 120],
        );

        $this->assertSame(49.5, round($product->fresh()->stock, 3));

        // Half of what was sold comes back.
        $return = $this->refund($sale, [$sale->items->first()->id => 0.25]);

        $this->assertSame('-60.00', $return->total);
        $this->assertSame('-0.250', $return->items->first()->weight_kg);
        $this->assertSame(49.75, round($product->fresh()->stock, 3));
    }

    /**
     * Refunding at today's price would turn a price rise into free money for
     * whichever side happened to benefit.
     */
    public function test_it_refunds_the_price_that_was_actually_charged(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 1]],
            ['paid_cash' => 50, 'cash_received' => 50],
        );

        $product->update(['price' => 500]);

        $return = $this->refund($sale, [$sale->items->first()->id => 1]);

        $this->assertSame('-50.00', $return->total);
    }

    /** A discounted line refunds proportionally, not at list price. */
    public function test_a_discounted_line_refunds_proportionally(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        // 4 x 100 with 40 off the line = 360 paid, i.e. 90 per unit.
        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 4, 'discount' => 40]],
            ['paid_cash' => 360, 'cash_received' => 360],
        );

        $this->assertSame('360.00', $sale->total);

        $return = $this->refund($sale, [$sale->items->first()->id => 1]);

        // 100 gross less a quarter of the 40 discount = 90.
        $this->assertSame('-90.00', $return->total);
    }

    public function test_a_credit_sale_refunds_to_the_customer_account_not_the_drawer(): void
    {
        $product = Product::factory()->create(['price' => 80, 'stock' => 5]);
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 1]],
            [
                'payment_method' => PosSale::CREDIT,
                'party_id' => $customer->id,
                'paid_on_credit' => 80,
            ],
        );

        $this->assertSame(80.0, $customer->fresh()->balance());

        $this->refund($sale, [$sale->items->first()->id => 1], method: 'credit');

        // The debt is cleared rather than cash being handed over for money the
        // customer never actually paid.
        $this->assertSame(0.0, $customer->fresh()->balance());
        $this->assertSame(0, CashMovement::where('direction', CashMovement::OUT)->count());
        $this->assertSame(2, LedgerEntry::count());
    }

    /* ---- guards ------------------------------------------------------- */

    public function test_it_refuses_to_return_more_than_was_sold(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 2]], ['paid_cash' => 100]);

        $this->expectException(RuntimeException::class);

        $this->refund($sale, [$sale->items->first()->id => 3]);
    }

    /** Two half-returns must not add up to more than the whole. */
    public function test_it_tracks_what_has_already_been_returned(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 3]], ['paid_cash' => 150]);
        $itemId = $sale->items->first()->id;

        $this->refund($sale, [$itemId => 2]);

        $remaining = $this->app->make(ReturnRegistrar::class)
            ->returnableQuantities($sale->fresh('items'));

        $this->assertSame(1.0, $remaining[$itemId]);

        $this->expectException(RuntimeException::class);
        $this->refund($sale, [$itemId => 2]);
    }

    public function test_a_return_cannot_itself_be_returned(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 1]], ['paid_cash' => 50]);

        $return = $this->refund($sale, [$sale->items->first()->id => 1]);

        $this->expectException(RuntimeException::class);
        $this->refund($return, [$return->items->first()->id => 1]);
    }

    public function test_replaying_the_same_return_uuid_creates_nothing_twice(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 2]], ['paid_cash' => 100]);

        $uuid = (string) Str::uuid();
        $registrar = $this->app->make(ReturnRegistrar::class);

        $first = $registrar->register($sale, [$sale->items->first()->id => 1], 'cash', $uuid, $this->shift, $this->user->id);
        $second = $registrar->register($sale, [$sale->items->first()->id => 1], 'cash', $uuid, $this->shift, $this->user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PosSale::where('type', 'return')->count());
        $this->assertSame(1, StockMovement::where('type', StockMovementType::CustomerReturn)->count());
        $this->assertSame(1, CashMovement::where('direction', CashMovement::OUT)->count());
    }

    public function test_a_return_with_no_quantities_is_rejected(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 1]], ['paid_cash' => 50]);

        $this->expectException(RuntimeException::class);

        $this->refund($sale, [$sale->items->first()->id => 0]);
    }

    /**
     * The drawer must expect less after a refund, or the shift will look short
     * by exactly the amount that was legitimately handed back.
     */
    public function test_the_shift_expects_less_cash_after_a_refund(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        $sale = $this->sell(
            [['product_id' => $product->id, 'quantity' => 2]],
            ['paid_cash' => 100, 'cash_received' => 100],
        );

        $this->assertSame(100.0, $this->shift->fresh()->expectedCash());

        $this->refund($sale, [$sale->items->first()->id => 1]);

        $this->assertSame(50.0, $this->shift->fresh()->expectedCash());
    }
}
