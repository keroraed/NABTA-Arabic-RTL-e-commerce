<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart;
use App\Services\ShippingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly ShippingCalculator $shipping,
    ) {}

    public function add(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $qty = (int) $request->integer('qty', 1);
        $this->cart->add($product, max(1, $qty));

        return $this->respond($request, "تمت الإضافة إلى السلة — {$product->name_ar}");
    }

    public function update(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->cart->set($product, (int) $request->integer('qty'));

        return $this->respond($request, 'تم تحديث السلة');
    }

    public function remove(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->cart->remove($product->id);

        return $this->respond($request, 'تم حذف المنتج من السلة');
    }

    public function state(): JsonResponse
    {
        return response()->json($this->payload());
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($this->payload() + ['message' => $message]);
        }

        return back()->with('status', $message);
    }

    private function payload(): array
    {
        $subtotal = $this->cart->subtotal();

        return [
            'count' => $this->cart->count(),
            'subtotal' => $subtotal,
            'free_shipping_threshold' => $this->shipping->freeShippingThreshold(),
            'qualifies_for_free_shipping' => $this->shipping->qualifiesForFreeShipping($subtotal),
            'lines' => $this->cart->lines()->map(fn ($l) => [
                'id' => $l->product->id,
                'slug' => $l->product->slug,
                'name' => $l->product->name_ar,
                'price' => (float) $l->product->price,
                'qty' => $l->qty,
                'stock' => $l->product->stock,
                'line_total' => $l->line_total,
                'image' => $l->product->primary_image_url,
                'url' => route('products.show', $l->product),
            ])->all(),
        ];
    }
}
