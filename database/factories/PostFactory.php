<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\PostCategory;
use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence();
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->paragraph(),
            'content' => $this->faker->paragraphs(5, true),
            'post_category_id' => PostCategory::factory(),
            'author_id' => Author::factory(),
            'status' => PostStatus::Published,
            'published_at' => now(),
        ];
    }
}
