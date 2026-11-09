<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\ShareChannel;
use App\Mail\InvoiceMail;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ShareLog;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ShareService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShareTest extends TestCase
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

    private function invoice(?string $phone = '98765 43210'): Invoice
    {
        $customer = Customer::factory()->create(['name' => 'Suresh Kale', 'phone' => $phone, 'email' => 'suresh@example.com']);
        $trip = Trip::factory()->create(['customer_id' => $customer->id]);

        return app(InvoiceService::class)->generate($trip);
    }

    private function share(): ShareService
    {
        return app(ShareService::class);
    }

    public function test_message_text(): void
    {
        $invoice = $this->invoice();

        $this->assertSame(
            'Dear Suresh Kale, your bill MTT/2026-27/0001 for ₹7,743.00 from Mahale Tours and Travels: '.url('/b/'.$invoice->share_token),
            $this->share()->message($invoice),
        );
    }

    public function test_whatsapp_url_encodes_message(): void
    {
        $url = $this->share()->whatsappUrl($this->invoice());

        $this->assertStringStartsWith('https://wa.me/919876543210?text=Dear%20Suresh%20Kale', $url);
        $this->assertStringContainsString(rawurlencode('₹7,743.00'), $url);
    }

    public function test_whatsapp_url_null_without_valid_phone(): void
    {
        $this->assertNull($this->share()->whatsappUrl($this->invoice(null)));
        $this->assertNull($this->share()->whatsappUrl($this->invoice('12345')));
    }

    public function test_public_link_streams_pdf_without_login(): void
    {
        $invoice = $this->invoice();

        $this->get('/b/'.$invoice->share_token)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_public_link_404_when_disabled(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->owner)->post("/invoices/{$invoice->id}/link", ['enabled' => false])->assertRedirect();
        $this->assertFalse($invoice->fresh()->share_enabled);

        auth()->logout();
        $this->get('/b/'.$invoice->share_token)->assertNotFound();
    }

    public function test_unknown_token_404(): void
    {
        $this->get('/b/'.str_repeat('x', 48))->assertNotFound();
    }

    public function test_first_share_locks_invoice_and_logs(): void
    {
        $invoice = $this->invoice();
        $this->assertNull($invoice->locked_at);

        $this->actingAs($this->owner)
            ->postJson("/invoices/{$invoice->id}/share", ['channel' => 'whatsapp_link'])
            ->assertOk();

        $invoice->refresh();
        $this->assertNotNull($invoice->locked_at);
        $log = ShareLog::firstOrFail();
        $this->assertSame(ShareChannel::WhatsappLink, $log->channel);
        $this->assertSame($invoice->id, $log->invoice_id);
        $this->assertSame('919876543210', $log->recipient);
    }

    public function test_share_rejects_unknown_channel(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->owner)
            ->postJson("/invoices/{$invoice->id}/share", ['channel' => 'pigeon'])
            ->assertUnprocessable();
        $this->assertSame(0, ShareLog::count());
    }

    public function test_email_sends_with_pdf_attachment(): void
    {
        Mail::fake();
        $invoice = $this->invoice();

        $this->actingAs($this->owner)
            ->post("/invoices/{$invoice->id}/email", ['to' => 'suresh@example.com'])
            ->assertRedirect("/trips/{$invoice->trip_id}")
            ->assertSessionHasNoErrors();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $names = collect($mail->attachments())->map(fn ($a) => $a->as)->all();

            return $mail->hasTo('suresh@example.com')
                && in_array('MTT-2026-27-0001.pdf', $names, true)
                && $mail->envelope()->subject === 'Bill MTT/2026-27/0001 from Mahale Tours and Travels';
        });

        $this->assertSame(ShareChannel::Email, ShareLog::firstOrFail()->channel);
        $this->assertNotNull($invoice->fresh()->locked_at);
    }

    public function test_email_requires_valid_address(): void
    {
        Mail::fake();
        $invoice = $this->invoice();

        $this->actingAs($this->owner)
            ->post("/invoices/{$invoice->id}/email", ['to' => 'not-an-email'])
            ->assertSessionHasErrors('to');
        Mail::assertNothingSent();
    }

    public function test_receipt_share_logs_against_payment(): void
    {
        $trip = Trip::factory()->create();
        $payment = Payment::create(['trip_id' => $trip->id, 'amount' => '2000', 'paid_on' => '2026-11-09', 'mode' => 'cash']);

        $this->actingAs($this->owner)
            ->postJson("/payments/{$payment->id}/share", ['channel' => 'share_sheet'])
            ->assertOk();

        $log = ShareLog::firstOrFail();
        $this->assertSame($payment->id, $log->payment_id);
        $this->assertNull($log->invoice_id);
    }
}
