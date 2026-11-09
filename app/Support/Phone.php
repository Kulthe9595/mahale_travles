<?php

namespace App\Support;

final class Phone
{
    /** Indian mobile number as WhatsApp wants it (919876543210), or null if it isn't one. */
    public static function toWhatsApp(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        $digits = ltrim($digits, '0');

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? '91'.$digits : null;
    }
}
