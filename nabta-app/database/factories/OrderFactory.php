<?php

namespace Database\Factories;

use App\Models\Governorate;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare order row, for tests that need orders in a given state. Real orders
 * come only from OrderPlacer — use the checkout in tests about placing them.
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'NB-T'.$this->faker->unique()->numerify('######'),
            'status' => Order::STATUS_PENDING,
            'payment_method' => Order::PAYMENT_COD,
            'payment_status' => Order::PAYMENT_UNPAID,
            'channel' => 'web',
            'customer_name' => 'عميل تجريبي',
            'customer_phone' => '01012345678',
            'governorate_id' => Governorate::factory(),
            'governorate_name_snapshot' => 'القاهرة',
            'city' => 'مدينة نصر',
            'address_line' => 'شارع تجريبي، عقار ١',
            'subtotal' => 100,
            'shipping_fee' => 50,
            'total' => 150,
            'currency' => 'EGP',
            'placed_at' => now(),
        ];
    }
}
