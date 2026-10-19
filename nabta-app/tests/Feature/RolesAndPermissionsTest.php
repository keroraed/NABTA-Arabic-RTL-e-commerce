<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CashAccount;
use App\Models\CashShift;
use App\Models\PosSale;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\SaleRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Access control, tested from the outside: what each role can actually reach
 * over HTTP, not just what the permission arrays say.
 */
class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(): PosTerminal
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'المحل', 'type' => 'store', 'is_active' => true, 'is_online_fulfilment' => true],
        );

        return PosTerminal::firstOrCreate(
            ['code' => 'T1'],
            [
                'name' => 'كاشير 1',
                'warehouse_id' => $warehouse->id,
                'cash_account_id' => CashAccount::create(['name' => 'خزينة', 'type' => 'cash'])->id,
            ],
        );
    }

    /* ---- the three roles ---------------------------------------------- */

    public function test_a_cashier_has_the_till_and_nothing_else(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->assertTrue($cashier->can(Permission::PosUse->value));
        $this->assertTrue($cashier->can(Permission::PosRefund->value));

        foreach ([
            Permission::ProductsManage,
            Permission::InventoryManage,
            Permission::AccountingView,
            Permission::ReportsView,
            Permission::UsersManage,
            Permission::SettingsManage,
        ] as $denied) {
            $this->assertFalse(
                $cashier->can($denied->value),
                "A cashier must not have {$denied->value}",
            );
        }
    }

    /**
     * The admin panel would be an empty shell for a cashier, so they are not
     * given one — they land on the till instead.
     */
    public function test_a_cashier_cannot_reach_the_admin_panel(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->assertFalse($cashier->canAccessAdminPanel());
        $this->assertStringContainsString('/pos', $cashier->homeRoute());

        $this->actingAs($cashier)->get('/admin')->assertForbidden();
        $this->actingAs($cashier)->get('/admin/products')->assertForbidden();
    }

    public function test_a_cashier_can_open_the_till(): void
    {
        $this->terminal();
        $cashier = User::factory()->cashier()->create();

        // Redirects into the terminal/shift flow rather than being refused.
        $this->actingAs($cashier)->get('/pos')->assertRedirect();
        $this->actingAs($cashier)->get('/pos/terminal')->assertSuccessful();
    }

    public function test_an_accountant_sees_the_books_but_not_the_till_or_the_staff(): void
    {
        $accountant = User::factory()->accountant()->create();

        $this->assertTrue($accountant->can(Permission::AccountingManage->value));
        $this->assertTrue($accountant->can(Permission::InventoryManage->value));
        $this->assertTrue($accountant->can(Permission::ReportsView->value));

        $this->assertFalse($accountant->can(Permission::PosUse->value));
        $this->assertFalse($accountant->can(Permission::UsersManage->value));
        $this->assertFalse($accountant->can(Permission::SettingsManage->value));

        $this->actingAs($accountant)->get('/pos')->assertForbidden();
        $this->actingAs($accountant)->get('/admin/users')->assertForbidden();
        $this->actingAs($accountant)->get('/admin/parties')->assertSuccessful();
    }

    public function test_an_admin_can_reach_everything(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }

        $this->actingAs($admin)->get('/admin/users')->assertSuccessful();
    }

    /**
     * A new permission added by a future migration must not silently lock the
     * owner out of their own shop — that is what Gate::before is for.
     */
    public function test_an_admin_is_granted_permissions_that_do_not_exist_yet(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('some.future.permission'));
    }

    /* ---- overrides and deactivation ------------------------------------ */

    public function test_a_per_user_override_widens_a_role(): void
    {
        $cashier = User::factory()->cashier()->create([
            'permissions' => [
                Permission::PosUse->value,
                Permission::PosRefund->value,
                Permission::InventoryView->value,
            ],
        ]);

        $this->assertTrue($cashier->can(Permission::InventoryView->value));
        $this->assertFalse($cashier->can(Permission::InventoryManage->value));

        // Having a non-till permission now earns them the admin panel.
        $this->assertTrue($cashier->canAccessAdminPanel());
    }

    public function test_a_per_user_override_narrows_a_role(): void
    {
        // A cashier trusted to sell but not to hand money back.
        $cashier = User::factory()->cashier()->create([
            'permissions' => [Permission::PosUse->value],
        ]);

        $this->assertTrue($cashier->can(Permission::PosUse->value));
        $this->assertFalse($cashier->can(Permission::PosRefund->value));

        $this->actingAs($cashier)->get('/pos/returns')->assertForbidden();
    }

    public function test_an_inactive_user_loses_every_door(): void
    {
        $admin = User::factory()->admin()->inactive()->create();
        $cashier = User::factory()->cashier()->inactive()->create();

        // Even an admin: deactivating is how a departing employee is stopped.
        $this->assertFalse($admin->can(Permission::UsersManage->value));
        $this->assertFalse($admin->canAccessAdminPanel());
        $this->assertFalse($cashier->can(Permission::PosUse->value));

        $this->actingAs($cashier)->get('/pos/terminal')->assertForbidden();
    }

    public function test_an_unknown_permission_in_the_override_is_ignored(): void
    {
        $user = User::factory()->cashier()->create([
            'permissions' => [Permission::PosUse->value, 'not.a.real.permission'],
        ]);

        $this->assertSame([Permission::PosUse->value], $user->effectivePermissions());
    }

    /* ---- returns are separately gated ---------------------------------- */

    public function test_returns_require_their_own_permission(): void
    {
        $terminal = $this->terminal();
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $admin = User::factory()->admin()->create();

        $shift = CashShift::create([
            'pos_terminal_id' => $terminal->id,
            'opened_by' => $admin->id,
            'opened_at' => now(),
            'opening_float' => 0,
            'status' => CashShift::OPEN,
        ]);

        $sale = $this->app->make(SaleRegistrar::class)->register($terminal, [
            'uuid' => (string) Str::uuid(),
            'receipt_number' => 'T1-000001',
            'payment_method' => PosSale::CASH,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'paid_cash' => 50,
        ], $shift, $admin->id);

        $canRefund = User::factory()->cashier()->create();
        $cannotRefund = User::factory()->cashier()->create([
            'permissions' => [Permission::PosUse->value],
        ]);

        $this->withSession(['pos_terminal_id' => $terminal->id])
            ->actingAs($canRefund)
            ->get('/pos/returns?receipt=T1-000001')
            ->assertSuccessful();

        $this->withSession(['pos_terminal_id' => $terminal->id])
            ->actingAs($cannotRefund)
            ->post("/pos/returns/{$sale->id}", [
                'uuid' => (string) Str::uuid(),
                'refund_method' => 'cash',
                'quantities' => [$sale->items->first()->id => 1],
            ])
            ->assertForbidden();

        $this->assertSame(0, PosSale::where('type', 'return')->count());
    }

    /* ---- language ------------------------------------------------------ */

    public function test_staff_can_switch_the_interface_language(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/locale/en')->assertRedirect();
        $this->assertSame('en', session('app_locale'));

        $this->actingAs($admin)->get('/locale/ar')->assertRedirect();
        $this->assertSame('ar', session('app_locale'));

        // Anything else is not a language this app has.
        $this->actingAs($admin)->get('/locale/fr')->assertNotFound();
    }

    public function test_role_and_permission_labels_exist_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (Role::cases() as $role) {
                $this->assertNotSame('roles.'.$role->value, $role->label(), "missing {$locale} label for role {$role->value}");
            }

            foreach (Permission::cases() as $permission) {
                $this->assertNotSame(
                    'permissions.'.$permission->value,
                    $permission->label(),
                    "missing {$locale} label for permission {$permission->value}",
                );
            }
        }
    }
}
