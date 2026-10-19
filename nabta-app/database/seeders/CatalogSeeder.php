<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Enums\StockMovementType;
use App\Models\ProductImage;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Imports the static site's catalogue (../data/products.json) into the database.
 * That file is the contract HANDOFF.md 3 describes; this seeder is the one-time
 * bridge from it. Idempotent — safe to re-run.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('../data/products.json');

        if (! is_file($path)) {
            throw new RuntimeException("Catalogue seed not found at {$path}");
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data['categories'] as $i => $c) {
            Category::updateOrCreate(
                ['slug' => $c['slug']],
                ['name_ar' => $c['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $categories = Category::pluck('id', 'slug');

        foreach ($data['products'] as $i => $p) {
            $product = Product::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'legacy_id' => $p['id'],
                    'sku' => $p['sku'],
                    'category_id' => $categories[$p['categorySlug']],
                    'name_ar' => $p['name'],
                    'short_desc_ar' => $p['shortDesc'] ?? null,
                    'description_ar' => $p['description'] ?? null,
                    'ingredients_ar' => $p['ingredients'] ?? null,
                    'usage_ar' => $p['usage'] ?? null,
                    'weight_label' => $p['weight'] ?? null,
                    'price' => $p['price'],
                    'compare_at_price' => $p['compareAtPrice'] ?? null,
                    'currency' => $p['currency'] ?? 'EGP',
                    'is_active' => true,
                    'is_featured' => (bool) ($p['featured'] ?? false),
                    'sort_order' => $i,
                ],
            );

            $product->images()->delete();

            $this->seedOpeningStock($product, (float) ($p['stock'] ?? 0));

            foreach (($p['images'] ?? []) as $j => $src) {
                ProductImage::create([
                    'product_id' => $product->id,
                    // products.json holds paths relative to the site root
                    // ("assets/products/x.webp"); the assets disk is rooted at
                    // public/assets, so the prefix comes off.
                    'path' => preg_replace('#^assets/#', '', $src),
                    'alt_ar' => $p['name'],
                    'sort_order' => $j,
                ]);
            }
        }
    }

    /**
     * Opening balance as a single adjustment movement, only when the product has
     * none yet. Re-running the seeders must not keep topping the shop up.
     *
     * These figures are invented placeholders (HANDOFF.md 2.4) — replace them
     * with a real physical count before go-live.
     */
    private function seedOpeningStock(Product $product, float $quantity): void
    {
        if ($quantity <= 0 || $product->movements()->exists()) {
            return;
        }

        $warehouse = Warehouse::onlineFulfilment();

        if (! $warehouse) {
            return;
        }

        app(StockLedger::class)->record(
            product: $product,
            warehouse: $warehouse,
            type: StockMovementType::Adjustment,
            quantity: $quantity,
            notes: 'رصيد افتتاحي',
        );
    }
}
