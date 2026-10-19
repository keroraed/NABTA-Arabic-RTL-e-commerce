<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\PosTerminal;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * The minimum a shop needs to open a till: one warehouse, one drawer, one
 * terminal, and the expense categories an Egyptian retail shop actually uses.
 *
 * Everything here is firstOrCreate, so re-seeding never duplicates a warehouse
 * that already has a stock ledger hanging off it.
 */
class PosFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'المحل',
                'type' => 'store',
                'is_active' => true,
                'is_online_fulfilment' => true,
            ],
        );

        $cashAccount = CashAccount::firstOrCreate(
            ['name' => 'خزينة المحل'],
            ['type' => 'cash', 'opening_balance' => 0, 'is_active' => true],
        );

        PosTerminal::firstOrCreate(
            ['code' => 'T1'],
            [
                'name' => 'كاشير 1',
                'warehouse_id' => $warehouse->id,
                'cash_account_id' => $cashAccount->id,
                'is_active' => true,
            ],
        );

        foreach (['إيجار', 'كهرباء', 'مياه', 'مرتبات', 'صيانة', 'نقل', 'مستلزمات تشغيل', 'أخرى'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
