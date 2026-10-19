<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Cashier = 'cashier';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /**
     * What each role can do out of the box.
     *
     * Admin is deliberately NOT listed: it is granted everything by a Gate::before
     * hook, so adding a new permission can never accidentally lock the owner out
     * of their own shop.
     *
     * @return array<int, Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),

            // Sees the money and the stock behind it, and can correct both.
            // Cannot touch staff accounts or store settings.
            self::Accountant => [
                Permission::ProductsView,
                Permission::OrdersManage,
                Permission::InventoryView,
                Permission::InventoryManage,
                Permission::AccountingView,
                Permission::AccountingManage,
                Permission::ReportsView,
            ],

            // The till and nothing else. A cashier signing in lands on the POS
            // screen and has no reason to see the admin panel at all.
            self::Cashier => [
                Permission::PosUse,
                Permission::PosRefund,
            ],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
