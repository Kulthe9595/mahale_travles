<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;

/** Renders bills and advance receipts on demand, so payments shown are always current. */
class InvoicePdfService
{
    public function invoice(Invoice $invoice): DomPdf
    {
        return Pdf::loadHTML($this->invoiceHtml($invoice))->setPaper('a4');
    }

    public function receipt(Payment $payment): DomPdf
    {
        return Pdf::loadHTML($this->receiptHtml($payment))->setPaper('a5', 'landscape');
    }

    public function invoiceHtml(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'trip.customer', 'trip.car', 'trip.driver', 'trip.payments', 'trip.activeInvoice']);
        $trip = $invoice->trip;
        $settings = BusinessSetting::current();
        $total = $invoice->totalPaise();

        $payments = $trip->payments->map(fn (Payment $p) => [
            'label' => $p->paid_on->lt($invoice->invoice_date) ? 'Advance received' : 'Payment received',
            'date' => $p->paid_on->format('d M Y'),
            'mode' => $p->mode->label(),
            'amount' => Money::format($p->amountPaise()),
        ]);
        $paid = $trip->paidPaise();
        $upi = $this->upiUri($invoice);

        $snap = $this->snapshotFor($invoice);

        return view('pdf.invoice', [
            'invoice' => $invoice,
            'trip' => $snap['trip'],
            'car' => $snap['car'],
            'driver' => $snap['driver'],
            'customer' => $snap['customer'],
            'settings' => $settings,
            'logo' => $this->logoDataUri($settings),
            'isGst' => $invoice->gst_enabled,
            'halfRate' => rtrim(rtrim(number_format((float) $invoice->gst_rate / 2, 2), '0'), '.'),
            'fullRate' => rtrim(rtrim(number_format((float) $invoice->gst_rate, 2), '0'), '.'),
            'payments' => $payments,
            'paid' => $paid,
            'balance' => max(0, $total - $paid),
            'excess' => max(0, $paid - $total),
            'isPaid' => $invoice->isActive() && $paid >= $total && $total > 0,
            'isCancelled' => ! $invoice->isActive(),
            'qr' => $upi ? $this->qrDataUri($upi) : null,
            'fmt' => fn ($decimal) => Money::format(Money::toPaise($decimal)),
            'words' => Money::inWords($total),
            'unit' => fn (string $d, $qty) => $this->unitFor($d, (float) $qty),
        ])->render();
    }

    /**
     * The bill-to, vehicle and trip details as they were when the bill was issued.
     * Bills made before snapshots existed fall back to the live records.
     */
    private function snapshotFor(Invoice $invoice): array
    {
        $tz = config('app.timezone');
        $snap = $invoice->meta['snapshot'] ?? null;
        $trip = $invoice->trip;

        if (! $snap) {
            $snap = [
                'customer' => $trip->customer->only(['name', 'phone', 'email', 'address', 'state', 'gstin']),
                'car' => $trip->car->only(['registration_no', 'model']),
                'driver' => $trip->driver?->only(['name', 'phone']),
                'trip' => $trip->only(['pickup', 'drop', 'start_km', 'end_km']) + [
                    'start_at' => $trip->start_at?->format('Y-m-d H:i:s'),
                    'end_at' => $trip->end_at?->format('Y-m-d H:i:s'),
                ],
            ];
        }

        $t = $snap['trip'];
        $t['start_at'] = $t['start_at'] ? \Carbon\CarbonImmutable::parse($t['start_at'], $tz) : null;
        $t['end_at'] = $t['end_at'] ? \Carbon\CarbonImmutable::parse($t['end_at'], $tz) : null;

        return [
            'customer' => (object) $snap['customer'],
            'car' => (object) $snap['car'],
            'driver' => $snap['driver'] ? (object) $snap['driver'] : null,
            'trip' => (object) $t,
        ];
    }

    public function receiptHtml(Payment $payment): string
    {
        $payment->loadMissing(['trip.customer', 'trip.car']);
        $settings = BusinessSetting::current();

        return view('pdf.receipt', [
            'payment' => $payment,
            'trip' => $payment->trip,
            'customer' => $payment->trip->customer,
            'settings' => $settings,
            'logo' => $this->logoDataUri($settings),
            'amount' => Money::format($payment->amountPaise()),
            'words' => Money::inWords($payment->amountPaise()),
        ])->render();
    }

    public function invoiceFilename(Invoice $invoice): string
    {
        return str_replace('/', '-', $invoice->invoice_no).'.pdf';
    }

    public function receiptFilename(Payment $payment): string
    {
        return $payment->receiptNumber().'.pdf';
    }

    /** UPI deep link for the balance due; null when nothing is due or no UPI id is set. */
    public function upiUri(Invoice $invoice): ?string
    {
        $invoice->loadMissing(['trip.payments']);
        $settings = BusinessSetting::current();
        $balance = max(0, $invoice->totalPaise() - $invoice->trip->paidPaise());

        if (! $settings->upi_id || $balance === 0 || ! $invoice->isActive()) {
            return null;
        }

        return 'upi://pay?'.http_build_query([
            'pa' => $settings->upi_id,
            'pn' => $settings->name,
            'am' => Money::toDecimal($balance),
            'cu' => 'INR',
            'tn' => $invoice->invoice_no,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function qrDataUri(string $data): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 4,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($data);
    }

    private function logoDataUri(BusinessSetting $settings): ?string
    {
        if (! $settings->logo_path || ! Storage::disk('public')->exists($settings->logo_path)) {
            return null;
        }

        $disk = Storage::disk('public');

        return 'data:'.$disk->mimeType($settings->logo_path).';base64,'.base64_encode($disk->get($settings->logo_path));
    }

    private function unitFor(string $description, float $qty): string
    {
        $one = $qty == 1.0;

        return match ($description) {
            'Kilometre charges', 'Extra km' => 'km',
            'Extra hours' => $one ? 'hr' : 'hrs',
            'Driver allowance' => $one ? 'day' : 'days',
            'Night halt' => $one ? 'night' : 'nights',
            default => '',
        };
    }
}
