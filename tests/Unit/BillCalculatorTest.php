<?php

namespace Tests\Unit;

use App\Billing\BillCalculator;
use App\Billing\BillInput;
use App\Billing\BillLine;
use App\Billing\BillResult;
use App\Enums\TaxType;
use App\Enums\TripType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BillCalculatorTest extends TestCase
{
    private const TZ = 'Asia/Kolkata';

    private function at(string $dt): CarbonImmutable
    {
        return CarbonImmutable::parse($dt, self::TZ);
    }

    private function calc(array $args): BillResult
    {
        return (new BillCalculator)->calculate(new BillInput(...$args));
    }

    private function outstationArgs(array $overrides = []): array
    {
        return array_merge([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-10 20:00'),
            'startKm' => 10000,
            'endKm' => 10300,
            'ratePerKm' => 1200,
            'minKmPerDay' => 250,
            'driverAllowancePerDay' => 30000,
            'nightHaltPerNight' => 25000,
            'toll' => 45000,
            'parking' => 10000,
            'gstEnabled' => true,
            'gstRate' => 5.0,
            'customerState' => 'Maharashtra',
        ], $overrides);
    }

    /** @return array<string, BillLine> */
    private function lines(BillResult $r): array
    {
        $out = [];
        foreach ($r->lines as $line) {
            $out[$line->description] = $line;
        }

        return $out;
    }

    public function test_per_km_outstation_with_min_km_and_gst_intra_state(): void
    {
        $r = $this->calc($this->outstationArgs());
        $lines = $this->lines($r);

        $this->assertSame(2, $r->days);
        $this->assertSame(1, $r->nights);
        $this->assertSame(300, $r->actualKm);
        $this->assertSame(500, $r->billableKm);

        $this->assertSame(['Kilometre charges', 'Driver allowance', 'Night halt', 'Toll', 'Parking'], array_keys($lines));
        $this->assertEquals(500, $lines['Kilometre charges']->qty);
        $this->assertSame(1200, $lines['Kilometre charges']->rate);
        $this->assertSame(600000, $lines['Kilometre charges']->amount);
        $this->assertSame(60000, $lines['Driver allowance']->amount);
        $this->assertEquals(2, $lines['Driver allowance']->qty);
        $this->assertSame(25000, $lines['Night halt']->amount);
        $this->assertSame(45000, $lines['Toll']->amount);
        $this->assertFalse($lines['Toll']->taxable);
        $this->assertSame(10000, $lines['Parking']->amount);
        $this->assertFalse($lines['Parking']->taxable);
        $this->assertTrue($lines['Kilometre charges']->taxable);

        $this->assertSame(685000, $r->taxableAmount);
        $this->assertSame(55000, $r->nonTaxableAmount);
        $this->assertSame(TaxType::CgstSgst, $r->taxType);
        $this->assertSame(17125, $r->cgst);
        $this->assertSame(17125, $r->sgst);
        $this->assertSame(0, $r->igst);
        $this->assertSame(774300, $r->total);
        $this->assertSame(50, $r->roundOff);
    }

    public function test_actual_km_used_when_above_minimum(): void
    {
        $r = $this->calc($this->outstationArgs(['endKm' => 10620]));

        $this->assertSame(620, $r->billableKm);
        $this->assertSame(744000, $this->lines($r)['Kilometre charges']->amount);
    }

    public function test_package_with_extra_km_and_hours_no_gst(): void
    {
        $r = $this->calc([
            'tripType' => TripType::Package,
            'startAt' => $this->at('2026-11-09 09:00'),
            'endAt' => $this->at('2026-11-09 19:30'),
            'startKm' => 5000,
            'endKm' => 5120,
            'packageAmount' => 250000,
            'includedKm' => 80,
            'includedHours' => 8,
            'extraKmRate' => 1300,
            'extraHourRate' => 15000,
        ]);
        $lines = $this->lines($r);

        $this->assertSame(11, $r->hours);
        $this->assertSame(['Package charges', 'Extra km', 'Extra hours'], array_keys($lines));
        $this->assertSame(250000, $lines['Package charges']->amount);
        $this->assertEquals(40, $lines['Extra km']->qty);
        $this->assertSame(52000, $lines['Extra km']->amount);
        $this->assertEquals(3, $lines['Extra hours']->qty);
        $this->assertSame(45000, $lines['Extra hours']->amount);
        $this->assertSame(TaxType::None, $r->taxType);
        $this->assertSame(347000, $r->total);
        $this->assertSame(0, $r->roundOff);
    }

    public function test_package_per_day_rate(): void
    {
        $r = $this->calc([
            'tripType' => TripType::Package,
            'startAt' => $this->at('2026-11-09 06:00'),
            'endAt' => $this->at('2026-11-11 21:00'),
            'startKm' => 0,
            'endKm' => 700,
            'packageRatePerDay' => 220000,
            'includedKm' => 900,
        ]);
        $line = $this->lines($r)['Package charges'];

        $this->assertEquals(3, $line->qty);
        $this->assertSame(220000, $line->rate);
        $this->assertSame(660000, $line->amount);
        $this->assertArrayNotHasKey('Extra km', $this->lines($r));
    }

    public function test_igst_for_other_state(): void
    {
        $r = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0,
            'endKm' => 100,
            'ratePerKm' => 1000,
            'gstEnabled' => true,
            'customerState' => 'Karnataka',
        ]);

        $this->assertSame(TaxType::Igst, $r->taxType);
        $this->assertSame(5000, $r->igst);
        $this->assertSame(0, $r->cgst);
        $this->assertSame(105000, $r->total);
    }

    public function test_state_match_ignores_case_and_spaces_and_null_means_intra(): void
    {
        $base = [
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0, 'endKm' => 100, 'ratePerKm' => 1000, 'gstEnabled' => true,
        ];

        $this->assertSame(TaxType::CgstSgst, $this->calc($base + ['customerState' => ' maharashtra '])->taxType);
        $this->assertSame(TaxType::CgstSgst, $this->calc($base + ['customerState' => null])->taxType);
    }

    public function test_discount_reduces_taxable(): void
    {
        $r = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0,
            'endKm' => 100,
            'ratePerKm' => 1000,
            'discount' => 10000,
            'gstEnabled' => true,
        ]);
        $lines = $this->lines($r);

        $this->assertSame(-10000, $lines['Discount']->amount);
        $this->assertTrue($lines['Discount']->taxable);
        $this->assertSame(90000, $r->taxableAmount);
        $this->assertSame(2250, $r->cgst);
        $this->assertSame(2250, $r->sgst);
        $this->assertSame(94500, $r->total);
    }

    public function test_odd_tax_paise_split_puts_extra_paisa_in_sgst(): void
    {
        $r = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0,
            'endKm' => 1,
            'ratePerKm' => 1030,
            'gstEnabled' => true,
        ]);

        // 10.30 × 5% = 0.515 → 52 paise → 26 + 26
        $this->assertSame(26, $r->cgst);
        $this->assertSame(26, $r->sgst);

        $r2 = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0,
            'endKm' => 1,
            'ratePerKm' => 1010,
            'gstEnabled' => true,
        ]);
        // 10.10 × 5% = 0.505 → 51 paise → 25 + 26
        $this->assertSame(25, $r2->cgst);
        $this->assertSame(26, $r2->sgst);
    }

    public function test_overnight_trip_counts_two_days_one_night(): void
    {
        $r = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 22:00'),
            'endAt' => $this->at('2026-11-10 06:00'),
            'startKm' => 0,
            'endKm' => 200,
            'ratePerKm' => 1000,
            'driverAllowancePerDay' => 30000,
        ]);

        $this->assertSame(2, $r->days);
        $this->assertSame(1, $r->nights);
        $this->assertSame(8, $r->hours);
        $this->assertSame(60000, $this->lines($r)['Driver allowance']->amount);
    }

    public function test_other_taxable_charge_uses_label(): void
    {
        $r = $this->calc([
            'tripType' => TripType::PerKm,
            'startAt' => $this->at('2026-11-09 08:00'),
            'endAt' => $this->at('2026-11-09 18:00'),
            'startKm' => 0,
            'endKm' => 10,
            'ratePerKm' => 1000,
            'otherTaxable' => 50000,
            'otherTaxableLabel' => 'Decoration',
            'statePermit' => 70000,
        ]);
        $lines = $this->lines($r);

        $this->assertSame(50000, $lines['Decoration']->amount);
        $this->assertTrue($lines['Decoration']->taxable);
        $this->assertSame(70000, $lines['State permit']->amount);
        $this->assertFalse($lines['State permit']->taxable);
    }

    public function test_rejects_end_km_below_start_km(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End km cannot be less than start km.');
        $this->calc($this->outstationArgs(['endKm' => 9999]));
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trip end cannot be before trip start.');
        $this->calc($this->outstationArgs(['endAt' => $this->at('2026-11-09 07:00')]));
    }

    public function test_rejects_discount_above_taxable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount cannot be more than the chargeable amount.');
        $this->calc($this->outstationArgs(['discount' => 99999999]));
    }

    public function test_zero_lines_are_omitted(): void
    {
        $r = $this->calc($this->outstationArgs(['toll' => 0, 'parking' => 0, 'nightHaltPerNight' => 0]));
        $lines = $this->lines($r);

        $this->assertArrayNotHasKey('Toll', $lines);
        $this->assertArrayNotHasKey('Parking', $lines);
        $this->assertArrayNotHasKey('Night halt', $lines);
    }

    public function test_to_array_uses_decimal_strings(): void
    {
        $a = $this->calc($this->outstationArgs())->toArray();

        $this->assertSame('7743.00', $a['total']);
        $this->assertSame('0.50', $a['round_off']);
        $this->assertSame('171.25', $a['cgst']);
        $this->assertSame('cgst_sgst', $a['tax_type']);
        $this->assertSame('6000.00', $a['lines'][0]['amount']);
        $this->assertSame('12.00', $a['lines'][0]['rate']);
        $this->assertSame(500, $a['billable_km']);
    }
}
