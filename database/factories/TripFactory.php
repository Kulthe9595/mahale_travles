<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Car;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'car_id' => Car::factory(),
            'driver_id' => null,
            'pickup' => 'Shrirampur',
            'drop' => 'Pune',
            'start_at' => '2026-11-09 08:00:00',
            'end_at' => '2026-11-10 20:00:00',
            'trip_type' => TripType::PerKm,
            'start_km' => 10000,
            'end_km' => 10300,
            'billing_inputs' => [
                'rate_per_km' => '12',
                'driver_allowance_per_day' => '300',
                'night_halt_per_night' => '250',
                'toll' => '450',
                'parking' => '100',
                'gst_enabled' => true,
            ],
            'status' => TripStatus::Completed,
        ];
    }
}
