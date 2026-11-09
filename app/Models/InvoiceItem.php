<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'description', 'qty', 'rate', 'amount', 'is_taxable', 'sort_order'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
