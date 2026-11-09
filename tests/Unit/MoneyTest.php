<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_to_paise(): void
    {
        $this->assertSame(845050, Money::toPaise('8450.5'));
        $this->assertSame(845050, Money::toPaise(8450.5));
        $this->assertSame(1200, Money::toPaise(12));
        $this->assertSame(10, Money::toPaise('0.1'));
        $this->assertSame(0, Money::toPaise(null));
        $this->assertSame(0, Money::toPaise(''));
    }

    public function test_to_decimal(): void
    {
        $this->assertSame('8450.00', Money::toDecimal(845000));
        $this->assertSame('0.50', Money::toDecimal(50));
        $this->assertSame('-100.00', Money::toDecimal(-10000));
    }

    public function test_format_uses_indian_grouping(): void
    {
        $this->assertSame('₹1,25,000.00', Money::format(12500000));
        $this->assertSame('₹8,450.00', Money::format(845000));
        $this->assertSame('₹999.00', Money::format(99900));
        $this->assertSame('₹1,00,00,000.00', Money::format(1000000000));
        $this->assertSame('-₹100.00', Money::format(-10000));
    }

    public function test_in_words(): void
    {
        $this->assertSame('Rupees Seven Thousand Seven Hundred Forty Three Only', Money::inWords(774300));
        $this->assertSame('Rupees One Lakh and Fifty Paise Only', Money::inWords(10000050));
        $this->assertSame('Rupees Twelve Crore Thirty Four Lakh Five Only', Money::inWords(12340000500));
        $this->assertSame('Rupees Zero Only', Money::inWords(0));
    }
}
