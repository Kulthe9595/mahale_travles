<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripStatus;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Regression tests for the final whole-branch review findings. */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 21:00', 'Asia/Kolkata'));
        BusinessSetting::current()->update(['min_km_per_day' => 250, 'gstin' => '27ABCDE1234F1Z5']);
        $this->owner = User::factory()->create(['role' => Role::Owner]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function payload(Trip $trip, array $o = []): array
    {
        return array_merge([
            'customer_id' => $trip->customer_id,
            'car_id' => $trip->car_id,
            'pickup' => $trip->pickup,
            'drop' => $trip->drop,
            'start_at' => '2026-11-09 08:00',
            'end_at' => '2026-11-10 20:00',
            'trip_type' => 'per_km',
            'start_km' => 10000,
            'end_km' => 10300,
            'status' => 'completed',
            'billing' => $trip->billing_inputs,
        ], $o);
    }

    // Critical #1: owner login on first deployment
    public function test_create_owner_command_creates_owner_without_env(): void
    {
        $this->artisan('app:create-owner', ['email' => 'jiju@example.com', '--name' => 'Jiju'])
            ->expectsQuestion('Password (min 8 characters)', 'strong-pass-1')
            ->expectsQuestion('Repeat password', 'strong-pass-1')
            ->assertSuccessful();

        $user = User::where('email', 'jiju@example.com')->firstOrFail();
        $this->assertSame(Role::Owner, $user->role);
        $this->assertSame('Jiju', $user->name);
        $this->assertTrue(Hash::check('strong-pass-1', $user->password));
    }

    public function test_create_owner_rejects_mismatched_or_short_password(): void
    {
        $this->artisan('app:create-owner', ['email' => 'jiju@example.com'])
            ->expectsQuestion('Password (min 8 characters)', 'short')
            ->expectsQuestion('Repeat password', 'short')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'jiju@example.com')->count());
    }

    // Important #2: issued bill is frozen
    public function test_shared_bill_does_not_change_when_trip_or_customer_is_edited(): void
    {
        $trip = Trip::factory()->create();
        $invoice = app(InvoiceService::class)->generate($trip);
        app(InvoiceService::class)->lock($invoice);
        $before = app(InvoicePdfService::class)->invoiceHtml($invoice->fresh());

        $trip->customer->update(['name' => 'Renamed Customer', 'gstin' => '27AAACP1234Q1Z5']);
        $this->actingAs($this->owner)
            ->put("/trips/{$trip->id}", $this->payload($trip, ['pickup' => 'Nashik', 'end_km' => 10900]))
            ->assertSessionHasNoErrors();

        $after = app(InvoicePdfService::class)->invoiceHtml(Invoice::findOrFail($invoice->id));
        $this->assertSame($before, $after);
        $this->assertStringNotContainsString('Nashik', $after);
        $this->assertStringNotContainsString('Renamed Customer', $after);
    }

    public function test_locked_bill_rejects_changing_the_customer(): void
    {
        $trip = Trip::factory()->create();
        app(InvoiceService::class)->lock(app(InvoiceService::class)->generate($trip));
        $other = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->put("/trips/{$trip->id}", $this->payload($trip, ['customer_id' => $other->id]))
            ->assertSessionHasErrors('customer_id');

        $this->assertNotSame($other->id, $trip->fresh()->customer_id);
    }

    public function test_unlocked_bill_follows_trip_edits(): void
    {
        $trip = Trip::factory()->create();
        app(InvoiceService::class)->generate($trip);

        $this->actingAs($this->owner)
            ->put("/trips/{$trip->id}", $this->payload($trip, ['pickup' => 'Nashik', 'end_km' => 10620]))
            ->assertSessionHasNoErrors();

        $invoice = $trip->fresh()->activeInvoice;
        $this->assertSame('7440.00', $invoice->items()->first()->amount);
        $this->assertStringContainsString('Nashik', app(InvoicePdfService::class)->invoiceHtml($invoice));
    }

    public function test_earnings_km_uses_billed_km_not_later_trip_edits(): void
    {
        $car = Car::factory()->create();
        $trip = Trip::factory()->create(['car_id' => $car->id]);
        app(InvoiceService::class)->lock(app(InvoiceService::class)->generate($trip));
        $trip->update(['end_km' => 99999]);

        $row = app(ReportService::class)
            ->earningsByCar(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))
            ->firstWhere(fn ($r) => $r['car']->id === $car->id);

        $this->assertSame(300, $row['km']);
    }

    // Important #3: cancelling bills and trips
    public function test_owner_can_cancel_bill_without_reissue(): void
    {
        $trip = Trip::factory()->create();
        $invoice = app(InvoiceService::class)->generate($trip);

        $this->actingAs($this->owner)->post("/invoices/{$invoice->id}/cancel")->assertRedirect("/trips/{$trip->id}");

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->fresh()->status);
        $this->assertNull($trip->fresh()->activeInvoice);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, app(ReportService::class)->outstanding()->count());
    }

    public function test_staff_cannot_cancel_bill(): void
    {
        $invoice = app(InvoiceService::class)->generate(Trip::factory()->create());
        $staff = User::factory()->create(['role' => Role::Staff]);

        $this->actingAs($staff)->post("/invoices/{$invoice->id}/cancel")->assertForbidden();
        $this->assertSame(Invoice::STATUS_ACTIVE, $invoice->fresh()->status);
    }

    public function test_trip_with_active_bill_cannot_be_marked_cancelled(): void
    {
        $trip = Trip::factory()->create();
        app(InvoiceService::class)->generate($trip);

        $this->actingAs($this->owner)
            ->put("/trips/{$trip->id}", $this->payload($trip, ['status' => 'cancelled']))
            ->assertSessionHasErrors('status');

        $this->assertSame(TripStatus::Completed, $trip->fresh()->status);
    }

    public function test_cancelled_trip_cannot_be_billed(): void
    {
        $trip = Trip::factory()->create(['status' => TripStatus::Cancelled]);

        try {
            app(InvoiceService::class)->generate($trip);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertSame('This trip is cancelled.', $e->errors()['trip'][0]);
        }
        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);
    }

    // Important #4: states
    public function test_customer_state_must_be_a_known_state(): void
    {
        $this->actingAs($this->owner)->post('/customers', ['name' => 'X', 'state' => 'MH'])
            ->assertSessionHasErrors('state');
        $this->actingAs($this->owner)->post('/customers', ['name' => 'X', 'state' => 'Maharastra'])
            ->assertSessionHasErrors('state');
        $this->actingAs($this->owner)->post('/customers', ['name' => 'X', 'state' => 'Karnataka'])
            ->assertSessionHasNoErrors();
    }

    public function test_customer_gstin_must_match_state(): void
    {
        $this->actingAs($this->owner)
            ->post('/customers', ['name' => 'X', 'state' => 'Karnataka', 'gstin' => '27AAACP1234Q1Z5'])
            ->assertSessionHasErrors('gstin');
    }

    public function test_settings_state_must_be_a_known_state(): void
    {
        $this->actingAs($this->owner)->post('/settings', [
            'name' => 'Mahale Tours and Travels', 'state' => 'MH', 'sac_code' => '996601',
            'gst_rate' => 5, 'min_km_per_day' => 0, 'invoice_prefix' => 'MTT',
        ])->assertSessionHasErrors('state');
    }

    public function test_form_pages_receive_state_list(): void
    {
        $this->actingAs($this->owner)->get('/customers/create')
            ->assertInertia(fn ($p) => $p->has('states', 36)->where('states.0', 'Andaman and Nicobar Islands'));
    }

    // Re-graded: email failure
    public function test_email_failure_shows_message_not_error_page(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        $invoice = app(InvoiceService::class)->generate(Trip::factory()->create());

        $this->actingAs($this->owner)
            ->post("/invoices/{$invoice->id}/email", ['to' => 'a@example.com'])
            ->assertRedirect("/trips/{$invoice->trip_id}")
            ->assertSessionHas('error', 'Email could not be sent. Check the mail settings (Gmail app password).');

        $this->assertNull($invoice->fresh()->locked_at);
    }

    // Re-graded: Save & bill is all-or-nothing
    public function test_save_and_bill_failure_does_not_leave_a_trip_behind(): void
    {
        $trip = Trip::factory()->make();
        $payload = $this->payload($trip, ['generate_invoice' => true]);
        $payload['billing']['discount'] = '99999';
        $payload['customer_id'] = Customer::factory()->create()->id;
        $payload['car_id'] = Car::factory()->create()->id;

        $this->actingAs($this->owner)->post('/trips', $payload)->assertSessionHasErrors('trip');

        $this->assertSame(0, Trip::count());
    }
}
