<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripStatus;
use App\Exports\GstSummaryExport;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        BusinessSetting::current()->update(['min_km_per_day' => 250]);
        $this->owner = User::factory()->create(['role' => Role::Owner]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function at(string $dt): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($dt, 'Asia/Kolkata'));
    }

    /** Bills a trip on the given date; GST on by default (₹7,743.00), off gives ₹7,400.00. */
    private function bill(string $date, array $tripAttrs = [], bool $gst = true): Invoice
    {
        $this->at($date);
        $trip = Trip::factory()->create($tripAttrs);
        $trip->update(['billing_inputs' => array_merge($trip->billing_inputs, ['gst_enabled' => $gst])]);

        return app(InvoiceService::class)->generate($trip->fresh());
    }

    private function day(string $d): CarbonImmutable
    {
        return CarbonImmutable::parse($d, 'Asia/Kolkata');
    }

    public function test_earnings_group_by_car_and_skip_cancelled(): void
    {
        $innova = Car::factory()->create(['registration_no' => 'MH17AA0001']);
        $dzire = Car::factory()->create(['registration_no' => 'MH17AA0002']);

        $this->bill('2026-11-05 10:00', ['car_id' => $innova->id]);
        $this->bill('2026-11-06 10:00', ['car_id' => $innova->id], false);
        $cancelled = $this->bill('2026-11-07 10:00', ['car_id' => $dzire->id]);
        app(InvoiceService::class)->cancelAndReissue($cancelled);
        $this->bill('2026-10-30 10:00', ['car_id' => $dzire->id]);

        $rows = app(ReportService::class)->earningsByCar($this->day('2026-11-01'), $this->day('2026-11-30'));
        $byReg = $rows->keyBy(fn ($r) => $r['car']->registration_no);

        $this->assertSame(2, $byReg['MH17AA0001']['trips']);
        $this->assertSame(600, $byReg['MH17AA0001']['km']);
        $this->assertSame(774300 + 740000, $byReg['MH17AA0001']['revenue_paise']);
        // The reissued bill (dated 7 Nov) counts once; the cancelled one does not; 30 Oct is outside.
        $this->assertSame(1, $byReg['MH17AA0002']['trips']);
        $this->assertSame(774300, $byReg['MH17AA0002']['revenue_paise']);
        $this->assertSame('MH17AA0001', $rows->first()['car']->registration_no);
    }

    public function test_gst_summary_excludes_non_gst_and_cancelled(): void
    {
        $customer = Customer::factory()->create(['name' => 'Patil Industries', 'gstin' => '27AAACP1234Q1Z5']);
        $gst = $this->bill('2026-11-05 10:00', ['customer_id' => $customer->id]);
        $this->bill('2026-11-06 10:00', [], false);
        $old = $this->bill('2026-11-07 10:00');
        $old->update(['status' => Invoice::STATUS_CANCELLED]);

        $rows = app(ReportService::class)->gstSummary($this->day('2026-11-01'), $this->day('2026-11-30'));

        $this->assertCount(1, $rows);
        $this->assertSame($gst->invoice_no, $rows[0]['invoice_no']);
        $this->assertSame('05-11-2026', $rows[0]['invoice_date']);
        $this->assertSame('Patil Industries', $rows[0]['customer']);
        $this->assertSame('27AAACP1234Q1Z5', $rows[0]['gstin']);
        $this->assertSame('6850.00', $rows[0]['taxable']);
        $this->assertSame('171.25', $rows[0]['cgst']);
        $this->assertSame('171.25', $rows[0]['sgst']);
        $this->assertSame('0.00', $rows[0]['igst']);
        $this->assertSame('7743.00', $rows[0]['total']);
    }

    public function test_gst_export_downloads_xlsx(): void
    {
        Excel::fake();
        $this->bill('2026-11-05 10:00');

        $this->actingAs($this->owner)->get('/reports/gst/export?from=2026-11-01&to=2026-11-30')->assertOk();

        Excel::assertDownloaded('gst-summary-2026-11-01-to-2026-11-30.xlsx', function (GstSummaryExport $export) {
            return $export->collection()->count() === 1
                && $export->headings()[0] === 'Invoice no.';
        });
    }

    public function test_outstanding_sorted_oldest_first_and_excludes_paid(): void
    {
        $older = $this->bill('2026-11-01 10:00');
        $newer = $this->bill('2026-11-08 10:00');
        $paid = $this->bill('2026-11-09 10:00');
        Payment::create(['trip_id' => $paid->trip_id, 'amount' => '7743', 'paid_on' => '2026-11-09', 'mode' => 'cash']);
        Payment::create(['trip_id' => $newer->trip_id, 'amount' => '743', 'paid_on' => '2026-11-09', 'mode' => 'cash']);
        $this->at('2026-11-15 10:00');

        $rows = app(ReportService::class)->outstanding();

        $this->assertCount(2, $rows);
        $this->assertSame($older->invoice_no, $rows[0]['invoice_no']);
        $this->assertSame(14, $rows[0]['age_days']);
        $this->assertSame(774300, $rows[0]['balance_paise']);
        $this->assertSame($newer->invoice_no, $rows[1]['invoice_no']);
        $this->assertSame(700000, $rows[1]['balance_paise']);
        $this->assertSame(74300, $rows[1]['paid_paise']);
    }

    public function test_dashboard_pending_sums_balances(): void
    {
        $a = $this->bill('2026-11-01 10:00');
        $this->bill('2026-10-20 10:00');
        Payment::create(['trip_id' => $a->trip_id, 'amount' => '1743', 'paid_on' => '2026-11-02', 'mode' => 'cash']);
        $this->at('2026-11-15 09:00');
        Trip::factory()->create(['start_at' => '2026-11-15 14:00', 'end_at' => null, 'end_km' => null, 'status' => TripStatus::Booked]);
        Trip::factory()->create(['start_at' => '2026-11-18 06:00', 'end_at' => null, 'end_km' => null, 'status' => TripStatus::Booked]);

        $d = app(ReportService::class)->dashboard();

        $this->assertSame(600000 + 774300, $d['pending_paise']);
        $this->assertSame(774300, $d['month_revenue_paise']);
        $this->assertCount(1, $d['today_trips']);
        $this->assertCount(1, $d['upcoming_trips']);

        $this->actingAs($this->owner)->get('/')
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->where('stats.pending', '13743.00')
                ->where('stats.month_revenue', '7743.00')
                ->has('todayTrips', 1)
                ->has('upcomingTrips', 1));
    }

    public function test_report_pages_render(): void
    {
        $this->bill('2026-11-05 10:00');
        $this->at('2026-11-15 10:00');

        $this->actingAs($this->owner)->get('/reports')->assertInertia(fn ($p) => $p->component('Reports/Index'));
        $this->actingAs($this->owner)->get('/reports/earnings?month=2026-11')
            ->assertInertia(fn ($p) => $p->component('Reports/Earnings')->has('rows', 1)->where('month', '2026-11'));
        $this->actingAs($this->owner)->get('/reports/gst?from=2026-11-01&to=2026-11-30')
            ->assertInertia(fn ($p) => $p->component('Reports/Gst')->has('rows', 1)->where('totals.total', '7743.00'));
        $this->actingAs($this->owner)->get('/reports/outstanding')
            ->assertInertia(fn ($p) => $p->component('Reports/Outstanding')->has('rows', 1)->where('total', '7743.00'));
    }

    public function test_customer_ledger_lists_bills_with_balance(): void
    {
        $customer = Customer::factory()->create();
        $a = $this->bill('2026-11-01 10:00', ['customer_id' => $customer->id]);
        $this->bill('2026-11-02 10:00', ['customer_id' => $customer->id]);
        $this->bill('2026-11-03 10:00');
        Payment::create(['trip_id' => $a->trip_id, 'amount' => '7743', 'paid_on' => '2026-11-02', 'mode' => 'upi']);

        $this->actingAs($this->owner)->get("/customers/{$customer->id}")
            ->assertInertia(fn ($page) => $page->component('Customers/Show')
                ->where('customer.id', $customer->id)
                ->has('bills', 2)
                ->where('bills.0.invoice_no', 'MTT/2026-27/0002')
                ->where('bills.0.balance', '7743.00')
                ->where('bills.1.paid', '7743.00')
                ->where('bills.1.balance', '0.00')
                ->where('outstanding', '7743.00'));
    }
}
