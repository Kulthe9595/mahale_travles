<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Customer = 'customer';

    public function canUseApp(): bool
    {
        return $this !== self::Customer;
    }
}
