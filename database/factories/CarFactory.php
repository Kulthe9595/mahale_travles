<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registration_no' => 'MH'.fake()->unique()->numerify('17AB####'),
            'model' => fake()->randomElement(['Toyota Innova Crysta', 'Maruti Dzire', 'Maruti Ertiga']),
            'type' => 'SUV',
            'seats' => 7,
            'rate_per_km' => '12.00',
            'rate_per_day' => '2500.00',
            'is_active' => true,
        ];
    }
}
