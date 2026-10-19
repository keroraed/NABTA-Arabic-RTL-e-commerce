<?php

namespace App\Providers;

use App\Models\Product;
use App\Services\Cart;
use App\Services\ShippingCalculator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class StorefrontServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The header cart badge and drawer appear on every storefront page, so the
        // cart is seeded into the initial HTML rather than fetched after paint —
        // no empty-then-populated flicker on the badge.
        View::composer('layouts.storefront', function ($view) {
            $cart = app(Cart::class);
            $shipping = app(ShippingCalculator::class);
            $subtotal = $cart->subtotal();

            $view->with('__cartState', [
                'count' => $cart->count(),
                'subtotal' => $subtotal,
                'free_shipping_threshold' => $shipping->freeShippingThreshold(),
                'qualifies_for_free_shipping' => $shipping->qualifiesForFreeShipping($subtotal),
                'lines' => $cart->lines()->map(fn ($l) => [
                    'id' => $l->product->id,
                    'slug' => $l->product->slug,
                    'name' => $l->product->name_ar,
                    'cat' => $l->product->category?->name_ar,
                    'price' => (float) $l->product->price,
                    'qty' => $l->qty,
                    'stock' => $l->product->stock,
                    'line_total' => $l->line_total,
                    'image' => $l->product->primary_image_url,
                    'url' => route('products.show', $l->product),
                ])->all(),
            ]);
        });

        // The search overlay and quick-view modal are client-side over the whole
        // catalogue, exactly as the prototype was. It is a handful of rows, so it
        // ships with the page instead of costing a round trip on first keystroke.
        View::composer("layouts.storefront", function ($view) {
            $view->with("__catalog", cache()->remember("storefront.catalog", 300, fn () => Product::query()
                ->active()
                ->with("images", "category")
                ->orderBy("sort_order")
                ->get()
                ->map(fn (Product $p) => [
                    "id" => $p->id,
                    "name" => $p->name_ar,
                    "cat" => $p->category?->name_ar,
                    "price" => (float) $p->price,
                    "desc" => $p->short_desc_ar,
                    "img" => $p->primary_image_url,
                    "url" => route("products.show", $p),
                ])->all()));
        });
    }
}
