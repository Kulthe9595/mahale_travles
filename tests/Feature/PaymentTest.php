<?php

namespace Tests\Feature;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\TripStatus;
use App\Models\BusinessSetting;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => Role::Owner]);
        BusinessSetting::current()->update(['min_km_per_day' => 250]);
    }

    private function pay(Trip $trip, string $amount, array $o = [])
    {
        return $this->actingAs($this->owner)->post("/trips/{$trip->id}/payments", array_merge([
            'amount' => $amount, 'paid_on' => '2026-11-09', 'mode' => 'upi', 'reference' => 'UTR123',
        ], $o));
    }

    /** Trip with the ₹7,743.00 bill from the calculator example. */
    private function billedTrip(): Trip
    {
        $trip = Trip::factory()->create();
        app(InvoiceService::class)->generate($trip);

        return $trip->fresh();
    }

    public function test_advance_before_invoice(): void
    {
        $trip = Trip::factory()->create(['status' => TripStatus::Booked, 'end_km' => null]);

        $this->pay($trip, '2000')->assertSessionHasNoErrors()->assertRedirect("/trips/{$trip->id}");

        $trip->refresh();
        $this->assertSame(200000, $trip->paidPaise());
        $this->assertNull($trip->balanceDuePaise());
        $this->assertNull($trip->paymentStatus());
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentMode::Upi, $payment->mode);
        $this->assertSame('RCPT-00001', $payment->receiptNumber());
    }

    public function test_status_moves_unpaid_partly_paid_paid(): void
    {
        $trip = $this->billedTrip();
        $this->assertSame(PaymentStatus::Unpaid, $trip->paymentStatus());
        $this->assertSame(774300, $trip->balanceDuePaise());

        $this->pay($trip, '2000');
        $trip->refresh();
        $this->assertSame(PaymentStatus::PartlyPaid, $trip->paymentStatus());
        $this->assertSame(574300, $trip->balanceDuePaise());

        $this->pay($trip, '5743', ['mode' => 'cash', 'reference' => null]);
        $trip->refresh();
        $this->assertSame(PaymentStatus::Paid, $trip->paymentStatus());
        $this->assertSame(0, $trip->balanceDuePaise());
        $this->assertSame(0, $trip->excessPaise());
    }

    public function test_advance_counts_against_bill_created_later(): void
    {
        $trip = Trip::factory()->create();
        $this->pay($trip, '2000');

        app(InvoiceService::class)->generate($trip->fresh());

        $this->assertSame(PaymentStatus::PartlyPaid, $trip->fresh()->paymentStatus());
        $this->assertSame(574300, $trip->fresh()->balanceDuePaise());
    }

    public function test_overpayment_shows_excess(): void
    {
        $trip = $this->billedTrip();

        $this->pay($trip, '8000');
        $trip->refresh();

        $this->assertSame(PaymentStatus::Paid, $trip->paymentStatus());
        $this->assertSame(0, $trip->balanceDuePaise());
        $this->assertSame(25700, $trip->excessPaise());
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $trip = $this->billedTrip();

        $this->pay($trip, '0')->assertSessionHasErrors('amount');
        $this->pay($trip, '-5')->assertSessionHasErrors('amount');
        $this->pay($trip, '100', ['mode' => 'cheque-ish'])->assertSessionHasErrors('mode');
        $this->assertSame(0, Payment::count());
    }

    public function test_payment_can_be_deleted_by_owner_only(): void
    {
        $trip = $this->billedTrip();
        $this->pay($trip, '1000');
        $payment = Payment::firstOrFail();

        $staff = User::factory()->create(['role' => Role::Staff]);
        $this->actingAs($staff)->delete("/payments/{$payment->id}")->assertForbidden();
        $this->assertSame(1, Payment::count());

        $this->actingAs($this->owner)->delete("/payments/{$payment->id}")->assertRedirect("/trips/{$trip->id}");
        $this->assertSame(0, Payment::count());
    }
}
