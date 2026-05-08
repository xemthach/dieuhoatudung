<?php

namespace Database\Factories;

use App\Enums\TagStatus;
use App\Enums\TagType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => TagType::Topic,
            'status' => TagStatus::Approved,
        ];
    }
}
