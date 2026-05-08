<?php

namespace Database\Factories;

use App\Models\Product;
use App\Enums\CaseStudyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaseStudyFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Dự án ' . fake()->unique()->company();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'client_name' => fake()->company(),
            'location' => fake()->city(),
            'area' => fake()->numberBetween(50, 500) . ' m2',
            'product_id' => Product::factory(),
            'problem' => fake()->paragraph(),
            'solution' => fake()->paragraphs(2, true),
            'result' => fake()->paragraph(),
            'status' => CaseStudyStatus::Published,
            'published_at' => now(),
        ];
    }
}
