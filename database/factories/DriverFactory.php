<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('98########'),
            'licence_no' => fake()->bothify('MH17##########'),
            'is_active' => true,
        ];
    }
}
