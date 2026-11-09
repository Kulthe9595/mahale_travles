<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ShareService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 21:00', 'Asia/Kolkata'));
        BusinessSetting::current()->update(['min_km_per_day' => 250]);
        $this->owner = User::factory()->create(['role' => Role::Owner]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return array{unpaid: Trip, partly: Trip, paid: Trip, unbilled: Trip} */
    private function seedTrips(): array
    {
        $svc = app(InvoiceService::class);
        $unpaid = Trip::factory()->create();
        $svc->generate($unpaid);

        $car = Car::factory()->create(['registration_no' => 'MH17XY9999']);
        $partly = Trip::factory()->create(['car_id' => $car->id]);
        $svc->generate($partly);
        Payment::create(['trip_id' => $partly->id, 'amount' => '1000', 'paid_on' => '2026-11-10', 'mode' => 'cash']);

        $customer = Customer::factory()->create(['name' => 'Zebra Logistics', 'phone' => '9123456780']);
        $paid = Trip::factory()->create(['customer_id' => $customer->id]);
        $svc->generate($paid);
        Payment::create(['trip_id' => $paid->id, 'amount' => '7743', 'paid_on' => '2026-11-10', 'mode' => 'upi']);

        $unbilled = Trip::factory()->create(['end_km' => null, 'status' => 'booked']);

        return compact('unpaid', 'partly', 'paid', 'unbilled');
    }

    public function test_index_filters_by_payment_status(): void
    {
        $t = $this->seedTrips();

        foreach (['unpaid' => $t['unpaid'], 'partly_paid' => $t['partly'], 'paid' => $t['paid']] as $status => $trip) {
            $this->actingAs($this->owner)->get("/trips?status={$status}")
                ->assertInertia(fn ($page) => $page
                    ->component('Trips/Index')
                    ->has('trips.data', 1)
                    ->where('trips.data.0.id', $trip->id)
                    ->where('trips.data.0.payment_status', $status));
        }

        $this->actingAs($this->owner)->get('/trips?status=unbilled')
            ->assertInertia(fn ($page) => $page->has('trips.data', 1)->where('trips.data.0.id', $t['unbilled']->id));

        $this->actingAs($this->owner)->get('/trips')
            ->assertInertia(fn ($page) => $page->has('trips.data', 4));
    }

    public function test_index_search_matches_car_customer_phone_and_invoice_no(): void
    {
        $t = $this->seedTrips();

        $cases = [
            'MH17XY' => $t['partly'],
            'mh 17 xy 9999' => $t['partly'],
            'zebra' => $t['paid'],
            '91234' => $t['paid'],
            '2026-27/0001' => $t['unpaid'],
        ];

        foreach ($cases as $q => $trip) {
            $this->actingAs($this->owner)->get('/trips?q='.urlencode((string) $q))
                ->assertInertia(fn ($page) => $page
                    ->has('trips.data', 1)
                    ->where('trips.data.0.id', $trip->id)
                    ->where('filters.q', (string) $q));
        }
    }

    public function test_index_rows_carry_money_columns(): void
    {
        $t = $this->seedTrips();

        $this->actingAs($this->owner)->get('/trips?status=partly_paid')
            ->assertInertia(fn ($page) => $page
                ->where('trips.data.0.invoice_no', 'MTT/2026-27/0002')
                ->where('trips.data.0.total', '7743.00')
                ->where('trips.data.0.balance', '6743.00')
                ->where('trips.data.0.car.registration_no', 'MH17XY9999'));
    }

    public function test_show_page_props_include_totals_payments_share_log_and_whatsapp_url(): void
    {
        $t = $this->seedTrips();
        $trip = $t['partly'];
        $invoice = $trip->fresh()->activeInvoice;
        app(ShareService::class)->log($invoice, \App\Enums\ShareChannel::WhatsappLink, '919000000000');

        $this->actingAs($this->owner)->get("/trips/{$trip->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->where('trip.id', $trip->id)
                ->where('invoice.invoice_no', 'MTT/2026-27/0002')
                ->where('invoice.locked', true)
                ->has('invoice.items', 5)
                ->where('money.total', '7743.00')
                ->where('money.paid', '1000.00')
                ->where('money.balance', '6743.00')
                ->where('money.excess', '0.00')
                ->where('money.status', 'partly_paid')
                ->has('payments', 1)
                ->where('payments.0.receipt_no', 'RCPT-00001')
                ->has('shareLogs', 1)
                ->where('share.whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/'))
                ->where('share.public_url', url('/b/'.$invoice->share_token))
                ->where('share.filename', 'MTT-2026-27-0002.pdf')
                ->where('canDeletePayments', true));
    }

    public function test_show_page_for_unbilled_trip_has_no_invoice(): void
    {
        $t = $this->seedTrips();

        $this->actingAs($this->owner)->get("/trips/{$t['unbilled']->id}")
            ->assertInertia(fn ($page) => $page
                ->where('invoice', null)
                ->where('money.status', null)
                ->where('canGenerate', false));
    }

    public function test_form_props_include_active_cars_and_drivers_only(): void
    {
        Car::factory()->create(['is_active' => true]);
        Car::factory()->create(['is_active' => false]);

        $this->actingAs($this->owner)->get('/trips/create')
            ->assertInertia(fn ($page) => $page->component('Trips/Form')->has('cars', 1)->where('trip', null));
    }

    public function test_edit_form_keeps_inactive_car_already_on_trip(): void
    {
        $inactive = Car::factory()->create(['is_active' => false]);
        $trip = Trip::factory()->create(['car_id' => $inactive->id]);

        $this->actingAs($this->owner)->get("/trips/{$trip->id}/edit")
            ->assertInertia(fn ($page) => $page->has('cars', 1)->where('cars.0.id', $inactive->id));
    }
}
