<?php

namespace App\Billing;

use App\Enums\TaxType;
use App\Support\Money;

final readonly class BillResult
{
    /** @param BillLine[] $lines */
    public function __construct(
        public array $lines,
        public int $days,
        public int $nights,
        public int $hours,
        public int $actualKm,
        public int $billableKm,
        public int $taxableAmount,
        public int $nonTaxableAmount,
        public TaxType $taxType,
        public int $cgst,
        public int $sgst,
        public int $igst,
        public int $roundOff,
        public int $total,
    ) {}

    public function subtotal(): int
    {
        return $this->taxableAmount + $this->nonTaxableAmount;
    }

    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (BillLine $l) => $l->toArray(), $this->lines),
            'days' => $this->days,
            'nights' => $this->nights,
            'hours' => $this->hours,
            'actual_km' => $this->actualKm,
            'billable_km' => $this->billableKm,
            'subtotal' => Money::toDecimal($this->subtotal()),
            'taxable_amount' => Money::toDecimal($this->taxableAmount),
            'non_taxable_amount' => Money::toDecimal($this->nonTaxableAmount),
            'tax_type' => $this->taxType->value,
            'cgst' => Money::toDecimal($this->cgst),
            'sgst' => Money::toDecimal($this->sgst),
            'igst' => Money::toDecimal($this->igst),
            'round_off' => Money::toDecimal($this->roundOff),
            'total' => Money::toDecimal($this->total),
        ];
    }
}
