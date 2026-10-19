<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a return line back to the exact sale line it reverses.
     *
     * Without it, "how much of this line has already been refunded" can only be
     * guessed from the product id — which is wrong the moment a receipt has the
     * same product on two lines at two different weights, which is normal here.
     */
    public function up(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->foreignId('original_item_id')
                ->nullable()
                ->after('product_id')
                ->constrained('pos_sale_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('original_item_id');
        });
    }
};
