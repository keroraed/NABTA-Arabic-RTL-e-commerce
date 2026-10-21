<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Payment\OrderPaymentSettler;
use Illuminate\Console\Command;

/**
 * Releases the stock held by Paymob orders that were never paid.
 *
 * A customer who closes the tab on Paymob's page sends no callback at all, so
 * without this their reserved stock would stay off the shelf forever. Scheduled
 * in routes/console.php; the cut-off is PAYMOB_EXPIRES_AFTER_MINUTES, which is
 * also the lifetime of the Paymob intention itself.
 *
 * If a payment does land after this ran, OrderPaymentSettler::markPaid()
 * re-takes the stock and confirms the order anyway.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Fail online orders whose payment never arrived and release their stock';

    public function handle(OrderPaymentSettler $settler): int
    {
        $minutes = max(1, (int) config('services.paymob.expires_after_minutes', 60));

        $expired = 0;

        Order::query()
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('placed_at', '<', now()->subMinutes($minutes))
            // By id, not offset: each order leaves the WHERE set as it is
            // failed, and offset paging would then skip the next page's rows.
            ->lazyById(200)
            ->each(function (Order $order) use ($settler, &$expired) {
                $settler->markFailed($order, 'انتهت مهلة الدفع الإلكتروني');
                $expired++;
            });

        $this->info("Expired {$expired} unpaid online order(s).");

        return self::SUCCESS;
    }
}
