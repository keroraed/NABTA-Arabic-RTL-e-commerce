<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // The static site used string ids ("masalek") in URLs. Keeping them as
            // the slug preserves every existing link: product.html?id=masalek -> /p/masalek
            $table->string('slug')->unique();
            $table->string('legacy_id')->nullable()->unique();
            $table->string('sku')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('name_ar');
            $table->text('short_desc_ar')->nullable();
            $table->text('description_ar')->nullable();
            $table->text('ingredients_ar')->nullable();
            $table->text('usage_ar')->nullable();
            $table->string('weight_label')->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->char('currency', 3)->default('EGP');

            // NOTE: the POS phase replaces this with an append-only stock_movements
            // ledger (see NABTA-POS-Scale-Offline-Prompt.md, Part 4). Do not build
            // anything new on top of this column.
            $table->integer('stock')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
