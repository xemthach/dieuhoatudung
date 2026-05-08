<?php

namespace Database\Factories;

use App\Enums\ProductCategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => ProductCategoryType::Main,
            'is_active' => true,
        ];
    }
}
