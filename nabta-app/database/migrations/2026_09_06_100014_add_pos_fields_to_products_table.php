<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Sold by weight (price_per_kg x weight from the scale label) rather
            // than by unit.
            $table->boolean('is_weighted')->default(false)->after('stock');
            $table->string('plu_code')->nullable()->unique()->after('is_weighted');
            $table->decimal('price_per_kg', 10, 2)->nullable()->after('plu_code');
            $table->string('barcode')->nullable()->index()->after('price_per_kg');
            $table->boolean('sync_to_scale')->default(false)->after('barcode');

            // Weighted average cost, recalculated on each purchase receipt.
            // Weighted average rather than FIFO because this shop sells loose
            // bulk goods that are physically mixed in one container — FIFO would
            // be an accounting fiction here.
            $table->decimal('average_cost', 12, 2)->default(0)->after('sync_to_scale');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_weighted', 'plu_code', 'price_per_kg', 'barcode',
                'sync_to_scale', 'average_cost',
            ]);
        });
    }
};
