<?php

namespace App\Services;

use App\Models\Governorate;
use App\Models\Setting;

/**
 * Single source of truth for shipping. The browser never computes a fee.
 */
class ShippingCalculator
{
    public function freeShippingThreshold(): float
    {
        return (float) Setting::get('free_shipping_threshold', 1000);
    }

    public function qualifiesForFreeShipping(float $subtotal): bool
    {
        return $subtotal >= $this->freeShippingThreshold();
    }

    public function feeFor(?Governorate $governorate, float $subtotal): float
    {
        if ($governorate === null) {
            return 0.0;
        }

        if ($this->qualifiesForFreeShipping($subtotal)) {
            return 0.0;
        }

        return round((float) $governorate->shipping_fee, 2);
    }
}
