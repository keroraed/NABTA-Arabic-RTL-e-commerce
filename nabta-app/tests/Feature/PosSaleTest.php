<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\CashShift;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Pos\SaleRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The correctness the whole POS rests on: a sale must move stock, money and the
 * customer ledger together, exactly once.
 */
class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    private PosTerminal $terminal;
    private CashShift $shift;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'المحل', 'type' => 'store', 'is_active' => true, 'is_online_fulfilment' => true],
        );

        $account = CashAccount::create(['name' => 'خزينة المحل', 'type' => 'cash']);

        $this->terminal = PosTerminal::create([
            'name' => 'كاشير 1',
            'code' => 'T1',
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $account->id,
        ]);

        $this->user = User::factory()->create();

        $this->shift = CashShift::create([
            'pos_terminal_id' => $this->terminal->id,
            'opened_by' => $this->user->id,
            'opened_at' => now(),
            'opening_float' => 200,
            'status' => CashShift::OPEN,
        ]);
    }

    private function registrar(): SaleRegistrar
    {
        return $this->app->make(SaleRegistrar::class);
    }

    private function saleData(array $overrides = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'receipt_number' => 'T1-000001',
            'payment_method' => PosSale::CASH,
            'paid_cash' => 0,
            'items' => [],
        ], $overrides);
    }

    public function test_a_cash_sale_moves_stock_and_cash_together(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'paid_cash' => 100,
            'cash_received' => 100,
        ]), $this->shift, $this->user->id);

        $this->assertSame('100.00', $sale->total);
        $this->assertSame(8.0, $product->fresh()->stock);

        $movement = StockMovement::where('product_id', $product->id)
            ->where('type', StockMovementType::PosSale)
            ->sole();
        $this->assertSame('-2.000', $movement->quantity);

        $cash = CashMovement::sole();
        $this->assertSame(CashMovement::IN, $cash->direction);
        $this->assertSame('100.00', $cash->amount);

        // A cash sale must not touch the party ledger at all.
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_a_weighted_sale_prices_by_the_kilo_and_moves_stock_in_kg(): void
    {
        $product = Product::factory()->create([
            'is_weighted' => true,
            'price_per_kg' => 240,
            'plu_code' => '1031',
            'stock' => 50,
        ]);

        // The sample label from the shop: 100 g at 240/kg = 24.00.
        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'weight_kg' => 0.1]],
            'paid_cash' => 24,
            'cash_received' => 24,
        ]), $this->shift, $this->user->id);

        $this->assertSame('24.00', $sale->total);

        $item = $sale->items()->sole();
        $this->assertSame('0.100', $item->weight_kg);
        $this->assertSame('240.00', $item->unit_price_snapshot);

        $this->assertSame(49.9, round($product->fresh()->stock, 3));
    }

    public function test_a_credit_sale_debits_the_customer_and_takes_no_cash(): void
    {
        $product = Product::factory()->create(['price' => 80, 'stock' => 5]);
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => PosSale::CREDIT,
            'party_id' => $customer->id,
            'paid_on_credit' => 80,
        ]), $this->shift, $this->user->id);

        $entry = LedgerEntry::sole();
        $this->assertSame('80.00', $entry->debit);
        $this->assertSame('0.00', $entry->credit);

        // Positive means "owed to us" for a customer.
        $this->assertSame(80.0, $customer->fresh()->balance());

        // No money changed hands, so the drawer must be untouched.
        $this->assertSame(0, CashMovement::count());
        $this->assertSame('0.00', $sale->paid_cash);
    }

    public function test_a_partial_payment_splits_between_the_drawer_and_the_ledger(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => PosSale::PARTIAL,
            'party_id' => $customer->id,
            'paid_cash' => 60,
            'paid_on_credit' => 40,
            'cash_received' => 60,
        ]), $this->shift, $this->user->id);

        $this->assertSame('60.00', CashMovement::sole()->amount);
        $this->assertSame('40.00', LedgerEntry::sole()->debit);
        $this->assertSame(40.0, $customer->fresh()->balance());
    }

    /**
     * The single most important correctness requirement in the brief: a retried
     * upload must never produce a second sale, a second stock movement or a
     * second ledger entry.
     */
    public function test_replaying_the_same_uuid_creates_nothing_twice(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $data = $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => PosSale::PARTIAL,
            'party_id' => $customer->id,
            'paid_cash' => 60,
            'paid_on_credit' => 40,
            'cash_received' => 60,
        ]);

        $first = $this->registrar()->register($this->terminal, $data, $this->shift, $this->user->id);
        $second = $this->registrar()->register($this->terminal, $data, $this->shift, $this->user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PosSale::count());
        $this->assertSame(1, PosSaleItem::count());
        $this->assertSame(1, StockMovement::where('type', StockMovementType::PosSale)->count());
        $this->assertSame(1, CashMovement::count());
        $this->assertSame(1, LedgerEntry::count());

        // And the stock moved once, not twice.
        $this->assertSame(8.0, $product->fresh()->stock);
    }

    /**
     * Regression: a line discount was being subtracted twice — once when the
     * line was built and again in the totals — so every discounted sale
     * undercharged the customer, silently.
     *
     * subtotal is GROSS, discount is everything taken off, and total is the
     * difference. That is what the receipt already claims, so it had better be
     * what the numbers mean.
     */
    public function test_a_line_discount_is_applied_exactly_once(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'discount' => 40]],
            'paid_cash' => 360,
        ]), $this->shift, $this->user->id);

        $this->assertSame('400.00', $sale->subtotal);
        $this->assertSame('40.00', $sale->discount);
        $this->assertSame('360.00', $sale->total);

        // subtotal - discount = total, on the row itself.
        $this->assertSame(
            round((float) $sale->subtotal - (float) $sale->discount, 2),
            round((float) $sale->total, 2),
        );

        // And the item stores what that line actually contributed.
        $this->assertSame('360.00', $sale->items->first()->line_total);
    }

    /** A sale discount stacks with a line discount without double-counting. */
    public function test_line_and_sale_discounts_stack_correctly(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'discount' => 40]],
            'discount' => 60,
            'paid_cash' => 300,
        ]), $this->shift, $this->user->id);

        $this->assertSame('400.00', $sale->subtotal);
        $this->assertSame('100.00', $sale->discount);   // 40 line + 60 sale
        $this->assertSame('300.00', $sale->total);
    }

    public function test_it_snapshots_the_name_and_price_so_later_edits_do_not_rewrite_history(): void
    {
        $product = Product::factory()->create(['name_ar' => 'عسل أسود', 'price' => 95, 'stock' => 5]);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_cash' => 95,
        ]), $this->shift, $this->user->id);

        $product->update(['name_ar' => 'اسم جديد', 'price' => 250]);

        $item = $sale->items()->sole();
        $this->assertSame('عسل أسود', $item->product_name_snapshot);
        $this->assertSame('95.00', $item->unit_price_snapshot);
    }

    /**
     * The customer is at the counter holding the goods. A sale is never blocked
     * on stock, and the balance is allowed to go negative — it self-corrects on
     * the next goods receipt.
     */
    public function test_a_sale_is_never_blocked_by_stock_and_may_go_negative(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 1]);

        $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'paid_cash' => 50,
        ]), $this->shift, $this->user->id);

        $this->assertSame(-4.0, $product->fresh()->stock);
    }

    /**
     * The client's total is recomputed, never trusted. A mismatch is flagged for
     * review but the sale still lands, because the money was already taken.
     */
    public function test_a_wrong_client_total_is_flagged_but_not_rejected(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $sale = $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_cash' => 100,
            'claimed_total' => 1,       // devtools says one pound
        ]), $this->shift, $this->user->id);

        $this->assertSame('100.00', $sale->total);
        $this->assertTrue($sale->has_total_discrepancy);
    }

    public function test_a_failed_sale_leaves_no_partial_record(): void
    {
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $before = $product->stock;

        // Credit with no customer: the ledger write throws mid-transaction.
        try {
            $this->registrar()->register($this->terminal, $this->saleData([
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payment_method' => PosSale::CREDIT,
                'paid_on_credit' => 50,
            ]), $this->shift, $this->user->id);
            $this->fail('Expected the sale to be rejected.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, PosSale::count());
        $this->assertSame(0, StockMovement::where('type', StockMovementType::PosSale)->count());
        $this->assertSame($before, $product->fresh()->stock);
    }

    public function test_the_shift_expects_only_the_cash_that_was_actually_taken(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        $customer = Party::create(['type' => Party::TYPE_CUSTOMER, 'name' => 'أحمد']);

        $this->registrar()->register($this->terminal, $this->saleData([
            'uuid' => (string) Str::uuid(),
            'receipt_number' => 'T1-000001',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_cash' => 100,
        ]), $this->shift, $this->user->id);

        // A fully-credit sale adds nothing to the drawer.
        $this->registrar()->register($this->terminal, $this->saleData([
            'uuid' => (string) Str::uuid(),
            'receipt_number' => 'T1-000002',
            'payment_method' => PosSale::CREDIT,
            'party_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_on_credit' => 100,
        ]), $this->shift, $this->user->id);

        // 200 float + 100 cash, not 300.
        $this->assertSame(300.0, $this->shift->fresh()->expectedCash());
    }

    /**
     * The cached balances are derived data. Replaying the ledger must reproduce
     * them exactly — if it does not, the cache was wrong.
     */
    public function test_rebuilding_stock_levels_reproduces_the_cache(): void
    {
        $a = Product::factory()->create(['price' => 20, 'stock' => 30]);
        $b = Product::factory()->create(['price' => 40, 'stock' => 12]);

        $this->registrar()->register($this->terminal, $this->saleData([
            'items' => [
                ['product_id' => $a->id, 'quantity' => 3],
                ['product_id' => $b->id, 'quantity' => 1],
            ],
            'paid_cash' => 100,
        ]), $this->shift, $this->user->id);

        $before = [$a->fresh()->stock, $b->fresh()->stock];

        $this->app->make(StockLedger::class)->rebuildCache();

        $this->assertSame($before, [$a->fresh()->stock, $b->fresh()->stock]);
        $this->assertSame(27.0, $a->fresh()->stock);
    }
}
