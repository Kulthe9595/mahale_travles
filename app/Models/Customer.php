<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'address', 'state', 'gstin'];

    protected static function booted(): void
    {
        static::saving(function (Customer $c) {
            $digits = preg_replace('/\D/', '', (string) $c->phone);
            $c->phone_digits = $digits !== '' ? $digits : null;
            $c->gstin = $c->gstin ? strtoupper(trim($c->gstin)) : null;
            $c->state = $c->state ? trim($c->state) : 'Maharashtra';
        });
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
