<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converts the single products.stock integer into opening `adjustment`
     * movements against the default warehouse, then drops the column.
     *
     * From here on, stock is derived from the movement ledger. Nothing should
     * read products.stock again — the storefront and admin were moved onto
     * stock_levels in the same change.
     */
    public function up(): void
    {
        $warehouseId = $this->defaultWarehouseId();
        $now = now();

        $rows = DB::table('products')->select('id', 'stock')->get();

        foreach ($rows as $row) {
            if ((int) $row->stock === 0) {
                continue;
            }

            $movementId = DB::table('stock_movements')->insertGetId([
                'product_id' => $row->id,
                'warehouse_id' => $warehouseId,
                'type' => 'adjustment',
                'quantity' => $row->stock,
                'notes' => 'رصيد افتتاحي — ترحيل من عمود المخزون القديم',
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stock_levels')->updateOrInsert(
                ['product_id' => $row->id, 'warehouse_id' => $warehouseId],
                ['quantity' => $row->stock, 'created_at' => $now, 'updated_at' => $now],
            );

            unset($movementId);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->default(0)->after('currency');
        });

        // Best-effort restore from the cached balances.
        foreach (DB::table('stock_levels')->get() as $level) {
            DB::table('products')
                ->where('id', $level->product_id)
                ->update(['stock' => (int) round($level->quantity)]);
        }

        DB::table('stock_movements')->where('type', 'adjustment')->delete();
    }

    /**
     * A fresh install has no warehouses yet, so create the one the shop starts
     * with. It doubles as the storefront's fulfilment location until an admin
     * says otherwise.
     */
    private function defaultWarehouseId(): int
    {
        $existing = DB::table('warehouses')->where('is_online_fulfilment', true)->value('id')
            ?? DB::table('warehouses')->orderBy('id')->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('warehouses')->insertGetId([
            'name' => 'المحل',
            'code' => 'MAIN',
            'type' => 'store',
            'is_active' => true,
            'is_online_fulfilment' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
