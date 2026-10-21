<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Cart;
use App\Services\Payment\OrderPaymentSettler;
use App\Services\Payment\PaymobService;
use App\Services\Payment\PaymobTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paymob's two callbacks. They are independent — either can arrive first, and
 * Paymob retries the webhook — so both just verify the signature and hand the
 * transaction to OrderPaymentSettler, which is idempotent.
 *
 * The webhook is the authoritative one (it arrives even if the customer closes
 * the tab). The redirect exists to send the customer somewhere sensible.
 */
class PaymobController extends Controller
{
    public function __construct(
        private readonly PaymobService $paymob,
        private readonly OrderPaymentSettler $settler,
    ) {}

    /** "Transaction processed" — server to server, POST JSON, CSRF-exempt. */
    public function webhook(Request $request): JsonResponse
    {
        // Paymob also posts TOKEN callbacks (saved cards). Not used; acknowledge
        // so Paymob does not keep retrying them.
        if ($request->input('type') !== 'TRANSACTION') {
            return response()->json(['ok' => true]);
        }

        $obj = $request->input('obj');

        $hmac = $request->query('hmac');

        if (! is_array($obj) || ! is_string($hmac) || ! $this->paymob->verifyProcessedCallback($obj, $hmac)) {
            Log::warning('Paymob webhook rejected: bad HMAC', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 403);
        }

        $this->settler->settle(PaymobTransaction::fromProcessedCallback($obj));

        return response()->json(['ok' => true]);
    }

    /** "Transaction response" — the customer's browser coming back from Paymob. */
    public function callback(Request $request, Cart $cart): RedirectResponse
    {
        $query = $request->query();

        if (! $this->paymob->verifyResponseCallback($query)) {
            Log::warning('Paymob redirect rejected: bad HMAC', ['ip' => $request->ip()]);

            return redirect()->route('shop')->with('status', 'تعذّر التحقق من نتيجة الدفع. إذا تم خصم المبلغ سنتواصل معك لتأكيد طلبك.');
        }

        $transaction = PaymobTransaction::fromResponseQuery($query);
        $order = $this->settler->settle($transaction);

        if (! $order) {
            return redirect()->route('shop')->with('status', 'لم نعثر على الطلب المرتبط بعملية الدفع. تواصل معنا إذا تم خصم المبلغ.');
        }

        // Only the browser that placed the order gets to see its details. A
        // replayed or shared redirect URL settles the payment but shows nothing.
        $ownsOrder = session(CheckoutController::PENDING_PAYMENT_SESSION_KEY) === $order->order_number
            || session('recent_order') === $order->order_number;

        // Paymob says the money moved but the settler would not accept it (an
        // amount mismatch, logged for staff). Do not pretend it is fine, and
        // do not clear the cart — but do not tell them it failed either.
        if ($transaction->isSuccessful() && ! $order->isPaid()) {
            return redirect()->route('shop')->with(
                'status',
                'استلمنا عملية الدفع للطلب '.$order->order_number.' لكن تعذّر تأكيدها تلقائياً. سنراجعها ونتواصل معك قريباً.',
            );
        }

        $stillPending = $transaction->pending && $order->status === Order::STATUS_PENDING_PAYMENT;

        if ($order->isPaid() || $stillPending) {
            if (! $ownsOrder) {
                return redirect()->route('shop')->with('status', 'تم استلام نتيجة الدفع للطلب '.$order->order_number.'.');
            }

            // Paid, or a wallet payment still being approved. Either way the
            // customer has finished at Paymob, so the cart has done its job.
            $cart->clear();
            session()->forget(CheckoutController::PENDING_PAYMENT_SESSION_KEY);
            session()->put('recent_order', $order->order_number);

            return redirect()->route('orders.confirmation', $order);
        }

        session()->forget(CheckoutController::PENDING_PAYMENT_SESSION_KEY);

        // Failed: the cart is untouched, so checkout shows their items again.
        return redirect()->route($cart->isEmpty() ? 'shop' : 'checkout')->with(
            'cart_error',
            'لم تكتمل عملية الدفع ولم يتم خصم أي مبلغ. يمكنك المحاولة مرة أخرى أو اختيار الدفع عند الاستلام.',
        );
    }
}
