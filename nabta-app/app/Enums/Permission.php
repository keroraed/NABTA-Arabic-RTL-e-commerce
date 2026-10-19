<?php

namespace App\Enums;

/**
 * Every distinct thing a user can be allowed to do.
 *
 * Deliberately coarse — one entry per area of the system, not one per button.
 * A shop with three staff does not need row-level ACL, and a permission list
 * nobody can hold in their head is one nobody configures correctly.
 */
enum Permission: string
{
    // Till
    case PosUse = 'pos.use';
    case PosRefund = 'pos.refund';

    // Catalogue
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';

    // Online orders
    case OrdersManage = 'orders.manage';

    // Stock
    case InventoryView = 'inventory.view';
    case InventoryManage = 'inventory.manage';

    // Parties, treasury, expenses
    case AccountingView = 'accounting.view';
    case AccountingManage = 'accounting.manage';

    case ReportsView = 'reports.view';

    // Admin-only by default
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';

    public function label(): string
    {
        return __('permissions.'.$this->value);
    }

    /** Grouped for the permission checkboxes on the user form. */
    public function group(): string
    {
        return match ($this) {
            self::PosUse, self::PosRefund => 'pos',
            self::ProductsView, self::ProductsManage, self::OrdersManage => 'shop',
            self::InventoryView, self::InventoryManage => 'inventory',
            self::AccountingView, self::AccountingManage, self::ReportsView => 'accounting',
            self::UsersManage, self::SettingsManage => 'system',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->all();
    }

    /** @return array<string, array<string, string>> group => [value => label] */
    public static function grouped(): array
    {
        return collect(self::cases())
            ->groupBy(fn (self $p) => $p->group())
            ->map(fn ($items) => $items->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all())
            ->all();
    }
}
