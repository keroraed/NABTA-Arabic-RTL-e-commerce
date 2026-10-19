<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function home(): View
    {
        // "اختيارات نبته" shows the catalogue in the admin's display order, which
        // is what the prototype's carousel did. is_featured is deliberately NOT a
        // filter here: only five of the eight products carry it, and a carousel
        // that drops three cards reads as a broken rail rather than a curated one.
        $featured = Product::active()->with('images', 'category')
            ->orderBy('sort_order')
            ->take(12)
            ->get();

        return view('storefront.home', [
            'featured' => $featured,
            'bestSellers' => $this->bestSellers(),
            'tiles' => $this->shelfTiles(),
            'categoryCount' => Category::active()->count(),
            'cardArt' => fn (Product $p) => $this->art('cards', 'card_default', $p),
            'bestArt' => fn (Product $p) => $this->art('best', 'best_default', $p),
        ]);
    }

    /**
     * The five home-page shelf tiles. Each one is art-directed per photo
     * (config/nabta.php) and shows the artwork of one specific product, so the
     * config drives the order and the database supplies the images.
     */
    private function shelfTiles(): Collection
    {
        $tiles = collect(config('nabta.art.tiles'));

        $products = Product::query()
            ->with('images')
            ->whereIn('legacy_id', $tiles->pluck('legacy_id'))
            ->get()
            ->keyBy('legacy_id');

        return $tiles->map(function (array $tile) use ($products) {
            $product = $products->get($tile['legacy_id']);
            $images = $product?->images ?? collect();

            return $tile + [
                'product' => $product,
                'normal_src' => $images->first()?->url,
                // The "opened" artwork is the second image; a product that has only
                // one photo simply gets the plain lift instead of the breakout.
                'opened_src' => $images->get(1)?->url,
            ];
        });
    }

    private function bestSellers(): Collection
    {
        $order = array_keys(config('nabta.art.best'));

        return Product::active()->with('images', 'category')
            ->whereIn('legacy_id', $order)
            ->get()
            ->sortBy(fn (Product $p) => array_search($p->legacy_id, $order, true))
            ->values();
    }

    private function art(string $map, string $default, Product $product): string
    {
        return config("nabta.art.{$map}.{$product->legacy_id}") ?? config("nabta.art.{$default}");
    }

    public function categories(): View
    {
        $categories = Category::active()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get();

        // Each card is fronted by one product's photo, named in the art config.
        $heroes = Product::query()
            ->with('images')
            ->whereIn('legacy_id', collect(config('nabta.art.category_cards'))->pluck('hero')->filter())
            ->get()
            ->keyBy('legacy_id');

        return view('storefront.categories', [
            'categories' => $categories,
            'cardArt' => function (Category $category) use ($heroes) {
                $art = config("nabta.art.category_cards.{$category->slug}")
                    ?? config('nabta.art.category_card_default');

                $images = $heroes->get($art['hero'])?->images ?? collect();

                return $art + [
                    'normal_src' => $images->first()?->url,
                    // Only five of the eight categories have "opened" artwork; the
                    // rest fall back to the plain lift, as in the prototype.
                    'opened_src' => $art['opened'] ? $images->get(1)?->url : null,
                ];
            },
        ]);
    }

    public function index(Request $request): View
    {
        $categories = Category::active()->orderBy('sort_order')->get();

        $slug = $request->query('cat');
        $active = $categories->firstWhere('slug', $slug);

        $products = Product::active()
            ->with('images', 'category')
            ->when($active, fn ($q) => $q->where('category_id', $active->id))
            ->orderBy('sort_order')
            ->get();

        return view('storefront.shop', [
            'categories' => $categories,
            'products' => $products,
            'activeCategory' => $active,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load('images', 'category');

        // Prefer same-category products, then top up from the rest of the
        // catalogue so the rail is never half-empty on a thin category.
        $related = Product::active()->with('images', 'category')
            ->whereKeyNot($product->id)
            ->where('category_id', $product->category_id)
            ->orderBy('sort_order')
            ->take(4)
            ->get();

        if ($related->count() < 4) {
            $related = $related->concat(
                Product::active()->with('images', 'category')
                    ->whereKeyNot($product->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->orderBy('sort_order')
                    ->take(4 - $related->count())
                    ->get()
            );
        }

        // The PDP accordion. Ingredients and usage are optional per product, so
        // the panels are built here rather than in the view — a product with
        // neither still gets the shipping panel and the accordion still reads
        // correctly instead of collapsing to nothing.
        $panels = array_values(array_filter([
            $product->ingredients_ar ? ['المكوّنات', $product->ingredients_ar] : null,
            $product->usage_ar ? ['طريقة الاستخدام والحفظ', $product->usage_ar] : null,
            ['الشحن والاسترجاع', 'يتم التوصيل خلال ٢-٥ أيام عمل حسب المحافظة. يمكنك رفض الاستلام إذا وصل المنتج تالفاً. للاستفسار تواصل معنا عبر الهاتف الموضّح في الأسفل.'],
        ]));

        return view('storefront.product', [
            'product' => $product,
            'related' => $related,
            'panels' => $panels,
        ]);
    }
}
