<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_indian_formats_normalize_to_whatsapp_number(): void
    {
        foreach (['98765 43210', '+91 98765-43210', '09876543210', '919876543210', '+91-9876543210', '(+91) 98765 43210'] as $raw) {
            $this->assertSame('919876543210', Phone::toWhatsApp($raw), $raw);
        }
    }

    public function test_invalid_numbers_return_null(): void
    {
        foreach (['', null, '12345', '0241-2345678', '1234567890', '9198765'] as $raw) {
            $this->assertNull(Phone::toWhatsApp($raw), var_export($raw, true));
        }
    }
}
