<?php

namespace App\Http\Controllers;

use App\Exceptions\CartChangedException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Governorate;
use App\Models\Order;
use App\Services\Cart;
use App\Services\OrderPlacer;
use App\Services\ShippingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly ShippingCalculator $shipping,
    ) {}

    public function show(): View|RedirectResponse
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
        ]);
    }

    public function store(StoreOrderRequest $request, OrderPlacer $placer): RedirectResponse
    {
        try {
            $order = $placer->place($request->validated() + [
                'ip_address' => $request->ip(),
            ]);
        } catch (CartChangedException $e) {
            return back()->withInput()->with('cart_error', $e->getMessage());
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
}
