<?php

namespace App\Enums;

enum TripType: string
{
    case PerKm = 'per_km';
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::PerKm => 'Per km',
            self::Package => 'Package / per day',
        };
    }
}
