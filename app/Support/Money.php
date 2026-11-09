<?php

namespace App\Support;

/**
 * Money is handled as integer paise inside the app; this class converts at the edges.
 */
final class Money
{
    public static function toPaise(string|int|float|null $rupees): int
    {
        if ($rupees === null || $rupees === '') {
            return 0;
        }

        return (int) round(((float) $rupees) * 100);
    }

    public static function toDecimal(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $abs = abs($paise);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** ₹1,25,000.00 — Indian digit grouping. */
    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $abs = abs($paise);
        $rupees = (string) intdiv($abs, 100);
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $sign.'₹'.self::groupIndian($rupees).'.'.$fraction;
    }

    public static function formatPlain(int $paise): string
    {
        return ltrim(str_replace('₹', '', self::format($paise)));
    }

    public static function inWords(int $paise): string
    {
        $abs = abs($paise);
        $rupees = intdiv($abs, 100);
        $fraction = $abs % 100;

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::numberToWords($rupees));
        if ($fraction > 0) {
            $words .= ' and '.self::numberToWords($fraction).' Paise';
        }

        return $words.' Only';
    }

    private static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return $rest.','.$last3;
    }

    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private static function numberToWords(int $n): string
    {
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$unit, $name]) {
            if ($n >= $unit) {
                $count = intdiv($n, $unit);
                $parts[] = self::numberToWords($count).' '.$name;
                $n %= $unit;
            }
        }
        if ($n > 0) {
            $parts[] = $n < 20
                ? self::ONES[$n]
                : trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
        }

        return implode(' ', $parts);
    }
}
