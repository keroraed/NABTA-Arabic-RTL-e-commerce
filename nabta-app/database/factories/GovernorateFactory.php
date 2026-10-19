<?php

namespace Database\Factories;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;

class GovernorateFactory extends Factory
{
    protected $model = Governorate::class;

    public function definition(): array
    {
        return [
            'name_ar' => 'محافظة '.$this->faker->unique()->numberBetween(1, 9999),
            'shipping_fee' => 50,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
