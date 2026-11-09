<?php

namespace App\Billing;

use App\Enums\TripType;
use Carbon\CarbonImmutable;

/** Everything the calculator needs. All money values are integer paise. */
final readonly class BillInput
{
    public function __construct(
        public TripType $tripType,
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
        public int $startKm,
        public int $endKm,
        public int $ratePerKm = 0,
        public int $minKmPerDay = 0,
        public int $packageAmount = 0,
        public ?int $packageRatePerDay = null,
        public int $includedKm = 0,
        public int $includedHours = 0,
        public int $extraKmRate = 0,
        public int $extraHourRate = 0,
        public int $driverAllowancePerDay = 0,
        public int $nightHaltPerNight = 0,
        public int $otherTaxable = 0,
        public string $otherTaxableLabel = 'Other charges',
        public int $toll = 0,
        public int $parking = 0,
        public int $statePermit = 0,
        public int $discount = 0,
        public bool $gstEnabled = false,
        public float $gstRate = 5.0,
        public string $businessState = 'Maharashtra',
        public ?string $customerState = null,
    ) {}
}
