<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Governorate;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontChromeTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): Product
    {
        Category::factory()->create();
        Governorate::factory()->create();

        return Product::factory()->create();
    }

    /** @return array<int, string> */
    private function pages(Product $product): array
    {
        return [
            route('home'),
            route('shop'),
            route('categories'),
            route('products.show', $product),
        ];
    }

    /**
     * Alpine's start() only walks trees rooted at an x-data. Without one on the
     * body, every Alpine binding on the page silently renders as an empty
     * element — the cart badge blank, the cart drawer empty, the subtotal
     * missing — with no console error to point at it. This is a cheap guard
     * against reintroducing that.
     */
    public function test_every_page_has_an_alpine_root(): void
    {
        $product = $this->seedCatalogue();

        foreach ($this->pages($product) as $url) {
            $this->get($url)
                ->assertSuccessful()
                ->assertSee('<body x-data>', escape: false);
        }
    }

    public function test_every_page_ships_the_cart_drawer_and_its_bindings(): void
    {
        $product = $this->seedCatalogue();

        foreach ($this->pages($product) as $url) {
            $response = $this->get($url);

            $response->assertSee('data-drawer', escape: false);
            $response->assertSee('x-for="it in $store.cart.lines"', escape: false);
            $response->assertSee('$store.cart.subtotalLabel', escape: false);
        }
    }

    /**
     * The five nav links collapse into the hamburger below 901px, matching the
     * home page. Before this, the inner pages squeezed all five into a
     * phone-width bar.
     */
    public function test_every_page_has_the_mobile_menu_and_hamburger(): void
    {
        $product = $this->seedCatalogue();

        foreach ($this->pages($product) as $url) {
            $response = $this->get($url);

            $response->assertSee('data-menu', escape: false);
            $response->assertSee('nb-mob', escape: false);
            $response->assertSee('NB.openMenu(event)', escape: false);
        }
    }

    /**
     * Blade's startSection() treats a null second argument as "capture until
     * endsection" and leaves an output buffer open for the rest of the request.
     * A product with no image and no short description is legal, and used to hit
     * exactly that through @section('og_image', ...). The leak is silent —
     * the page still renders — so it needs its own guard.
     */
    public function test_a_product_without_an_image_or_description_does_not_leak_an_output_buffer(): void
    {
        Category::factory()->create();

        $bare = Product::factory()->create([
            'short_desc_ar' => null,
            'description_ar' => null,
            'ingredients_ar' => null,
            'usage_ar' => null,
        ]);

        $before = ob_get_level();

        $this->get(route('products.show', $bare))->assertSuccessful();

        $this->assertSame($before, ob_get_level(), 'The product page left an output buffer open.');
    }

    public function test_the_cart_state_is_rendered_into_the_page_not_fetched_after_paint(): void
    {
        $product = $this->seedCatalogue();

        $this->withSession(['cart' => [$product->id => 2]])
            ->get(route('shop'))
            ->assertSuccessful()
            ->assertSee('"count":2', escape: false)
            ->assertSee($product->name_ar);
    }
}
