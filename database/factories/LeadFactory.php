<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'need_type' => 'Tư vấn lắp đặt',
            'area' => fake()->numberBetween(50, 200) . ' m2',
            'status' => LeadStatus::New,
        ];
    }
}
