<?php

namespace App\Services\Pos;

use App\Models\Product;

/**
 * All POS money maths, in one place, with no database or network access so it
 * can be unit-tested directly.
 *
 * Everything is computed in integer piastres. Egyptian prices are small enough
 * that float drift would take a while to show up, which is exactly what makes it
 * dangerous: it would surface as a shift that is 3 piastres short, once a week,
 * with no way to trace it.
 */
class PosPricing
{
    public function __construct(private readonly int $roundingStepPiastres = 1) {}

    public static function fromConfig(): self
    {
        return new self(max(1, (int) config('pos.rounding.step_piastres', 1)));
    }

    public function toPiastres(float $pounds): int
    {
        return (int) round($pounds * 100);
    }

    public function toPounds(int $piastres): float
    {
        return round($piastres / 100, 2);
    }

    /**
     * Price of a weighted line: price per kg x weight.
     *
     * Rounded to the configured step because the scale prints its own price on
     * the sticker. If the POS rounds differently the receipt disagrees with the
     * label in the customer's hand, at the counter, in front of a queue.
     */
    public function weightedLinePiastres(float $pricePerKg, int $weightGrams): int
    {
        $exact = $this->toPiastres($pricePerKg) * $weightGrams / 1000;

        return $this->roundToStep((int) round($exact));
    }

    public function unitLinePiastres(float $unitPrice, float $quantity): int
    {
        return $this->roundToStep((int) round($this->toPiastres($unitPrice) * $quantity));
    }

    /** Rounds to the nearest configured step (1 = nearest piastre). */
    public function roundToStep(int $piastres): int
    {
        if ($this->roundingStepPiastres <= 1) {
            return $piastres;
        }

        return (int) (round($piastres / $this->roundingStepPiastres) * $this->roundingStepPiastres);
    }

    /**
     * The unit price to snapshot on the sale item. A weighted product is priced
     * per kg; everything else uses the shelf price.
     */
    public function unitPriceFor(Product $product): float
    {
        return $product->is_weighted
            ? (float) ($product->price_per_kg ?? 0)
            : (float) $product->price;
    }

    /**
     * Totals for a whole cart, in piastres.
     *
     * Lines are passed GROSS, with their discount alongside. Passing an
     * already-discounted line total here would subtract the discount twice —
     * which is exactly the bug this signature exists to make impossible.
     *
     * @param  array<int, array{gross_piastres: int, discount_piastres?: int}>  $lines
     * @return array{subtotal: int, line_discounts: int, sale_discount: int, total: int}
     */
    public function totals(array $lines, int $saleDiscountPiastres = 0): array
    {
        $subtotal = 0;
        $lineDiscounts = 0;

        foreach ($lines as $line) {
            $subtotal += (int) $line['gross_piastres'];
            $lineDiscounts += (int) ($line['discount_piastres'] ?? 0);
        }

        // A whole-sale discount can never take the total below zero — the
        // cashier mistyping 5000 instead of 50 must not produce a negative sale
        // that then has to be unpicked from the ledger.
        $afterLines = max(0, $subtotal - $lineDiscounts);
        $saleDiscount = min($saleDiscountPiastres, $afterLines);

        return [
            'subtotal' => $subtotal,
            'line_discounts' => $lineDiscounts,
            'sale_discount' => $saleDiscount,
            'total' => $afterLines - $saleDiscount,
        ];
    }

    public function changeDue(int $totalPiastres, int $cashReceivedPiastres): int
    {
        return max(0, $cashReceivedPiastres - $totalPiastres);
    }
}
