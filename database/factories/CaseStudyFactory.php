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
        $title = 'Dự án ' . $this->faker->unique()->company();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'client_name' => $this->faker->company(),
            'location' => $this->faker->city(),
            'area' => $this->faker->numberBetween(50, 500) . ' m2',
            'product_id' => Product::factory(),
            'problem' => $this->faker->paragraph(),
            'solution' => $this->faker->paragraphs(2, true),
            'result' => $this->faker->paragraph(),
            'status' => CaseStudyStatus::Published,
            'published_at' => now(),
        ];
    }
}
