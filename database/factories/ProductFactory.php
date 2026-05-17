<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Điều hòa tủ đứng ' . fake()->unique()->words(2, true) . ' ' . fake()->randomElement(['24000BTU', '36000BTU', '48000BTU']);
        $regularPrice = fake()->numberBetween(15000000, 50000000);
        $salePrice = $regularPrice * 0.9;
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => fake()->unique()->bothify('AC-????-####'),
            'model_code' => fake()->bothify('MODEL-##??'),
            'brand_id' => Brand::factory(),
            'product_category_id' => ProductCategory::factory(),
            'btu' => fake()->randomElement([18000, 24000, 36000, 48000, 100000]),
            'inverter' => fake()->boolean(),
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice,
            'price_includes_vat' => false,
            'short_description' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
