<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'علامة تجريبية',
            'name_en' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(2),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
