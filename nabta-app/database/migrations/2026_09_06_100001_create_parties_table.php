<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suppliers and customers in one table: both carry a running balance derived
     * from the same ledger, and splitting them would duplicate every piece of
     * that logic. Customers here are deliberately lightweight — a name and a
     * phone, no address and no login.
     */
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->string('type');                 // supplier | customer
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('phone');                 // POS looks customers up by phone
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
