<?php

namespace App\Services;

use App\Enums\ShareChannel;
use App\Mail\InvoiceMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ShareLog;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

class ShareService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function publicUrl(Invoice $invoice): string
    {
        return url('/b/'.$invoice->share_token);
    }

    public function message(Invoice $invoice): string
    {
        $invoice->loadMissing('trip.customer');

        return sprintf(
            'Dear %s, your bill %s for %s from %s: %s',
            $invoice->trip->customer->name,
            $invoice->invoice_no,
            Money::format($invoice->totalPaise()),
            BusinessSetting::current()->name,
            $this->publicUrl($invoice),
        );
    }

    public function whatsappNumber(Invoice $invoice): ?string
    {
        $invoice->loadMissing('trip.customer');

        return Phone::toWhatsApp($invoice->trip->customer->phone);
    }

    public function whatsappUrl(Invoice $invoice): ?string
    {
        $number = $this->whatsappNumber($invoice);

        return $number ? "https://wa.me/{$number}?text=".rawurlencode($this->message($invoice)) : null;
    }

    public function email(Invoice $invoice, string $to): void
    {
        Mail::to($to)->send(new InvoiceMail($invoice, $this->publicUrl($invoice)));
        $this->log($invoice, ShareChannel::Email, $to);
    }

    /** Records a share; the first share of a bill locks it. */
    public function log(Invoice|Payment $target, ShareChannel $channel, ?string $recipient = null): ShareLog
    {
        $log = ShareLog::create([
            'invoice_id' => $target instanceof Invoice ? $target->id : null,
            'payment_id' => $target instanceof Payment ? $target->id : null,
            'channel' => $channel,
            'recipient' => $recipient,
            'shared_at' => CarbonImmutable::now(),
        ]);

        if ($target instanceof Invoice) {
            $this->invoices->lock($target);
        }

        return $log;
    }
}
