<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Car $car;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => Role::Owner]);
        $this->car = Car::factory()->create(['rate_per_km' => '12.00']);
        $this->customer = Customer::factory()->create(['state' => 'Maharashtra']);
        BusinessSetting::current()->update(['min_km_per_day' => 250]);
    }

    /** The Task 3 outstation example as a form payload. */
    private function outstationPayload(array $o = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'car_id' => $this->car->id,
            'driver_id' => null,
            'pickup' => 'Shrirampur',
            'drop' => 'Pune',
            'start_at' => '2026-11-09 08:00',
            'end_at' => '2026-11-10 20:00',
            'trip_type' => 'per_km',
            'start_km' => 10000,
            'end_km' => 10300,
            'status' => 'completed',
            'billing' => [
                'rate_per_km' => '12',
                'driver_allowance_per_day' => '300',
                'night_halt_per_night' => '250',
                'toll' => '450',
                'parking' => '100',
                'gst_enabled' => true,
            ],
        ], $o);
    }

    public function test_trip_can_be_booked_without_end_details(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload([
            'status' => 'booked', 'end_at' => null, 'end_km' => null, 'start_km' => null,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $trip = Trip::firstOrFail();
        $this->assertSame(TripStatus::Booked, $trip->status);
        $this->assertNull($trip->end_km);
        $this->assertSame(TripType::PerKm, $trip->trip_type);
        $this->assertTrue($trip->billing_inputs['gst_enabled']);
    }

    public function test_completed_trip_requires_end_time_and_km(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload([
            'end_at' => null, 'end_km' => null,
        ]))->assertSessionHasErrors(['end_at', 'end_km']);
    }

    public function test_end_km_must_not_be_below_start_km(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload(['end_km' => 9000]))
            ->assertSessionHasErrors('end_km');
    }

    public function test_rates_default_from_car(): void
    {
        Car::factory()->create(['is_active' => false]);
        Driver::factory()->create();

        $this->actingAs($this->owner)->get('/trips/create')
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Form')
                ->has('cars', 1)
                ->where('cars.0.rate_per_km', '12.00')
                ->has('drivers', 1)
                ->where('settings.min_km_per_day', 250));
    }

    public function test_to_bill_input_converts_rupees_to_paise_and_uses_settings(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload());
        $input = Trip::firstOrFail()->toBillInput(BusinessSetting::current());

        $this->assertSame(1200, $input->ratePerKm);
        $this->assertSame(250, $input->minKmPerDay);
        $this->assertSame(30000, $input->driverAllowancePerDay);
        $this->assertSame(45000, $input->toll);
        $this->assertTrue($input->gstEnabled);
        $this->assertSame(5.0, $input->gstRate);
        $this->assertSame('Maharashtra', $input->customerState);
        $this->assertSame('2026-11-09 08:00', $input->startAt->format('Y-m-d H:i'));
        $this->assertSame('Asia/Kolkata', $input->startAt->getTimezone()->getName());
    }

    public function test_preview_returns_same_totals_as_calculator(): void
    {
        $this->actingAs($this->owner)->postJson('/bill-preview', $this->outstationPayload())
            ->assertOk()
            ->assertJsonPath('total', '7743.00')
            ->assertJsonPath('round_off', '0.50')
            ->assertJsonPath('billable_km', 500)
            ->assertJsonPath('tax_type', 'cgst_sgst');
    }

    public function test_preview_uses_igst_for_out_of_state_customer(): void
    {
        $other = Customer::factory()->create(['state' => 'Karnataka']);

        $this->actingAs($this->owner)->postJson('/bill-preview', $this->outstationPayload(['customer_id' => $other->id]))
            ->assertOk()->assertJsonPath('tax_type', 'igst');
    }

    public function test_preview_returns_422_when_end_km_below_start(): void
    {
        $this->actingAs($this->owner)->postJson('/bill-preview', $this->outstationPayload(['end_km' => 9999]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'End km cannot be less than start km.');
    }

    public function test_preview_returns_422_when_end_details_missing(): void
    {
        $this->actingAs($this->owner)->postJson('/bill-preview', $this->outstationPayload(['end_km' => null]))
            ->assertStatus(422);
    }

    public function test_trip_can_be_updated(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload(['status' => 'booked']));
        $trip = Trip::firstOrFail();

        $this->actingAs($this->owner)->put("/trips/{$trip->id}", $this->outstationPayload(['drop' => 'Mumbai']))
            ->assertSessionHasNoErrors()->assertRedirect("/trips/{$trip->id}");

        $this->assertSame('Mumbai', $trip->fresh()->drop);
        $this->assertSame(TripStatus::Completed, $trip->fresh()->status);
    }

    public function test_save_and_create_bill_in_one_step(): void
    {
        $this->actingAs($this->owner)
            ->post('/trips', $this->outstationPayload(['generate_invoice' => true]))
            ->assertSessionHasNoErrors();

        $trip = Trip::firstOrFail();
        $this->assertSame('7743.00', $trip->activeInvoice->total);
    }

    public function test_save_and_update_bill_refreshes_unlocked_invoice(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload(['generate_invoice' => true]));
        $trip = Trip::firstOrFail();

        $this->actingAs($this->owner)
            ->put("/trips/{$trip->id}", $this->outstationPayload(['end_km' => 10620, 'generate_invoice' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $trip->invoices()->count());
        $this->assertSame('7440.00', $trip->fresh()->activeInvoice->items()->first()->amount);
    }

    public function test_generate_invoice_on_booked_trip_is_ignored(): void
    {
        $this->actingAs($this->owner)->post('/trips', $this->outstationPayload([
            'status' => 'booked', 'end_at' => null, 'end_km' => null, 'generate_invoice' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Trip::firstOrFail()->activeInvoice);
    }
}
