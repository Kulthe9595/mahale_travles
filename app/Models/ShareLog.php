<?php

namespace App\Models;

use App\Enums\ShareChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLog extends Model
{
    protected $fillable = ['invoice_id', 'payment_id', 'channel', 'recipient', 'shared_at'];

    protected function casts(): array
    {
        return [
            'channel' => ShareChannel::class,
            'shared_at' => 'immutable_datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
