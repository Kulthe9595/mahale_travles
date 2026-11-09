<?php

namespace App\Services;

use App\Billing\BillCalculator;
use App\Billing\BillResult;
use App\Enums\TripStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Trip;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly InvoiceNumberService $numbers,
    ) {}

    public function generate(Trip $trip, ?int $revisionOfId = null): Invoice
    {
        return DB::transaction(function () use ($trip, $revisionOfId) {
            $locked = Trip::query()->whereKey($trip->id)->lockForUpdate()->firstOrFail();

            if ($locked->invoices()->where('status', Invoice::STATUS_ACTIVE)->exists()) {
                throw ValidationException::withMessages(['trip' => 'This trip already has a bill.']);
            }

            $settings = BusinessSetting::current();
            $result = $this->calculate($locked, $settings);
            $now = CarbonImmutable::now(config('app.timezone'));

            $invoice = Invoice::create([
                'trip_id' => $locked->id,
                'invoice_no' => $this->numbers->next($now, $settings->invoice_prefix),
                'financial_year' => $this->numbers->financialYear($now),
                'invoice_date' => $now->toDateString(),
                'status' => Invoice::STATUS_ACTIVE,
                'share_enabled' => true,
                'revision_of_id' => $revisionOfId,
            ] + $this->amounts($result, $settings, $locked));

            $this->writeItems($invoice, $result);

            if ($locked->status !== TripStatus::Completed) {
                $locked->update(['status' => TripStatus::Completed]);
            }

            return $invoice->load('items');
        });
    }

    /** Recompute an unlocked invoice from its trip; the number never changes. */
    public function refresh(Invoice $invoice): Invoice
    {
        if ($invoice->isLocked() || ! $invoice->isActive()) {
            throw ValidationException::withMessages([
                'invoice' => 'This bill has been shared and is locked. Cancel and reissue it to make changes.',
            ]);
        }

        return DB::transaction(function () use ($invoice) {
            $trip = $invoice->trip()->with('customer')->firstOrFail();
            $settings = BusinessSetting::current();
            $result = $this->calculate($trip, $settings);

            $invoice->update($this->amounts($result, $settings, $trip));
            $invoice->items()->delete();
            $this->writeItems($invoice, $result);

            return $invoice->fresh('items');
        });
    }

    public function lock(Invoice $invoice): void
    {
        if ($invoice->locked_at === null) {
            $invoice->forceFill(['locked_at' => CarbonImmutable::now()])->save();
        }
    }

    public function cancelAndReissue(Invoice $invoice): Invoice
    {
        if (! $invoice->isActive()) {
            throw ValidationException::withMessages(['invoice' => 'This bill is already cancelled.']);
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => Invoice::STATUS_CANCELLED]);

            return $this->generate($invoice->trip, $invoice->id);
        });
    }

    private function calculate(Trip $trip, BusinessSetting $settings): BillResult
    {
        $trip->loadMissing('customer');

        try {
            return $this->calculator->calculate($trip->toBillInput($settings));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['trip' => $e->getMessage()]);
        }
    }

    private function amounts(BillResult $r, BusinessSetting $settings, Trip $trip): array
    {
        return [
            'gst_enabled' => (bool) ($trip->billing_inputs['gst_enabled'] ?? false),
            'tax_type' => $r->taxType,
            'gst_rate' => ($trip->billing_inputs['gst_enabled'] ?? false) ? $settings->gst_rate : 0,
            'subtotal' => Money::toDecimal($r->subtotal()),
            'taxable_amount' => Money::toDecimal($r->taxableAmount),
            'cgst' => Money::toDecimal($r->cgst),
            'sgst' => Money::toDecimal($r->sgst),
            'igst' => Money::toDecimal($r->igst),
            'round_off' => Money::toDecimal($r->roundOff),
            'total' => Money::toDecimal($r->total),
            'meta' => [
                'days' => $r->days,
                'nights' => $r->nights,
                'hours' => $r->hours,
                'actual_km' => $r->actualKm,
                'billable_km' => $r->billableKm,
            ],
        ];
    }

    private function writeItems(Invoice $invoice, BillResult $result): void
    {
        foreach ($result->lines as $i => $line) {
            $invoice->items()->create([
                'description' => $line->description,
                'qty' => $line->qty,
                'rate' => Money::toDecimal($line->rate),
                'amount' => Money::toDecimal($line->amount),
                'is_taxable' => $line->taxable,
                'sort_order' => $i,
            ]);
        }
    }
}
