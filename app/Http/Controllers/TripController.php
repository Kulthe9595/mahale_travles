<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\TripRequest;
use App\Models\BusinessSetting;
use App\Models\Car;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ShareLog;
use App\Models\Trip;
use App\Enums\TripStatus;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\ShareService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $q = (string) $request->query('q', '');

        $trips = Trip::query()
            ->withBilling()
            ->with(['customer:id,name,phone', 'car:id,registration_no,model'])
            ->wherePaymentStatus($status)
            ->search($q)
            ->when($request->query('from'), fn ($w, $from) => $w->whereDate('start_at', '>=', $from))
            ->when($request->query('to'), fn ($w, $to) => $w->whereDate('start_at', '<=', $to))
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Trip $t) => $this->row($t));

        return Inertia::render('Trips/Index', [
            'trips' => $trips,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Trips/Form', $this->formProps(null));
    }

    public function store(TripRequest $request, InvoiceService $invoices): RedirectResponse
    {
        $trip = Trip::create($request->tripAttributes());

        return $this->afterSave($request, $trip, $invoices, 'Trip saved.');
    }

    public function show(Request $request, Trip $trip, ShareService $share, InvoicePdfService $pdf): Response
    {
        $trip->load(['customer', 'car', 'driver', 'payments', 'activeInvoice.items']);
        $invoice = $trip->activeInvoice;
        $total = $invoice?->totalPaise();
        $paid = $trip->paidPaise();

        $invoiceIds = $trip->invoices()->pluck('id');
        $paymentIds = $trip->payments->pluck('id');
        $logs = ShareLog::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->orWhereIn('payment_id', $paymentIds)
            ->latest('shared_at')
            ->limit(30)
            ->get()
            ->map(fn (ShareLog $l) => [
                'id' => $l->id,
                'what' => $l->invoice_id ? 'Bill' : 'Receipt',
                'channel' => $l->channel->label(),
                'recipient' => $l->recipient,
                'shared_at' => $l->shared_at->setTimezone(config('app.timezone'))->format('d M Y, h:i A'),
            ]);

        $settings = BusinessSetting::current();
        $hasEndDetails = $trip->end_at && $trip->end_km !== null && $trip->start_km !== null;

        return Inertia::render('Trips/Show', [
            'trip' => $trip->only(['id', 'pickup', 'drop', 'trip_type', 'status', 'start_km', 'end_km', 'notes', 'billing_inputs'])
                + [
                    'start_at' => $trip->start_at->format('d M Y, h:i A'),
                    'end_at' => $trip->end_at?->format('d M Y, h:i A'),
                    'customer' => $trip->customer->only(['id', 'name', 'phone', 'email', 'state', 'gstin']),
                    'car' => $trip->car->only(['id', 'registration_no', 'model']),
                    'driver' => $trip->driver?->only(['id', 'name', 'phone']),
                ],
            'invoice' => $invoice ? [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('d M Y'),
                'gst_enabled' => $invoice->gst_enabled,
                'tax_type' => $invoice->tax_type->value,
                'gst_rate' => $invoice->gst_rate,
                'subtotal' => $invoice->subtotal,
                'taxable_amount' => $invoice->taxable_amount,
                'cgst' => $invoice->cgst,
                'sgst' => $invoice->sgst,
                'igst' => $invoice->igst,
                'round_off' => $invoice->round_off,
                'total' => $invoice->total,
                'meta' => $invoice->meta,
                'locked' => $invoice->isLocked(),
                'share_enabled' => $invoice->share_enabled,
                'items' => $invoice->items->map->only(['id', 'description', 'qty', 'rate', 'amount', 'is_taxable']),
            ] : null,
            'cancelledInvoices' => $trip->invoices()->where('status', Invoice::STATUS_CANCELLED)
                ->orderByDesc('id')->get(['id', 'invoice_no', 'total'])
                ->map(fn (Invoice $i) => $i->only(['id', 'invoice_no', 'total'])),
            'money' => [
                'total' => $total !== null ? Money::toDecimal($total) : null,
                'paid' => Money::toDecimal($paid),
                'balance' => $total !== null ? Money::toDecimal(max(0, $total - $paid)) : null,
                'excess' => Money::toDecimal($total !== null ? max(0, $paid - $total) : 0),
                'status' => $trip->paymentStatus()?->value,
            ],
            'payments' => $trip->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_no' => $p->receiptNumber(),
                'amount' => $p->amount,
                'paid_on' => $p->paid_on->format('d M Y'),
                'mode' => $p->mode->label(),
                'reference' => $p->reference,
                'note' => $p->note,
                'receipt_filename' => $pdf->receiptFilename($p),
            ]),
            'shareLogs' => $logs,
            'share' => $invoice ? [
                'public_url' => $share->publicUrl($invoice),
                'whatsapp_url' => $share->whatsappUrl($invoice),
                'message' => $share->message($invoice),
                'filename' => $pdf->invoiceFilename($invoice),
                'email' => $trip->customer->email,
            ] : null,
            'canGenerate' => ! $invoice && $hasEndDetails,
            'canDeletePayments' => $request->user()->isOwner(),
            'gstRegistered' => (bool) $settings->gstin,
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(Trip $trip): Response
    {
        return Inertia::render('Trips/Form', $this->formProps($trip->load('customer')));
    }

    public function update(TripRequest $request, Trip $trip, InvoiceService $invoices): RedirectResponse
    {
        $trip->update($request->tripAttributes());

        return $this->afterSave($request, $trip, $invoices, 'Trip updated.');
    }

    /** "Save & create bill": generate a new bill, or refresh an unlocked one, for completed trips. */
    private function afterSave(TripRequest $request, Trip $trip, InvoiceService $invoices, string $message): RedirectResponse
    {
        if ($request->boolean('generate_invoice') && $trip->status === TripStatus::Completed) {
            $existing = $trip->activeInvoice()->first();
            if (! $existing) {
                $invoices->generate($trip);
                $message = 'Trip saved and bill created.';
            } elseif (! $existing->isLocked()) {
                $invoices->refresh($existing);
                $message = 'Trip saved and bill updated.';
            } else {
                $message .= ' The bill was already shared, so it was not changed.';
            }
        }

        return redirect("/trips/{$trip->id}")->with('success', $message);
    }

    private function row(Trip $t): array
    {
        $total = $t->invoice_total !== null ? Money::toPaise($t->invoice_total) : null;
        $paid = Money::toPaise($t->paid_total);
        $status = null;
        if ($total !== null) {
            $status = match (true) {
                $paid <= 0 => PaymentStatus::Unpaid,
                $paid < $total => PaymentStatus::PartlyPaid,
                default => PaymentStatus::Paid,
            };
        }

        return [
            'id' => $t->id,
            'start_at' => $t->start_at->format('d M Y'),
            'pickup' => $t->pickup,
            'drop' => $t->drop,
            'status' => $t->status->value,
            'customer' => $t->customer?->only(['id', 'name']),
            'car' => $t->car?->only(['id', 'registration_no', 'model']),
            'invoice_no' => $t->invoice_no,
            'total' => $total !== null ? Money::toDecimal($total) : null,
            'paid' => Money::toDecimal($paid),
            'balance' => $total !== null ? Money::toDecimal(max(0, $total - $paid)) : null,
            'payment_status' => $status?->value,
        ];
    }

    private function formProps(?Trip $trip): array
    {
        $settings = BusinessSetting::current();

        return [
            'trip' => $trip ? $trip->only([
                'id', 'customer_id', 'car_id', 'driver_id', 'pickup', 'drop', 'trip_type',
                'start_km', 'end_km', 'billing_inputs', 'status', 'notes',
            ]) + [
                'start_at' => $trip->start_at->format('Y-m-d\TH:i'),
                'end_at' => $trip->end_at?->format('Y-m-d\TH:i'),
                'customer' => $trip->customer?->only(['id', 'name', 'phone', 'state', 'gstin']),
                'locked' => (bool) $trip->activeInvoice?->isLocked(),
            ] : null,
            'cars' => Car::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $trip?->car_id))
                ->orderBy('registration_no')
                ->get(['id', 'registration_no', 'model', 'rate_per_km', 'rate_per_day']),
            'drivers' => Driver::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $trip?->driver_id))
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'settings' => [
                'min_km_per_day' => (int) $settings->min_km_per_day,
                'gst_rate' => $settings->gst_rate,
                'gst_registered' => (bool) $settings->gstin,
            ],
        ];
    }
}
