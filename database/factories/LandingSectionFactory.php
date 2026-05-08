<?php

namespace Database\Factories;

use App\Enums\LandingSectionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandingSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_key' => 'home',
            'section_type' => LandingSectionType::Hero,
            'title' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
