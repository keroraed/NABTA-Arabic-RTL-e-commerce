<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(3),
            'legacy_id' => $this->faker->unique()->lexify('????????'),
            'sku' => $this->faker->unique()->bothify('NBT-???-###'),
            'category_id' => Category::factory(),
            'name_ar' => 'منتج تجريبي',
            'short_desc_ar' => 'وصف مختصر',
            'description_ar' => 'وصف كامل',
            'price' => 100,
            'currency' => 'EGP',
            'stock' => 10,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
