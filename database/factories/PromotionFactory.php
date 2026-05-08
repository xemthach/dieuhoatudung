<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Khuyến mãi ' . $this->faker->unique()->monthName();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->sentence(),
            'discount_type' => DiscountType::Percent,
            'discount_value' => $this->faker->numberBetween(5, 20),
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(15),
            'is_active' => true,
        ];
    }
}
