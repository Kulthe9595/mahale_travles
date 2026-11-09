<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->safeEmail(),
            'address' => 'Shrirampur',
            'state' => 'Maharashtra',
            'gstin' => null,
        ];
    }
}
