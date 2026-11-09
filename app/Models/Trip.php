<?php

namespace App\Models;

use App\Billing\BillInput;
use App\Enums\PaymentStatus;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Trip extends Model
{
    use HasFactory;

    /** Rupee-valued keys stored in billing_inputs. */
    public const MONEY_KEYS = [
        'rate_per_km', 'package_amount', 'package_rate_per_day', 'extra_km_rate', 'extra_hour_rate',
        'driver_allowance_per_day', 'night_halt_per_night', 'other_taxable', 'toll', 'parking',
        'state_permit', 'discount',
    ];

    protected $fillable = [
        'customer_id', 'car_id', 'driver_id', 'pickup', 'drop', 'start_at', 'end_at',
        'trip_type', 'start_km', 'end_km', 'billing_inputs', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'trip_type' => TripType::class,
            'status' => TripStatus::class,
            'start_km' => 'integer',
            'end_km' => 'integer',
            'billing_inputs' => 'array',
        ];
    }

    public const SQL_INVOICE_TOTAL = "(select i.total from invoices i where i.trip_id = trips.id and i.status = 'active' order by i.id desc limit 1)";

    public const SQL_INVOICE_NO = "(select i.invoice_no from invoices i where i.trip_id = trips.id and i.status = 'active' order by i.id desc limit 1)";

    public const SQL_PAID_TOTAL = '(select coalesce(sum(p.amount), 0) from payments p where p.trip_id = trips.id)';

    /** Adds invoice_no, invoice_total and paid_total columns for lists and reports. */
    public function scopeWithBilling(Builder $query): Builder
    {
        return $query->select('trips.*')
            ->selectRaw(self::SQL_INVOICE_NO.' as invoice_no')
            ->selectRaw(self::SQL_INVOICE_TOTAL.' as invoice_total')
            ->selectRaw(self::SQL_PAID_TOTAL.' as paid_total');
    }

    public function scopeWherePaymentStatus(Builder $query, ?string $status): Builder
    {
        $inv = self::SQL_INVOICE_TOTAL;
        $paid = self::SQL_PAID_TOTAL;

        return match ($status) {
            'unbilled' => $query->whereRaw("{$inv} is null"),
            'unpaid' => $query->whereRaw("{$inv} is not null and {$paid} <= 0"),
            'partly_paid' => $query->whereRaw("{$inv} is not null and {$paid} > 0 and {$paid} < {$inv}"),
            'paid' => $query->whereRaw("{$inv} is not null and {$paid} >= {$inv}"),
            default => $query,
        };
    }

    public function scopeSearch(Builder $query, ?string $q): Builder
    {
        $q = trim((string) $q);
        if ($q === '') {
            return $query;
        }

        $reg = Car::normalizeRegistration($q);
        $digits = preg_replace('/\D/', '', $q);

        return $query->where(function (Builder $w) use ($q, $reg, $digits) {
            $w->whereHas('customer', function (Builder $c) use ($q, $digits) {
                $c->where('name', 'like', "%{$q}%");
                if (strlen($digits) >= 4) {
                    $c->orWhere('phone_digits', 'like', "%{$digits}%");
                }
            })
                ->orWhereHas('invoices', fn (Builder $i) => $i->where('invoice_no', 'like', "%{$q}%"));

            if (strlen($reg) >= 3) {
                $w->orWhereHas('car', fn (Builder $c) => $c->where('registration_no', 'like', "%{$reg}%"));
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function activeInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', Invoice::STATUS_ACTIVE)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function paidPaise(): int
    {
        return (int) $this->payments->sum(fn (Payment $p) => $p->amountPaise());
    }

    /** Null until the trip has an active bill; never negative. */
    public function balanceDuePaise(): ?int
    {
        $invoice = $this->activeInvoice;

        return $invoice ? max(0, $invoice->totalPaise() - $this->paidPaise()) : null;
    }

    public function excessPaise(): int
    {
        $invoice = $this->activeInvoice;

        return $invoice ? max(0, $this->paidPaise() - $invoice->totalPaise()) : 0;
    }

    public function paymentStatus(): ?PaymentStatus
    {
        if (! $this->activeInvoice) {
            return null;
        }

        $paid = $this->paidPaise();

        return match (true) {
            $paid <= 0 => PaymentStatus::Unpaid,
            $paid < $this->activeInvoice->totalPaise() => PaymentStatus::PartlyPaid,
            default => PaymentStatus::Paid,
        };
    }

    public function toBillInput(BusinessSetting $settings): BillInput
    {
        if ($this->end_at === null || $this->end_km === null || $this->start_km === null) {
            throw new InvalidArgumentException('Enter start km, end km and trip end time to calculate the bill.');
        }

        $b = $this->billing_inputs ?? [];
        $paise = fn (string $key) => Money::toPaise($b[$key] ?? null);
        $usePerDay = ($b['package_mode'] ?? 'fixed') === 'per_day';
        $tz = config('app.timezone');

        return new BillInput(
            tripType: $this->trip_type,
            startAt: CarbonImmutable::parse($this->start_at->format('Y-m-d H:i:s'), $tz),
            endAt: CarbonImmutable::parse($this->end_at->format('Y-m-d H:i:s'), $tz),
            startKm: $this->start_km,
            endKm: $this->end_km,
            ratePerKm: $paise('rate_per_km'),
            minKmPerDay: (int) $settings->min_km_per_day,
            packageAmount: $paise('package_amount'),
            packageRatePerDay: $usePerDay ? $paise('package_rate_per_day') : null,
            includedKm: (int) ($b['included_km'] ?? 0),
            includedHours: (int) ($b['included_hours'] ?? 0),
            extraKmRate: $paise('extra_km_rate'),
            extraHourRate: $paise('extra_hour_rate'),
            driverAllowancePerDay: $paise('driver_allowance_per_day'),
            nightHaltPerNight: $paise('night_halt_per_night'),
            otherTaxable: $paise('other_taxable'),
            otherTaxableLabel: trim((string) ($b['other_taxable_label'] ?? '')) ?: 'Other charges',
            toll: $paise('toll'),
            parking: $paise('parking'),
            statePermit: $paise('state_permit'),
            discount: $paise('discount'),
            gstEnabled: (bool) ($b['gst_enabled'] ?? false),
            gstRate: (float) $settings->gst_rate,
            businessState: $settings->state,
            customerState: $this->customer?->state,
        );
    }
}
