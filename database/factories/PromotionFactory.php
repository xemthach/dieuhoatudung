<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Khuyến mãi ' . fake()->unique()->monthName();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(),
            'discount_type' => DiscountType::Percent,
            'discount_value' => fake()->numberBetween(5, 20),
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(15),
            'is_active' => true,
        ];
    }
}
