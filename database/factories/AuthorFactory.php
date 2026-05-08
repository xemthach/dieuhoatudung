<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuthorFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'bio' => fake()->paragraph(),
            'role' => 'Chuyên gia HVAC',
            'is_active' => true,
        ];
    }
}
