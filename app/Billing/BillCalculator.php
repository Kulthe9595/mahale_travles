<?php

namespace App\Billing;

use App\Enums\TaxType;
use App\Enums\TripType;
use InvalidArgumentException;

/**
 * The single source of truth for bill maths. Pure: no database, no clock.
 */
final class BillCalculator
{
    public function calculate(BillInput $in): BillResult
    {
        if ($in->endKm < $in->startKm) {
            throw new InvalidArgumentException('End km cannot be less than start km.');
        }
        if ($in->endAt->lt($in->startAt)) {
            throw new InvalidArgumentException('Trip end cannot be before trip start.');
        }

        $actualKm = $in->endKm - $in->startKm;
        $days = max(1, (int) $in->startAt->startOfDay()->diffInDays($in->endAt->startOfDay()) + 1);
        $nights = $days - 1;
        $hours = (int) ceil($in->startAt->diffInMinutes($in->endAt) / 60);

        $lines = [];
        $billableKm = $actualKm;

        if ($in->tripType === TripType::PerKm) {
            $billableKm = max($actualKm, $in->minKmPerDay * $days);
            $lines[] = new BillLine('Kilometre charges', $billableKm, $in->ratePerKm, $billableKm * $in->ratePerKm, true);
        } else {
            if ($in->packageRatePerDay !== null) {
                $lines[] = new BillLine('Package charges', $days, $in->packageRatePerDay, $days * $in->packageRatePerDay, true);
            } else {
                $lines[] = new BillLine('Package charges', 1, $in->packageAmount, $in->packageAmount, true);
            }

            $extraKm = max(0, $actualKm - $in->includedKm);
            $lines[] = new BillLine('Extra km', $extraKm, $in->extraKmRate, $extraKm * $in->extraKmRate, true);

            $extraHours = max(0, $hours - $in->includedHours);
            $lines[] = new BillLine('Extra hours', $extraHours, $in->extraHourRate, $extraHours * $in->extraHourRate, true);
        }

        $lines[] = new BillLine('Driver allowance', $days, $in->driverAllowancePerDay, $days * $in->driverAllowancePerDay, true);
        $lines[] = new BillLine('Night halt', $nights, $in->nightHaltPerNight, $nights * $in->nightHaltPerNight, true);
        $lines[] = new BillLine($in->otherTaxableLabel ?: 'Other charges', 1, $in->otherTaxable, $in->otherTaxable, true);

        $taxableBeforeDiscount = array_sum(array_map(fn (BillLine $l) => $l->amount, $lines));
        if ($in->discount > $taxableBeforeDiscount) {
            throw new InvalidArgumentException('Discount cannot be more than the chargeable amount.');
        }
        $lines[] = new BillLine('Discount', 1, -$in->discount, -$in->discount, true);

        $lines[] = new BillLine('Toll', 1, $in->toll, $in->toll, false);
        $lines[] = new BillLine('Parking', 1, $in->parking, $in->parking, false);
        $lines[] = new BillLine('State permit', 1, $in->statePermit, $in->statePermit, false);

        $lines = array_values(array_filter($lines, fn (BillLine $l) => $l->amount !== 0));

        $taxable = 0;
        $nonTaxable = 0;
        foreach ($lines as $line) {
            $line->taxable ? $taxable += $line->amount : $nonTaxable += $line->amount;
        }

        $taxType = TaxType::None;
        $cgst = $sgst = $igst = 0;
        if ($in->gstEnabled) {
            $basisPoints = (int) round($in->gstRate * 100);
            $tax = intdiv($taxable * $basisPoints + 5000, 10000);

            if ($this->isIntraState($in)) {
                $taxType = TaxType::CgstSgst;
                $cgst = intdiv($tax, 2);
                $sgst = $tax - $cgst;
            } else {
                $taxType = TaxType::Igst;
                $igst = $tax;
            }
        }

        $exact = $taxable + $nonTaxable + $cgst + $sgst + $igst;
        $rounded = intdiv($exact + 50, 100) * 100;

        return new BillResult(
            lines: $lines,
            days: $days,
            nights: $nights,
            hours: $hours,
            actualKm: $actualKm,
            billableKm: $billableKm,
            taxableAmount: $taxable,
            nonTaxableAmount: $nonTaxable,
            taxType: $taxType,
            cgst: $cgst,
            sgst: $sgst,
            igst: $igst,
            roundOff: $rounded - $exact,
            total: $rounded,
        );
    }

    private function isIntraState(BillInput $in): bool
    {
        if ($in->customerState === null || trim($in->customerState) === '') {
            return true;
        }

        return strcasecmp(trim($in->customerState), trim($in->businessState)) === 0;
    }
}
