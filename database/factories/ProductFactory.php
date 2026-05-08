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
        $name = 'Điều hòa tủ đứng ' . $this->faker->unique()->words(2, true) . ' ' . $this->faker->randomElement(['24000BTU', '36000BTU', '48000BTU']);
        $regularPrice = $this->faker->numberBetween(15000000, 50000000);
        $salePrice = $regularPrice * 0.9;
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => $this->faker->unique()->bothify('AC-????-####'),
            'model_code' => $this->faker->bothify('MODEL-##??'),
            'brand_id' => Brand::factory(),
            'product_category_id' => ProductCategory::factory(),
            'btu' => $this->faker->randomElement([18000, 24000, 36000, 48000, 100000]),
            'inverter' => $this->faker->boolean(),
            'regular_price' => $regularPrice,
            'sale_price' => $salePrice,
            'short_description' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }
}
