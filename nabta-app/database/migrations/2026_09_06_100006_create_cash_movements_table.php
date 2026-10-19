<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only treasury ledger. Every movement of actual money lands here:
     * POS cash sales in, supplier payments out, expenses out, customer receipts in.
     */
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained()->restrictOnDelete();
            $table->string('direction');            // in | out
            $table->decimal('amount', 12, 2);       // always positive; direction carries the sign
            $table->nullableMorphs('reference');
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cash_account_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
