<?php

namespace App\Models;

use App\Enums\TaxType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'trip_id', 'invoice_no', 'financial_year', 'invoice_date', 'gst_enabled', 'tax_type', 'gst_rate',
        'subtotal', 'taxable_amount', 'cgst', 'sgst', 'igst', 'round_off', 'total', 'meta',
        'share_token', 'share_enabled', 'locked_at', 'status', 'revision_of_id',
    ];

    protected $hidden = ['share_token'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'immutable_date',
            'gst_enabled' => 'boolean',
            'tax_type' => TaxType::class,
            'gst_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'cgst' => 'decimal:2',
            'sgst' => 'decimal:2',
            'igst' => 'decimal:2',
            'round_off' => 'decimal:2',
            'total' => 'decimal:2',
            'meta' => 'array',
            'share_enabled' => 'boolean',
            'locked_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->share_token ??= Str::random(48);
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'revision_of_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function totalPaise(): int
    {
        return Money::toPaise($this->total);
    }
}
