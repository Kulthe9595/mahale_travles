<?php

namespace App\Mail;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice, public readonly string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Bill {$this->invoice->invoice_no} from ".BusinessSetting::current()->name,
        );
    }

    public function content(): Content
    {
        $settings = BusinessSetting::current();

        return new Content(
            view: 'mail.invoice',
            with: [
                'customerName' => $this->invoice->trip->customer->name,
                'invoiceNo' => $this->invoice->invoice_no,
                'total' => Money::format($this->invoice->totalPaise()),
                'link' => $this->link,
                'business' => $settings,
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = app(InvoicePdfService::class);

        return [
            Attachment::fromData(fn () => $pdf->invoice($this->invoice)->output(), $pdf->invoiceFilename($this->invoice))
                ->withMime('application/pdf'),
        ];
    }
}
