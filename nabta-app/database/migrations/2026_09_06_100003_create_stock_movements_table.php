<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Never UPDATE or DELETE a row here — a correction is another
     * movement in the opposite direction. This is what makes negative stock
     * self-correcting and gives a full audit trail per kilogram.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            $table->string('type');   // see App\Enums\StockMovementType

            // Signed: positive = in, negative = out. Decimal is mandatory —
            // this shop sells by weight, and 0.250 kg is a normal quantity.
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();

            $table->nullableMorphs('reference');    // sale, purchase, transfer, adjustment
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
