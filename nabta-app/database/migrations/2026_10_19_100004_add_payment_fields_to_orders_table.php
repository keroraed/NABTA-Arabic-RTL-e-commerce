<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online payment through Paymob. `payment_method` already exists (always
     * 'cod' until now); these columns track whether the money actually arrived
     * and tie the order to Paymob's own identifiers for reconciliation.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->string('paymob_intention_id')->nullable()->after('payment_status');
            $table->string('paymob_order_id')->nullable()->index()->after('paymob_intention_id');
            $table->string('paymob_transaction_id')->nullable()->unique()->after('paymob_order_id');
            $table->timestamp('paid_at')->nullable()->after('placed_at');
            // Set once the shop has been emailed, so a webhook and a browser
            // redirect for the same payment cannot both send it.
            $table->timestamp('admin_notified_at')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['paymob_transaction_id']);
            $table->dropIndex(['paymob_order_id']);
            $table->dropColumn([
                'payment_status', 'paymob_intention_id', 'paymob_order_id',
                'paymob_transaction_id', 'paid_at', 'admin_notified_at',
            ]);
        });
    }
};
