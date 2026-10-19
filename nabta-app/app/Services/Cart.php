<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-backed cart.
 *
 * The static prototype kept the cart in localStorage and computed totals in the
 * browser, which meant a customer could edit the total in devtools (HANDOFF.md 2.3).
 * Holding it server-side removes that class of bug entirely: the browser only ever
 * sends product ids and quantities, and every price is read from the database.
 */
class Cart
{
    private const KEY = 'cart';

    public function __construct(private readonly Session $session) {}

    /** @return array<int,int> productId => qty */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(Product $product, int $qty = 1): void
    {
        $lines = $this->raw();
        $current = $lines[$product->id] ?? 0;

        $this->write($lines, $product, $current + $qty);
    }

    public function set(Product $product, int $qty): void
    {
        $this->write($this->raw(), $product, $qty);
    }

    public function remove(int $productId): void
    {
        $lines = $this->raw();
        unset($lines[$productId]);
        $this->session->put(self::KEY, $lines);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function count(): int
    {
        return (int) $this->lines()->sum('qty');
    }

    /**
     * Resolves the stored ids against the live catalogue.
     *
     * Lines whose product has been deleted or deactivated are dropped rather than
     * crashing the page, and quantities are re-clamped to current stock on every
     * read — so a cart that sat open for a week cannot check out at a stale price
     * or beyond what is actually on the shelf.
     *
     * @return Collection<int,object{product:Product,qty:int,line_total:float}>
     */
    public function lines(): Collection
    {
        $stored = $this->raw();

        if ($stored === []) {
            return collect();
        }

        $products = Product::query()
            ->active()
            ->with('images')
            ->whereIn('id', array_keys($stored))
            ->get()
            ->keyBy('id');

        return collect($stored)
            ->map(function (int $qty, int $id) use ($products) {
                $product = $products->get($id);

                if (! $product || $product->stock <= 0) {
                    return null;
                }

                // Stock is decimal now (weighted goods sell in kg), but the
                // storefront only sells packaged units, so the clamp floors back
                // to a whole number rather than offering 2.5 jars.
                $qty = max(1, min($qty, (int) floor($product->stock)));

                return (object) [
                    'product' => $product,
                    'qty' => $qty,
                    'line_total' => round((float) $product->price * $qty, 2),
                ];
            })
            ->filter()
            ->values();
    }

    public function subtotal(): float
    {
        return round((float) $this->lines()->sum('line_total'), 2);
    }

    private function write(array $lines, Product $product, int $qty): void
    {
        $qty = min(max($qty, 0), max((int) floor($product->stock), 0));

        if ($qty <= 0) {
            unset($lines[$product->id]);
        } else {
            $lines[$product->id] = $qty;
        }

        $this->session->put(self::KEY, $lines);
    }
}
