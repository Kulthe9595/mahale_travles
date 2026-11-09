<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\Car;
use App\Models\Invoice;
use App\Models\Trip;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    /** Active bills dated within the range, grouped by car. Every car is listed, busiest first. */
    public function earningsByCar(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $invoices = Invoice::active()
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->with('trip:id,car_id,start_km,end_km')
            ->get();

        $byCar = $invoices->groupBy(fn (Invoice $i) => $i->trip->car_id);

        return Car::query()->orderBy('registration_no')->get()
            ->map(function (Car $car) use ($byCar) {
                $bills = $byCar->get($car->id, collect());

                return [
                    'car' => $car,
                    'trips' => $bills->count(),
                    'km' => (int) $bills->sum(fn (Invoice $i) => (int) ($i->meta['actual_km'] ?? max(0, (int) $i->trip->end_km - (int) $i->trip->start_km))),
                    'revenue_paise' => (int) $bills->sum(fn (Invoice $i) => $i->totalPaise()),
                ];
            })
            ->sortByDesc('revenue_paise')
            ->values();
    }

    public function gstSummary(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Invoice::active()
            ->where('gst_enabled', true)
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->with('trip.customer')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Invoice $i) => [
                'invoice_no' => $i->invoice_no,
                'invoice_date' => $i->invoice_date->format('d-m-Y'),
                'customer' => $i->trip->customer->name,
                'gstin' => $i->trip->customer->gstin,
                'state' => $i->trip->customer->state,
                'taxable' => $i->taxable_amount,
                'cgst' => $i->cgst,
                'sgst' => $i->sgst,
                'igst' => $i->igst,
                'total' => $i->total,
            ])
            ->values();
    }

    /** Bills with money still due, oldest first. */
    public function outstanding(): Collection
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return Trip::query()
            ->withBilling()
            ->wherePaymentStatus('unpaid')
            ->orWhere(fn ($q) => $q->wherePaymentStatus('partly_paid'))
            ->with(['customer:id,name,phone', 'activeInvoice:id,trip_id,invoice_date'])
            ->get()
            ->map(function (Trip $t) use ($today) {
                $total = Money::toPaise($t->invoice_total);
                $paid = Money::toPaise($t->paid_total);
                $date = $t->activeInvoice->invoice_date;

                return [
                    'trip_id' => $t->id,
                    'customer' => $t->customer->name,
                    'phone' => $t->customer->phone,
                    'invoice_no' => $t->invoice_no,
                    'invoice_date' => $date->format('d M Y'),
                    'total_paise' => $total,
                    'paid_paise' => $paid,
                    'balance_paise' => max(0, $total - $paid),
                    'age_days' => (int) $date->startOfDay()->diffInDays($today),
                    'sort' => $date->toDateString().'-'.str_pad((string) $t->activeInvoice->id, 10, '0', STR_PAD_LEFT),
                ];
            })
            ->sortBy('sort')
            ->values();
    }

    public function dashboard(): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        $tripRow = fn (Trip $t) => [
            'id' => $t->id,
            'time' => $t->start_at->format('d M, h:i A'),
            'customer' => $t->customer->name,
            'car' => $t->car->registration_no,
            'route' => trim(($t->pickup ?: '—').' → '.($t->drop ?: '—')),
            'status' => $t->status->value,
        ];

        return [
            'today_trips' => Trip::with(['customer', 'car'])
                ->whereDate('start_at', $now->toDateString())
                ->where('status', '!=', TripStatus::Cancelled)
                ->orderBy('start_at')->get()->map($tripRow)->all(),
            'upcoming_trips' => Trip::with(['customer', 'car'])
                ->where('start_at', '>', $now->endOfDay())
                ->where('start_at', '<=', $now->addDays(7)->endOfDay())
                ->whereIn('status', [TripStatus::Booked, TripStatus::Ongoing])
                ->orderBy('start_at')->get()->map($tripRow)->all(),
            'pending_paise' => (int) $this->outstanding()->sum('balance_paise'),
            'month_revenue_paise' => (int) Invoice::active()
                ->whereBetween('invoice_date', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
                ->get()->sum(fn (Invoice $i) => $i->totalPaise()),
        ];
    }
}
