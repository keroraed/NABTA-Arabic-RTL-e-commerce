<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_a_product_and_returns_the_new_state(): void
    {
        $product = Product::factory()->create(['price' => 120, 'stock' => 10]);

        $this->postJson(route('cart.add', $product), ['qty' => 2])
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('subtotal', 240);
    }

    public function test_it_accumulates_repeat_adds_of_the_same_product(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->postJson(route('cart.add', $product), ['qty' => 1]);

        $this->postJson(route('cart.add', $product), ['qty' => 2])
            ->assertJsonPath('count', 3);
    }

    public function test_it_never_lets_the_cart_exceed_available_stock(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->postJson(route('cart.add', $product), ['qty' => 99])
            ->assertJsonPath('count', 3);
    }

    public function test_setting_a_quantity_to_zero_removes_the_line(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->postJson(route('cart.add', $product), ['qty' => 2]);

        $this->patchJson(route('cart.update', $product), ['qty' => 0])
            ->assertJsonPath('count', 0)
            ->assertJsonPath('lines', []);
    }

    public function test_it_removes_a_line(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $this->postJson(route('cart.add', $product), ['qty' => 2]);

        $this->deleteJson(route('cart.remove', $product))
            ->assertJsonPath('count', 0);
    }

    /**
     * A cart that sat open for a week must not check out at last week's price or
     * against stock that has since gone. Both are re-resolved on every read.
     */
    public function test_it_reprices_and_reclamps_stored_lines_on_read(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $this->session(['cart' => [$product->id => 8]]);
        $product->update(['price' => 150, 'stock' => 3]);

        $lines = $this->app->make(Cart::class)->lines();

        $this->assertCount(1, $lines);
        $this->assertSame(3, $lines->first()->qty);
        $this->assertSame(450.0, $lines->first()->line_total);
    }

    public function test_it_drops_lines_whose_product_went_away(): void
    {
        $live = Product::factory()->create(['stock' => 5]);
        $deactivated = Product::factory()->inactive()->create(['stock' => 5]);
        $soldOut = Product::factory()->outOfStock()->create();

        $this->session(['cart' => [
            $live->id => 1,
            $deactivated->id => 1,
            $soldOut->id => 1,
        ]]);

        $cart = $this->app->make(Cart::class);

        $this->assertCount(1, $cart->lines());
        $this->assertSame($live->id, $cart->lines()->first()->product->id);
    }

    public function test_adding_an_inactive_product_is_not_found(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->postJson(route('cart.add', $product), ['qty' => 1])->assertNotFound();
    }
}
