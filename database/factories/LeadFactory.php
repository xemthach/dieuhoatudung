<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'need_type' => 'Tư vấn lắp đặt',
            'area' => $this->faker->numberBetween(50, 200) . ' m2',
            'status' => LeadStatus::New,
        ];
    }
}
