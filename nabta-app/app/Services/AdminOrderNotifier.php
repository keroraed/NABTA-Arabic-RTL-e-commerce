<?php

namespace App\Services;

use App\Mail\AdminOrderPlacedNotification;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Emails the shop about a new order: a COD order as soon as it is placed, a
 * Paymob order once the payment is confirmed.
 *
 * The email is a convenience for staff, never part of the sale. It is sent
 * after the HTTP response has gone out, and any SMTP failure — host down, bad
 * credentials, rejected sender — is logged and swallowed, so it can neither
 * fail nor slow down a customer's checkout.
 */
class AdminOrderNotifier
{
    public function notify(Order $order): void
    {
        if ($this->recipients() === []) {
            return;
        }

        defer(fn () => $this->send($order), 'admin-order-'.$order->getKey());
    }

    /**
     * Sends at most once per order. The admin_notified_at stamp is claimed with
     * a conditional UPDATE, so the Paymob webhook and the browser redirect —
     * which can land at the same moment — cannot both send it.
     */
    public function send(Order $order): bool
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            return false;
        }

        $claimed = Order::query()
            ->whereKey($order->getKey())
            ->whereNull('admin_notified_at')
            ->update(['admin_notified_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        try {
            Mail::to($recipients)->send(new AdminOrderPlacedNotification($order->fresh(['items'])));

            return true;
        } catch (Throwable $e) {
            // Release the claim so a later callback for the same order can retry.
            Order::query()->whereKey($order->getKey())->update(['admin_notified_at' => null]);

            Log::warning('Admin order notification email failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return list<string> */
    public function recipients(): array
    {
        $configured = (string) config('nabta.admin_notification_email', '');

        return array_values(array_filter(
            array_map('trim', explode(',', $configured)),
            fn (string $address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }
}
