<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['trip_id', 'amount', 'paid_on', 'mode', 'reference', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'immutable_date',
            'mode' => PaymentMode::class,
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function receiptNumber(): string
    {
        return sprintf('RCPT-%05d', $this->id);
    }

    public function amountPaise(): int
    {
        return Money::toPaise($this->amount);
    }
}
