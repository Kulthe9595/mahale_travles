<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $fillable = ['registration_no', 'model', 'type', 'seats', 'rate_per_km', 'rate_per_day', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate_per_km' => 'decimal:2',
            'rate_per_day' => 'decimal:2',
            'is_active' => 'boolean',
            'seats' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function normalizeRegistration(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    public function label(): string
    {
        return "{$this->registration_no} · {$this->model}";
    }
}
