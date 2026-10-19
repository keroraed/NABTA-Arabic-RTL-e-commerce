<?php

namespace App\Http\Controllers;

use App\Exceptions\CartChangedException;
use App\Exceptions\PaymobException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Governorate;
use App\Models\Order;
use App\Services\Cart;
use App\Services\OrderPlacer;
use App\Services\Payment\OrderPaymentSettler;
use App\Services\Payment\PaymobService;
use App\Services\ShippingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * The order number of this browser's Paymob order that has been sent to
     * Paymob but not yet settled. See PaymobController::callback().
     */
    public const PENDING_PAYMENT_SESSION_KEY = 'paymob_pending_order';

    public function __construct(
        private readonly Cart $cart,
        private readonly ShippingCalculator $shipping,
    ) {}

    public function show(PaymobService $paymob): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop')->with('status', 'سلتك فارغة — تصفّح المنتجات أولاً.');
        }

        $subtotal = $this->cart->subtotal();

        return view('storefront.checkout', [
            'lines' => $this->cart->lines(),
            'subtotal' => $subtotal,
            'governorates' => Governorate::active()->orderBy('sort_order')->get(),
            'freeShippingThreshold' => $this->shipping->freeShippingThreshold(),
            'qualifiesForFreeShipping' => $this->shipping->qualifiesForFreeShipping($subtotal),
            'onlinePaymentAvailable' => $paymob->isConfigured(),
        ]);
    }

    public function store(
        StoreOrderRequest $request,
        OrderPlacer $placer,
        PaymobService $paymob,
        OrderPaymentSettler $settler,
    ): RedirectResponse {
        // The customer went to Paymob, came back with the browser's back button
        // and is checking out again. Their earlier unpaid order is still holding
        // the stock, and would otherwise block this one with "only 0 left".
        $settler->releaseAbandoned(session()->pull(self::PENDING_PAYMENT_SESSION_KEY));

        try {
            $order = $placer->place($request->validated() + [
                'ip_address' => $request->ip(),
            ]);
        } catch (CartChangedException $e) {
            return back()->withInput()->with('cart_error', $e->getMessage());
        }

        if ($order->isPaymob()) {
            return $this->redirectToPaymob($order, $paymob, $settler);
        }

        $this->cart->clear();

        // Confirmation is keyed to the session so a shared or guessed order URL
        // cannot expose someone else's name, phone and address.
        session()->put('recent_order', $order->order_number);

        return redirect()->route('orders.confirmation', $order);
    }

    public function confirmation(Order $order): View|RedirectResponse
    {
        if (session('recent_order') !== $order->order_number) {
            return redirect()->route('shop');
        }

        $order->load('items');

        return view('storefront.confirmation', ['order' => $order]);
    }

    /**
     * The cart is deliberately NOT cleared here: if the payment fails, the
     * customer comes back to a checkout that still has their items. It is
     * cleared once Paymob confirms the payment.
     */
    private function redirectToPaymob(Order $order, PaymobService $paymob, OrderPaymentSettler $settler): RedirectResponse
    {
        try {
            $url = $paymob->startCheckout($order);
        } catch (PaymobException $e) {
            Log::warning('Could not start Paymob checkout', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            $settler->markFailed($order, 'تعذّر الاتصال ببوابة الدفع');

            return back()->withInput()->with(
                'cart_error',
                'تعذّر الاتصال ببوابة الدفع الإلكتروني الآن. لم يتم خصم أي مبلغ — حاول مرة أخرى بعد قليل أو اختر الدفع عند الاستلام.',
            );
        }

        session()->put(self::PENDING_PAYMENT_SESSION_KEY, $order->order_number);

        return redirect()->away($url);
    }
}
