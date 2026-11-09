<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case Bank = 'bank';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Upi => 'UPI',
            self::Bank => 'Bank transfer',
        };
    }
}
