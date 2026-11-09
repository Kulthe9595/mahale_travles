<?php

namespace App\Billing;

use App\Support\Money;

final readonly class BillLine
{
    public function __construct(
        public string $description,
        public float $qty,
        public int $rate,
        public int $amount,
        public bool $taxable,
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'qty' => $this->qty,
            'rate' => Money::toDecimal($this->rate),
            'amount' => Money::toDecimal($this->amount),
            'taxable' => $this->taxable,
        ];
    }
}
