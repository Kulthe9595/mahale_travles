<?php

namespace App\Support;

final class Gstin
{
    public const RULE = 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public const MESSAGE = 'Enter a valid 15-character GSTIN.';

    public static function normalize(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
