<?php

namespace App\Services\Payment;

use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AdminOrderNotifier;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the outcome of an online payment to its order.
 *
 * A Paymob order reserves its stock the moment it is placed (the same
 * `online_sale` movement a COD order records), so two customers cannot pay for
 * the last unit at once. From there:
 *
 *   paid     -> confirmed + paid. The shop is emailed.
 *   declined -> failed_payment. The reservation is reversed.
 *   expired  -> same as declined (see the orders:expire-unpaid command).
 *
 * Paymob retries webhooks and the browser redirect races the webhook, so every
 * path here is idempotent and re-reads the order under a row lock.
 */
class OrderPaymentSettler
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly AdminOrderNotifier $notifier,
    ) {}

    /** Finds the order a verified transaction belongs to and settles it. */
    public function settle(PaymobTransaction $transaction): ?Order
    {
        $order = $this->findOrder($transaction);

        if (! $order) {
            Log::warning('Paymob transaction for an unknown order', [
                'transaction' => $transaction->id,
                'merchant_order_id' => $transaction->merchantOrderId,
                'paymob_order_id' => $transaction->paymobOrderId,
            ]);

            return null;
        }

        if (! $order->isPaymob()) {
            Log::warning('Paymob transaction for a non-Paymob order', ['order' => $order->order_number]);

            return $order;
        }

        // Wallet payments can report "pending" while the customer approves on
        // their phone. Nothing to do until the final callback arrives.
        if ($transaction->pending) {
            return $order;
        }

        if ($transaction->isSuccessful()) {
            if ($transaction->amountCents !== $order->totalInPiastres()
                || strtoupper($transaction->currency) !== strtoupper((string) $order->currency)) {
                // Signed by Paymob, but not for what this order costs. Never
                // mark it paid; leave it for a human to reconcile.
                Log::error('Paymob amount mismatch — order NOT marked paid', [
                    'order' => $order->order_number,
                    'expected_cents' => $order->totalInPiastres(),
                    'received_cents' => $transaction->amountCents,
                    'currency' => $transaction->currency,
                    'transaction' => $transaction->id,
                ]);

                return $order;
            }

            return $this->markPaid($order, $transaction);
        }

        if ($order->isPaid()) {
            // A failed retry, a void or a refund AFTER the order was paid. Money
            // questions like that are for staff, not for an automatic rollback.
            Log::warning('Paymob reported a non-success transaction for an already-paid order', [
                'order' => $order->order_number,
                'transaction' => $transaction->id,
                'voided' => $transaction->voided,
                'refunded' => $transaction->refunded,
            ]);

            return $order;
        }

        return $this->markFailed($order, $this->failureReason($transaction));
    }

    /**
     * Binds the transaction to an order by Paymob's own order id, which IS one
     * of the HMAC-signed fields. merchant_order_id is NOT signed — anyone
     * holding a valid callback could change it to another customer's order
     * number — so it is only used as a consistency check, never as the key.
     */
    public function findOrder(PaymobTransaction $transaction): ?Order
    {
        if (! $transaction->paymobOrderId) {
            return null;
        }

        $order = Order::query()->where('paymob_order_id', $transaction->paymobOrderId)->first();

        if ($order && $transaction->merchantOrderId && $transaction->merchantOrderId !== $order->order_number) {
            Log::warning('Paymob callback merchant_order_id does not match its signed order id', [
                'paymob_order_id' => $transaction->paymobOrderId,
                'merchant_order_id' => $transaction->merchantOrderId,
            ]);

            return null;
        }

        return $order;
    }

    /**
     * Fails this browser's earlier Paymob order if it is still unpaid — the
     * customer came back from Paymob and is checking out again, and the old
     * order's stock reservation would otherwise block the new one.
     */
    public function releaseAbandoned(?string $orderNumber): void
    {
        if (! $orderNumber) {
            return;
        }

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->first();

        if ($order) {
            $this->markFailed($order, 'أعاد العميل إتمام الطلب قبل إكمال الدفع');
        }
    }

    public function markPaid(Order $order, PaymobTransaction $transaction): Order
    {
        $justPaid = DB::transaction(function () use ($order, $transaction) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isPaid()) {
                return false;
            }

            // The order had already been failed (declined first attempt, or the
            // expiry sweep ran) and its stock released, yet the money arrived.
            // The customer has paid, so the order stands: take the stock again.
            // It may go negative — the same trade-off the POS makes — and the
            // low-stock report will show it.
            if ($locked->status === Order::STATUS_FAILED_PAYMENT) {
                $this->reserveStock($locked);
            }

            $locked->forceFill([
                'status' => Order::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'payment_status' => Order::PAYMENT_PAID,
                'paid_at' => now(),
                'paymob_transaction_id' => $transaction->id ?: null,
                'paymob_order_id' => $locked->paymob_order_id ?? $transaction->paymobOrderId,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ])->save();

            return true;
        });

        $order->refresh();

        if ($justPaid) {
            $this->notifier->notify($order);
        }

        return $order;
    }

    /** Fails an unpaid online order and puts its reserved stock back. */
    public function markFailed(Order $order, string $reason): Order
    {
        DB::transaction(function () use ($order, $reason) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Order::STATUS_PENDING_PAYMENT) {
                return;
            }

            $this->releaseStock($locked);

            $locked->forceFill([
                'status' => Order::STATUS_FAILED_PAYMENT,
                'payment_status' => Order::PAYMENT_FAILED,
                'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 255),
            ])->save();
        });

        return $order->refresh();
    }

    /**
     * Reverses whatever this order still holds, per product and warehouse, by
     * netting its own ledger movements. Reading the ledger rather than the
     * order lines means a repeated call releases nothing twice.
     */
    private function releaseStock(Order $order): void
    {
        $held = StockMovement::query()
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->getKey())
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as net')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        foreach ($held as $row) {
            $net = round((float) $row->net, 3);

            if ($net >= 0) {
                continue;
            }

            $this->stock->record(
                product: (int) $row->product_id,
                warehouse: (int) $row->warehouse_id,
                type: StockMovementType::OnlineSaleReleased,
                quantity: abs($net),
                reference: $order,
                notes: 'إلغاء حجز الطلب '.$order->order_number,
            );
        }
    }

    private function reserveStock(Order $order): void
    {
        $warehouse = Warehouse::onlineFulfilment();

        if (! $warehouse) {
            return;
        }

        foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
            $this->stock->record(
                product: (int) $item->product_id,
                warehouse: $warehouse,
                type: StockMovementType::OnlineSale,
                quantity: (float) $item->qty,
                reference: $order,
                notes: 'طلب '.$order->order_number.' (دفع متأخر)',
            );
        }
    }

    private function failureReason(PaymobTransaction $transaction): string
    {
        return trim('لم تكتمل عملية الدفع عبر Paymob'.($transaction->message ? ': '.$transaction->message : ''));
    }
}
