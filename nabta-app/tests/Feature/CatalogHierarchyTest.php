<?php

namespace Tests\Feature;

use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Brand;
use App\Models\CashAccount;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogHierarchyTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------------
     | Model rules
     --------------------------------------------------------------------- */

    public function test_a_products_primary_category_is_always_in_its_categories(): void
    {
        $product = Product::factory()->create();

        $this->assertEquals([$product->category_id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_changing_the_primary_category_moves_the_product_but_keeps_the_extras(): void
    {
        [$old, $extra, $new] = Category::factory()->count(3)->create();
        $product = Product::factory()->create(['category_id' => $old->id]);
        $product->categories()->attach($extra);

        $product->update(['category_id' => $new->id]);

        $this->assertEqualsCanonicalizing(
            [$extra->id, $new->id],
            $product->categories()->pluck('categories.id')->all(),
        );
        $this->assertFalse(Product::inCategory($old)->whereKey($product->id)->exists());
    }

    public function test_a_product_in_several_categories_is_found_in_each(): void
    {
        [$herbs, $teas, $gifts] = Category::factory()->count(3)->create();
        $product = Product::factory()->create(['category_id' => $herbs->id]);
        $product->categories()->attach([$teas->id]);

        $this->assertTrue(Product::inCategory($herbs)->whereKey($product->id)->exists());
        $this->assertTrue(Product::inCategory($teas)->whereKey($product->id)->exists());
        $this->assertFalse(Product::inCategory($gifts)->whereKey($product->id)->exists());
    }

    public function test_a_parent_category_includes_its_subcategories_products(): void
    {
        $herbs = Category::factory()->create();
        $teaHerbs = Category::factory()->create(['parent_id' => $herbs->id]);

        $product = Product::factory()->create(['category_id' => $teaHerbs->id]);

        $this->assertTrue(Product::inCategory($herbs)->whereKey($product->id)->exists());
        $this->assertTrue(Product::inCategory($teaHerbs)->whereKey($product->id)->exists());
        $this->assertFalse(Product::inCategory(Category::factory()->create())->whereKey($product->id)->exists());
    }

    public function test_roots_and_subcategories_scopes(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->assertEquals([$root->id], Category::roots()->pluck('id')->all());
        $this->assertEquals([$child->id], Category::subcategories()->pluck('id')->all());
        $this->assertTrue($root->children->contains($child));
        $this->assertTrue($child->parent->is($root));
    }

    public function test_a_category_cannot_become_its_own_ancestor(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->expectException(InvalidArgumentException::class);

        $root->update(['parent_id' => $child->id]);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $category->update(['parent_id' => $category->id]);
    }

    public function test_categories_are_two_levels_deep(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->expectException(InvalidArgumentException::class);

        Category::factory()->create(['parent_id' => $child->id]);
    }

    public function test_the_categories_page_counts_subcategory_products_on_the_parent_card(): void
    {
        $root = Category::factory()->create(['slug' => 'herbs']);
        $child = Category::factory()->create(['parent_id' => $root->id]);
        Product::factory()->create(['category_id' => $child->id]);

        $categories = $this->get(route('categories'))->assertOk()->viewData('categories');

        $this->assertSame(1, $categories->firstWhere('id', $root->id)->products_count);
    }

    public function test_deleting_a_parent_promotes_its_subcategories(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $root->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_a_product_belongs_to_a_brand(): void
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $this->assertTrue($product->brand->is($brand));
        $this->assertTrue($brand->products->contains($product));
        $this->assertTrue(Product::ofBrand($brand)->whereKey($product->id)->exists());
    }

    /* ---------------------------------------------------------------------
     | Storefront
     --------------------------------------------------------------------- */

    public function test_the_shop_filters_by_subcategory_through_the_pivot(): void
    {
        $herbs = Category::factory()->create(['slug' => 'herbs', 'name_ar' => 'أعشاب']);
        $teas = Category::factory()->create(['slug' => 'teas', 'name_ar' => 'شاي أعشاب', 'parent_id' => $herbs->id]);
        $other = Category::factory()->create(['slug' => 'nuts']);

        // Primary category elsewhere; filed under the subcategory only via the pivot.
        $tea = Product::factory()->create(['name_ar' => 'شاي البابونج', 'category_id' => $other->id]);
        $tea->categories()->attach($teas);

        $nut = Product::factory()->create(['name_ar' => 'لوز محمص', 'category_id' => $other->id]);

        $this->get(route('shop', ['cat' => 'teas']))
            ->assertOk()
            ->assertSee('شاي البابونج')
            ->assertDontSee('لوز محمص');

        // The parent category includes it too, and shows its subcategory row.
        $this->get(route('shop', ['cat' => 'herbs']))
            ->assertOk()
            ->assertSee('شاي البابونج')
            ->assertDontSee('لوز محمص')
            ->assertSee('شاي أعشاب');
    }

    public function test_the_shop_filters_by_brand_and_combines_with_category(): void
    {
        $category = Category::factory()->create(['slug' => 'oils']);
        $brand = Brand::factory()->create(['slug' => 'hemani', 'name_ar' => 'هيماني']);

        Product::factory()->create(['name_ar' => 'زيت هيماني', 'category_id' => $category->id, 'brand_id' => $brand->id]);
        Product::factory()->create(['name_ar' => 'زيت محلي', 'category_id' => $category->id]);

        $this->get(route('shop', ['brand' => 'hemani']))
            ->assertOk()
            ->assertSee('زيت هيماني')
            ->assertDontSee('زيت محلي');

        $this->get(route('shop', ['cat' => 'oils', 'brand' => 'hemani']))
            ->assertOk()
            ->assertSee('زيت هيماني')
            ->assertDontSee('زيت محلي');
    }

    public function test_the_categories_page_lists_only_top_level_categories(): void
    {
        $root = Category::factory()->create(['name_ar' => 'قسم رئيسي ظاهر']);
        Category::factory()->create(['name_ar' => 'قسم فرعي مخفي', 'parent_id' => $root->id]);

        $this->get(route('categories'))
            ->assertOk()
            ->assertSee('قسم رئيسي ظاهر')
            ->assertDontSee('قسم فرعي مخفي');
    }

    /* ---------------------------------------------------------------------
     | Admin
     --------------------------------------------------------------------- */

    public function test_the_brand_and_category_admin_screens_load(): void
    {
        $brand = Brand::factory()->create();
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $child->id]);

        $this->actingAs(User::factory()->admin()->create());

        foreach ([
            BrandResource::getUrl('index'),
            BrandResource::getUrl('create'),
            BrandResource::getUrl('edit', ['record' => $brand]),
            CategoryResource::getUrl('index'),
            CategoryResource::getUrl('edit', ['record' => $child]),
            ProductResource::getUrl('index'),
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_the_product_form_assigns_a_brand_and_several_categories(): void
    {
        $brand = Brand::factory()->create();
        [$primary, $extra, $another] = Category::factory()->count(3)->create();
        $product = Product::factory()->create(['category_id' => $primary->id]);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            // The primary category is left out of the multi-select on purpose:
            // saving must keep it anyway.
            ->fillForm([
                'brand_id' => $brand->id,
                'categories' => [$extra->id, $another->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame($brand->id, $product->brand_id);
        $this->assertEqualsCanonicalizing(
            [$primary->id, $extra->id, $another->id],
            $product->categories()->pluck('categories.id')->all(),
        );
    }

    public function test_changing_the_primary_category_in_the_form_swaps_it_in_the_list(): void
    {
        [$old, $extra, $new] = Category::factory()->count(3)->create();
        $product = Product::factory()->create(['category_id' => $old->id]);
        $product->categories()->attach($extra);

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => $new->id])
            ->assertSchemaStateSet(fn (array $state) => $this->assertEqualsCanonicalizing(
                [$extra->id, $new->id],
                array_map('intval', $state['categories']),
            ) ?? [])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$extra->id, $new->id],
            $product->categories()->pluck('categories.id')->all(),
        );
    }

    /* ---------------------------------------------------------------------
     | POS
     --------------------------------------------------------------------- */

    public function test_the_pos_catalogue_carries_every_category_and_the_brand(): void
    {
        $brand = Brand::factory()->create(['name_ar' => 'هيماني']);
        [$primary, $extra] = Category::factory()->count(2)->create();
        $product = Product::factory()->create(['category_id' => $primary->id, 'brand_id' => $brand->id]);
        $product->categories()->attach($extra);

        $cashier = User::factory()->create();
        $terminal = PosTerminal::create([
            'name' => 'كاشير 1',
            'code' => 'T1',
            'warehouse_id' => Warehouse::onlineFulfilment()->id,
            'cash_account_id' => CashAccount::create(['name' => 'خزينة', 'type' => 'cash'])->id,
        ]);
        CashShift::create([
            'pos_terminal_id' => $terminal->id,
            'opened_by' => $cashier->id,
            'opened_at' => now(),
            'opening_float' => 0,
            'status' => CashShift::OPEN,
        ]);

        $catalogue = $this->actingAs($cashier)
            ->withSession(['pos_terminal_id' => $terminal->id])
            ->get(route('pos.index'))
            ->assertOk()
            ->viewData('catalogue');

        $row = collect($catalogue)->firstWhere('id', $product->id);

        $this->assertSame($primary->name_ar, $row['category']);
        $this->assertEqualsCanonicalizing([$primary->id, $extra->id], $row['category_ids']);
        $this->assertSame('هيماني', $row['brand']);
    }
}
