<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();

            // COD refusal/no-answer rates in Egypt are high, so the status model
            // carries the confirmation call explicitly. See HANDOFF.md 3.
            $table->string('status')->default('pending');
            $table->string('payment_method')->default('cod');
            $table->string('channel')->default('web');

            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('customer_alt_phone', 20)->nullable();

            $table->foreignId('governorate_id')->constrained()->restrictOnDelete();
            // Snapshot: shipping zones get renamed and merged. A delivered order
            // must always show the governorate the customer actually chose.
            $table->string('governorate_name_snapshot');
            $table->string('city');
            $table->text('address_line');
            $table->string('landmark')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 8, 2);
            $table->decimal('total', 10, 2);
            $table->char('currency', 3)->default('EGP');

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
