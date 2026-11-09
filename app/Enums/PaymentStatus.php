<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartlyPaid = 'partly_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartlyPaid => 'Partly paid',
            self::Paid => 'Paid',
        };
    }
}
