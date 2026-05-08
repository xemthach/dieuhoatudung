<?php

namespace Database\Factories;

use App\Enums\PolicyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PolicyPageFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Chính sách ' . fake()->word();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(3, true),
            'type' => PolicyType::Terms,
            'is_active' => true,
        ];
    }
}
