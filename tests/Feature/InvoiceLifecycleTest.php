<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 21:00', 'Asia/Kolkata'));
        BusinessSetting::current()->update(['min_km_per_day' => 250]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function svc(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    public function test_generate_creates_items_matching_calculator(): void
    {
        $trip = Trip::factory()->create(['status' => TripStatus::Ongoing]);

        $invoice = $this->svc()->generate($trip);

        $this->assertSame('MTT/2026-27/0001', $invoice->invoice_no);
        $this->assertSame('2026-27', $invoice->financial_year);
        $this->assertSame('2026-11-10', $invoice->invoice_date->format('Y-m-d'));
        $this->assertSame('cgst_sgst', $invoice->tax_type->value);
        $this->assertCount(5, $invoice->items);
        $this->assertSame('Kilometre charges', $invoice->items[0]->description);
        $this->assertSame('6000.00', $invoice->items[0]->amount);
        $this->assertSame('7400.00', $invoice->subtotal);
        $this->assertSame('6850.00', $invoice->taxable_amount);
        $this->assertSame('171.25', $invoice->cgst);
        $this->assertSame('171.25', $invoice->sgst);
        $this->assertSame('0.50', $invoice->round_off);
        $this->assertSame('7743.00', $invoice->total);
        $this->assertSame(774300, $invoice->totalPaise());
        $this->assertSame(500, $invoice->meta['billable_km']);
        $this->assertSame(48, strlen($invoice->share_token));
        $this->assertTrue($invoice->share_enabled);
        $this->assertSame(TripStatus::Completed, $trip->fresh()->status);
    }

    public function test_second_generate_for_same_trip_fails(): void
    {
        $trip = Trip::factory()->create();
        $this->svc()->generate($trip);

        try {
            $this->svc()->generate($trip->fresh());
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertSame('This trip already has a bill.', $e->errors()['trip'][0]);
        }

        $this->assertSame(1, Invoice::count());
    }

    public function test_generate_via_http_twice_gives_one_invoice(): void
    {
        $owner = User::factory()->create(['role' => Role::Owner]);
        $trip = Trip::factory()->create();

        $this->actingAs($owner)->post("/trips/{$trip->id}/invoice")->assertRedirect("/trips/{$trip->id}");
        $this->actingAs($owner)->post("/trips/{$trip->id}/invoice")->assertSessionHasErrors('trip');

        $this->assertSame(1, Invoice::count());
    }

    public function test_generate_fails_cleanly_without_end_details(): void
    {
        $trip = Trip::factory()->create(['end_km' => null, 'status' => TripStatus::Booked]);

        $this->expectException(ValidationException::class);
        $this->svc()->generate($trip);
    }

    public function test_refresh_recomputes_until_locked(): void
    {
        $trip = Trip::factory()->create();
        $invoice = $this->svc()->generate($trip);

        $trip->update(['end_km' => 10620]);
        $invoice = $this->svc()->refresh($invoice);
        $this->assertSame('MTT/2026-27/0001', $invoice->invoice_no);
        $this->assertSame('7440.00', $invoice->items[0]->amount);

        $this->svc()->lock($invoice);
        $this->expectException(ValidationException::class);
        $this->svc()->refresh($invoice->fresh());
    }

    public function test_lock_sets_locked_at_once(): void
    {
        $invoice = $this->svc()->generate(Trip::factory()->create());

        $this->svc()->lock($invoice);
        $first = $invoice->fresh()->locked_at;
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 10:00', 'Asia/Kolkata'));
        $this->svc()->lock($invoice->fresh());

        $this->assertEquals($first, $invoice->fresh()->locked_at);
    }

    public function test_cancel_and_reissue_keeps_old_number_and_links_revision(): void
    {
        $trip = Trip::factory()->create();
        $old = $this->svc()->generate($trip);
        $this->svc()->lock($old);

        $new = $this->svc()->cancelAndReissue($old->fresh());

        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('MTT/2026-27/0001', $old->fresh()->invoice_no);
        $this->assertSame('MTT/2026-27/0002', $new->invoice_no);
        $this->assertSame($old->id, $new->revision_of_id);
        $this->assertSame('active', $new->status);
        $this->assertNull($new->locked_at);
        $this->assertSame($new->id, $trip->fresh()->activeInvoice->id);

        $this->expectException(ValidationException::class);
        $this->svc()->generate($trip->fresh());
    }
}
