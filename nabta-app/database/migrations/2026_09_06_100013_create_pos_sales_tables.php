<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();

            // Client-generated at the moment of sale. The unique index is the
            // idempotency guarantee for the offline sync layer: a retried upload
            // hits this constraint and returns the existing sale rather than
            // creating a duplicate. Enforced in the DB, not just in PHP, because
            // two concurrent requests can both pass a SELECT check.
            $table->uuid('uuid')->unique();

            $table->foreignId('pos_terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number');

            // Returns are not built in this phase, but the columns exist now so
            // adding them later is not a migration against live sales data.
            $table->string('type')->default('sale');    // sale | return
            $table->foreignId('original_sale_id')->nullable()->constrained('pos_sales')->nullOnDelete();

            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();  // credit customer

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            // No VAT is applied today (see docs/POS.md). The column exists so
            // introducing it later does not reshape historical rows.
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->string('payment_method');            // cash | credit | partial
            $table->decimal('paid_cash', 12, 2)->default(0);
            $table->decimal('paid_on_credit', 12, 2)->default(0);
            $table->decimal('cash_received', 12, 2)->default(0);
            $table->decimal('change_given', 12, 2)->default(0);

            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();

            // sold_at is the real time of sale on the terminal, which is NOT the
            // time it reached the server. The offline layer will populate the
            // skew columns; online sales leave them at zero.
            $table->timestamp('sold_at');
            $table->integer('clock_offset_ms')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->boolean('has_total_discrepancy')->default(false);
            $table->timestamps();

            $table->index(['pos_terminal_id', 'sold_at']);
            $table->index('payment_method');
            $table->unique(['pos_terminal_id', 'receipt_number']);
        });

        Schema::create('pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Snapshots: prices change, historical receipts must not.
            $table->string('product_name_snapshot');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('cost_at_sale', 12, 2)->default(0);

            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('weight_kg', 12, 3)->nullable();   // weighted items only
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_items');
        Schema::dropIfExists('pos_sales');
    }
};
