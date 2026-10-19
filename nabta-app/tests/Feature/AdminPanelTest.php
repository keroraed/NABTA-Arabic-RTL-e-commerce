<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_admin_panel_requires_a_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->get('/admin/orders')->assertRedirect('/admin/login');
    }

    public function test_every_admin_screen_loads(): void
    {
        Category::factory()->create();
        Product::factory()->create();
        Governorate::factory()->create();

        $this->actingAs($this->admin());

        foreach ([
            '/admin',
            '/admin/products',
            '/admin/products/create',
            '/admin/categories',
            '/admin/categories/create',
            '/admin/orders',
            '/admin/governorates',
            '/admin/manage-store-settings',
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    /**
     * Every POS, inventory and accounting screen, WITH a record in each table.
     *
     * Loading an empty table proves almost nothing: Filament never evaluates a
     * column's formatStateUsing/color closures when there are no rows, so a
     * broken closure sails straight through. That is exactly how a bad closure
     * parameter name reached the browser once already.
     */
    public function test_every_pos_and_accounting_screen_renders_with_real_rows(): void
    {
        $this->seedOneOfEverything();
        $this->actingAs($this->admin());

        foreach ([
            '/admin/warehouses',
            '/admin/warehouses/create',
            '/admin/stock-movements',
            '/admin/parties',
            '/admin/parties/create',
            '/admin/cash-accounts',
            '/admin/cash-accounts/create',
            '/admin/payments',
            '/admin/expenses',
            '/admin/expense-categories',
            '/admin/pos-terminals',
            '/admin/pos-terminals/create',
            '/admin/pos-sales',
            '/admin/products',
            '/admin/orders',
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    /** The POS sale detail view renders every derived column too. */
    public function test_the_pos_sale_detail_view_renders(): void
    {
        $sale = $this->seedOneOfEverything();

        $this->actingAs($this->admin())
            ->get("/admin/pos-sales/{$sale->getRouteKey()}")
            ->assertSuccessful()
            ->assertSee($sale->receipt_number);
    }

    /**
     * One row in every table the admin lists, including a complete POS sale
     * with its stock, cash and ledger consequences.
     */
    private function seedOneOfEverything(): \App\Models\PosSale
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'المحل', 'type' => 'store', 'is_active' => true, 'is_online_fulfilment' => true],
        );

        $account = \App\Models\CashAccount::create(['name' => 'خزينة المحل', 'type' => 'cash']);

        $terminal = \App\Models\PosTerminal::create([
            'name' => 'كاشير 1',
            'code' => 'T1',
            'warehouse_id' => $warehouse->id,
            'cash_account_id' => $account->id,
        ]);

        $user = $this->admin();

        $shift = \App\Models\CashShift::create([
            'pos_terminal_id' => $terminal->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_float' => 100,
            'status' => \App\Models\CashShift::OPEN,
        ]);

        Category::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 20]);

        // Both party types, so the balance column renders in both directions.
        $customer = \App\Models\Party::create([
            'type' => \App\Models\Party::TYPE_CUSTOMER, 'name' => 'أحمد', 'phone' => '01012345678',
        ]);
        $supplier = \App\Models\Party::create([
            'type' => \App\Models\Party::TYPE_SUPPLIER, 'name' => 'مورد المكسرات',
        ]);

        $category = \App\Models\ExpenseCategory::create(['name' => 'كهرباء']);

        \App\Models\Expense::create([
            'expense_category_id' => $category->id,
            'cash_account_id' => $account->id,
            'amount' => 250,
            'expense_date' => today(),
            'description' => 'فاتورة الكهرباء',
        ]);

        \App\Models\Payment::create([
            'party_id' => $supplier->id,
            'cash_account_id' => $account->id,
            'direction' => \App\Models\Payment::PAID,
            'amount' => 500,
            'payment_date' => today(),
            'method' => 'cash',
        ]);

        // A partial sale exercises the cash column, the ledger column and the
        // customer relation all at once.
        return $this->app->make(\App\Services\Pos\SaleRegistrar::class)->register(
            $terminal,
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'receipt_number' => 'T1-000001',
                'payment_method' => \App\Models\PosSale::PARTIAL,
                'party_id' => $customer->id,
                'paid_cash' => 30,
                'paid_on_credit' => 20,
                'cash_received' => 30,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ],
            $shift,
            $user->id,
        );
    }

    /**
     * The stock ledger and POS sales are append-only records written by the
     * services. Admin can read them; it must never offer a way to type one in.
     */
    public function test_ledger_records_cannot_be_created_or_edited_from_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/stock-movements/create')->assertNotFound();
        $this->get('/admin/pos-sales/create')->assertNotFound();
    }

    public function test_the_product_edit_screen_loads_for_a_real_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->get("/admin/products/{$product->getRouteKey()}/edit")
            ->assertSuccessful()
            ->assertSee($product->name_ar);
    }

    public function test_the_admin_renders_right_to_left_in_arabic(): void
    {
        Product::factory()->create();

        $response = $this->actingAs($this->admin())->get('/admin/products');

        $response->assertSuccessful();
        $response->assertSee('dir="rtl"', escape: false);
        $response->assertSee('lang="ar"', escape: false);

        // The column headers the client actually reads, not just a 200.
        foreach (['المنتج', 'القسم', 'السعر', 'المخزون'] as $heading) {
            $response->assertSee($heading, escape: false);
        }
    }

    public function test_the_order_list_is_split_by_status(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/orders');

        $response->assertSuccessful();

        foreach (['قيد المراجعة', 'تم التأكيد', 'تم الشحن', 'تم التسليم'] as $tab) {
            $response->assertSee($tab, escape: false);
        }
    }

    /**
     * Regression: Filament\Tables\Columns\ImageColumn resolves a bound value
     * either as a literal URL (only if filter_var() accepts it as absolute, which
     * a host-relative string like "/assets/x.webp" never does) or as a path on a
     * disk. Binding it to our already-resolved URL made it silently check
     * existence against the wrong (default 'public') disk and render nothing
     * for every product. The column must be bound to the raw path with
     * ->disk('assets') instead.
     */
    public function test_the_product_list_thumbnail_actually_resolves_to_the_asset_disk(): void
    {
        $product = Product::factory()->create();
        ProductImage::create([
            'product_id' => $product->id,
            // A real file under public/assets/products/, so this exercises
            // Filament's actual existence check, not just the accessor.
            'path' => 'products/masalek.webp',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/products');

        $response->assertSuccessful();
        $response->assertSee('/assets/products/masalek.webp', escape: false);
    }

    public function test_an_order_can_be_viewed_but_not_created_or_edited(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])->post(route('checkout.store'), [
            'customer_name' => 'أحمد محمد علي',
            'customer_phone' => '01012345678',
            'governorate_id' => $governorate->id,
            'city' => 'مدينة نصر',
            'address_line' => 'شارع عباس العقاد، عقار 15، الدور الثالث',
        ]);

        $order = Order::sole();

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->getRouteKey()}")
            ->assertSuccessful()
            ->assertSee($order->order_number);

        // Totals are snapshotted at checkout; there is no admin path to retype them.
        $this->get('/admin/orders/create')->assertNotFound();
        $this->get("/admin/orders/{$order->getRouteKey()}/edit")->assertNotFound();
    }
}
