<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function validForm(Governorate $governorate, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'أحمد محمد علي',
            'customer_phone' => '01012345678',
            'governorate_id' => $governorate->id,
            'city' => 'مدينة نصر',
            'address_line' => 'شارع عباس العقاد، عقار 15، الدور الثالث',
        ], $overrides);
    }

    public function test_it_recomputes_every_amount_from_the_database(): void
    {
        $governorate = Governorate::factory()->create(['shipping_fee' => 60]);
        $product = Product::factory()->create(['price' => 180, 'stock' => 10]);

        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->validForm($governorate))
            ->assertRedirect();

        $order = Order::sole();

        $this->assertSame('360.00', $order->subtotal);
        $this->assertSame('60.00', $order->shipping_fee);
        $this->assertSame('420.00', $order->total);
    }

    public function test_it_ignores_prices_and_totals_sent_by_the_browser(): void
    {
        $governorate = Governorate::factory()->create(['shipping_fee' => 60]);
        $product = Product::factory()->create(['price' => 180, 'stock' => 10]);

        // A customer editing the payload in devtools: every one of these is junk.
        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->validForm($governorate, [
                'subtotal' => 1,
                'shipping_fee' => 0,
                'total' => 1,
                'unit_price' => 1,
                'items' => [['productId' => $product->id, 'unitPrice' => 1, 'qty' => 2]],
            ]))
            ->assertRedirect();

        $order = Order::sole();

        $this->assertSame('420.00', $order->total);
        $this->assertSame('180.00', $order->items->first()->unit_price);
    }

    public function test_it_snapshots_the_name_and_price_so_later_edits_do_not_rewrite_history(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['name_ar' => 'عسل أسود', 'price' => 95, 'stock' => 5]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validForm($governorate));

        $product->update(['name_ar' => 'اسم جديد', 'price' => 250]);

        $item = Order::sole()->items->first();

        $this->assertSame('عسل أسود', $item->name_ar);
        $this->assertSame('95.00', $item->unit_price);
    }

    public function test_it_decrements_stock_inside_the_order(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $this->withSession(['cart' => [$product->id => 3]])
            ->post(route('checkout.store'), $this->validForm($governorate));

        $this->assertSame(7.0, $product->fresh()->stock);
    }

    public function test_it_rejects_an_order_for_more_than_is_in_stock_and_leaves_nothing_behind(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);

        // Stock dropped after the item went into the cart — someone else bought it.
        $product->update(['stock' => 1]);

        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->validForm($governorate))
            ->assertRedirect()
            ->assertSessionHas('cart_error');

        // No half-written order, and the stock is untouched.
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(1.0, $product->fresh()->stock);
    }

    public function test_free_shipping_applies_at_the_configured_threshold(): void
    {
        Setting::put('free_shipping_threshold', 1000);
        $governorate = Governorate::factory()->create(['shipping_fee' => 90]);
        $product = Product::factory()->create(['price' => 500, 'stock' => 10]);

        $this->withSession(['cart' => [$product->id => 2]])
            ->post(route('checkout.store'), $this->validForm($governorate));

        $this->assertSame('0.00', Order::sole()->shipping_fee);
    }

    public function test_it_normalises_an_egyptian_phone_number(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validForm($governorate, [
                'customer_phone' => '+20 101 234 5678',
            ]))
            ->assertRedirect();

        $this->assertSame('01012345678', Order::sole()->customer_phone);
    }

    public function test_it_rejects_a_phone_number_that_is_not_egyptian(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validForm($governorate, [
                'customer_phone' => '0991234567',
            ]))
            ->assertSessionHasErrors('customer_phone');

        $this->assertSame(0, Order::count());
    }

    public function test_order_numbers_do_not_collide(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create(['stock' => 50]);

        foreach (range(1, 3) as $ignored) {
            $this->withSession(['cart' => [$product->id => 1]])
                ->post(route('checkout.store'), $this->validForm($governorate));
        }

        $this->assertSame(3, Order::count());
        $this->assertSame(3, Order::distinct()->count('order_number'));
    }

    public function test_the_confirmation_page_is_only_served_to_the_session_that_placed_the_order(): void
    {
        $governorate = Governorate::factory()->create();
        $product = Product::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validForm($governorate));

        $order = Order::sole();

        // A fresh session — someone who guessed or was sent the URL — gets nothing.
        $this->flushSession();
        $this->get(route('orders.confirmation', $order))->assertRedirect(route('shop'));
    }
}
