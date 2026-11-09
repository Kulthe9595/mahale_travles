<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Services\InvoiceNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $dt): CarbonImmutable
    {
        return CarbonImmutable::parse($dt, 'Asia/Kolkata');
    }

    public function test_financial_year(): void
    {
        $svc = new InvoiceNumberService;

        $this->assertSame('2026-27', $svc->financialYear($this->at('2026-11-09 10:00')));
        $this->assertSame('2026-27', $svc->financialYear($this->at('2027-03-31 23:59')));
        $this->assertSame('2027-28', $svc->financialYear($this->at('2027-04-01 00:00')));
        $this->assertSame('2099-00', $svc->financialYear($this->at('2099-06-01 00:00')));
    }

    public function test_numbers_increment_within_fy(): void
    {
        $svc = new InvoiceNumberService;

        $this->assertSame('MTT/2026-27/0001', $svc->next($this->at('2026-11-09 10:00')));
        $this->assertSame('MTT/2026-27/0002', $svc->next($this->at('2026-11-09 10:05')));
    }

    public function test_fy_boundary_in_ist(): void
    {
        $svc = new InvoiceNumberService;

        $this->assertSame('MTT/2025-26/0001', $svc->next($this->at('2026-03-30 12:00')));
        $this->assertSame('MTT/2025-26/0002', $svc->next($this->at('2026-03-31 23:30')));
        $this->assertSame('MTT/2026-27/0001', $svc->next($this->at('2026-04-01 00:10')));

        // 31 Mar 19:00 UTC is already 1 Apr 00:30 in India.
        $utc = CarbonImmutable::parse('2026-03-31 19:00', 'UTC');
        $this->assertSame('MTT/2026-27/0002', $svc->next($utc));
    }

    public function test_prefix_change_starts_its_own_series(): void
    {
        $svc = new InvoiceNumberService;
        $svc->next($this->at('2026-11-09 10:00'));

        BusinessSetting::current()->update(['invoice_prefix' => 'MAH']);

        $this->assertSame('MAH/2026-27/0001', $svc->next($this->at('2026-11-09 11:00')));
    }

    public function test_numbers_beyond_9999_keep_growing(): void
    {
        $svc = new InvoiceNumberService;
        \App\Models\InvoiceSequence::create(['financial_year' => '2026-27', 'prefix' => 'MTT', 'last_number' => 9999]);

        $this->assertSame('MTT/2026-27/10000', $svc->next($this->at('2026-11-09 10:00')));
    }
}
