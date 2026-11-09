<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 21:00', 'Asia/Kolkata'));
        BusinessSetting::current()->update([
            'min_km_per_day' => 250,
            'gstin' => '27ABCDE1234F1Z5',
            'upi_id' => 'mahale@upi',
            'bank_details' => 'SBI A/c 12345',
            'terms' => 'Thank you for travelling with us.',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function pdf(): InvoicePdfService
    {
        return app(InvoicePdfService::class);
    }

    private function gstInvoice(): Invoice
    {
        $customer = Customer::factory()->create([
            'name' => 'Patil Industries', 'gstin' => '27AAACP1234Q1Z5', 'state' => 'Maharashtra',
        ]);
        $trip = Trip::factory()->create(['customer_id' => $customer->id]);

        return app(InvoiceService::class)->generate($trip);
    }

    private function nonGstInvoice(): Invoice
    {
        $trip = Trip::factory()->create();
        $trip->update(['billing_inputs' => array_merge($trip->billing_inputs, ['gst_enabled' => false])]);

        return app(InvoiceService::class)->generate($trip->fresh());
    }

    public function test_gst_invoice_shows_tax_invoice_title_gstin_and_split(): void
    {
        $html = $this->pdf()->invoiceHtml($this->gstInvoice());

        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('27ABCDE1234F1Z5', $html);
        $this->assertStringContainsString('27AAACP1234Q1Z5', $html);
        $this->assertStringContainsString('SAC', $html);
        $this->assertStringContainsString('CGST @ 2.5%', $html);
        $this->assertStringContainsString('SGST @ 2.5%', $html);
        $this->assertStringContainsString('₹171.25', $html);
        $this->assertStringContainsString('MTT/2026-27/0001', $html);
        $this->assertStringContainsString('10 Nov 2026', $html);
        $this->assertStringContainsString('Kilometre charges', $html);
        $this->assertStringContainsString('500 km', $html);
        $this->assertStringContainsString('>1 night<', $html);
        $this->assertStringContainsString('>2 days<', $html);
    }

    public function test_non_gst_bill_has_bill_title_and_no_tax_rows(): void
    {
        $html = $this->pdf()->invoiceHtml($this->nonGstInvoice());

        $this->assertStringContainsString('>BILL<', $html);
        $this->assertStringNotContainsString('TAX INVOICE', $html);
        $this->assertStringNotContainsString('CGST', $html);
        $this->assertStringNotContainsString('27ABCDE1234F1Z5', $html);
    }

    public function test_shows_amount_in_words_and_round_off(): void
    {
        $html = $this->pdf()->invoiceHtml($this->gstInvoice());

        $this->assertStringContainsString('Rupees Seven Thousand Seven Hundred Forty Three Only', $html);
        $this->assertStringContainsString('Round off', $html);
        $this->assertStringContainsString('₹7,743.00', $html);
    }

    public function test_payment_block_shows_advance_and_balance(): void
    {
        $invoice = $this->gstInvoice();
        Payment::create(['trip_id' => $invoice->trip_id, 'amount' => '2000', 'paid_on' => '2026-11-09', 'mode' => 'upi']);

        $html = $this->pdf()->invoiceHtml($invoice->fresh());

        $this->assertStringContainsString('Advance received', $html);
        $this->assertStringContainsString('₹2,000.00', $html);
        $this->assertStringContainsString('Balance due', $html);
        $this->assertStringContainsString('₹5,743.00', $html);
        $this->assertStringNotContainsString('class="stamp paid"', $html);
    }

    public function test_paid_stamp_when_fully_paid(): void
    {
        $invoice = $this->gstInvoice();
        Payment::create(['trip_id' => $invoice->trip_id, 'amount' => '7743', 'paid_on' => '2026-11-10', 'mode' => 'cash']);

        $html = $this->pdf()->invoiceHtml($invoice->fresh());

        $this->assertStringContainsString('class="stamp paid"', $html);
        $this->assertStringContainsString('Payment received', $html);
        $this->assertNull($this->pdf()->upiUri($invoice->fresh()));
    }

    public function test_cancelled_invoice_shows_cancelled_stamp(): void
    {
        $invoice = $this->gstInvoice();
        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

        $this->assertStringContainsString('class="stamp cancelled"', $this->pdf()->invoiceHtml($invoice->fresh()));
    }

    public function test_upi_uri_contains_balance_and_invoice_no(): void
    {
        $invoice = $this->gstInvoice();
        Payment::create(['trip_id' => $invoice->trip_id, 'amount' => '2000', 'paid_on' => '2026-11-09', 'mode' => 'upi']);

        $uri = $this->pdf()->upiUri($invoice->fresh());

        $this->assertStringStartsWith('upi://pay?', $uri);
        parse_str(parse_url($uri, PHP_URL_QUERY), $q);
        $this->assertSame('mahale@upi', $q['pa']);
        $this->assertSame('Mahale Tours and Travels', $q['pn']);
        $this->assertSame('5743.00', $q['am']);
        $this->assertSame('INR', $q['cu']);
        $this->assertSame('MTT/2026-27/0001', $q['tn']);
        $this->assertStringContainsString('data:image/png;base64,', $this->pdf()->invoiceHtml($invoice->fresh()));
    }

    public function test_no_qr_without_upi_id(): void
    {
        BusinessSetting::current()->update(['upi_id' => null]);

        $this->assertNull($this->pdf()->upiUri($this->gstInvoice()));
    }

    public function test_invoice_pdf_renders_and_downloads(): void
    {
        $invoice = $this->gstInvoice();
        $owner = User::factory()->create(['role' => Role::Owner]);

        $this->assertStringStartsWith('%PDF', $this->pdf()->invoice($invoice)->output());
        $this->assertSame('MTT-2026-27-0001.pdf', $this->pdf()->invoiceFilename($invoice));

        $response = $this->actingAs($owner)->get("/invoices/{$invoice->id}/pdf");
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('MTT-2026-27-0001.pdf', $response->headers->get('content-disposition'));
    }

    public function test_receipt_shows_amount_mode_and_receipt_number(): void
    {
        $trip = Trip::factory()->create();
        $payment = Payment::create([
            'trip_id' => $trip->id, 'amount' => '2500', 'paid_on' => '2026-11-08', 'mode' => 'upi', 'reference' => 'UTR999',
        ]);

        $html = $this->pdf()->receiptHtml($payment);

        $this->assertStringContainsString('RECEIPT', $html);
        $this->assertStringContainsString($payment->receiptNumber(), $html);
        $this->assertStringContainsString('₹2,500.00', $html);
        $this->assertStringContainsString('UPI', $html);
        $this->assertStringContainsString('UTR999', $html);
        $this->assertStringContainsString('Rupees Two Thousand Five Hundred Only', $html);
        $this->assertStringContainsString('08 Nov 2026', $html);
        $this->assertStringStartsWith('%PDF', $this->pdf()->receipt($payment)->output());
    }
}
