<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A product can be filed under several categories. `products.category_id`
     * stays as the PRIMARY category — it drives the label on product cards, the
     * related-products rail and the POS tile — and is always also present in
     * this pivot, so "every product in category X" is one join.
     */
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
            $table->index('category_id');
        });

        // Backfill from the existing single-category column.
        $now = now();

        DB::table('products')
            ->select('id', 'category_id')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunk(500, function ($products) use ($now) {
                DB::table('category_product')->insertOrIgnore(
                    $products->map(fn ($p) => [
                        'product_id' => $p->id,
                        'category_id' => $p->category_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
