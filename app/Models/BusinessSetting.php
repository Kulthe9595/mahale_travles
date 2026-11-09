<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'name', 'address', 'phone', 'email', 'logo_path', 'gstin', 'state', 'sac_code',
        'gst_rate', 'min_km_per_day', 'invoice_prefix', 'bank_details', 'upi_id', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'gst_rate' => 'decimal:2',
            'min_km_per_day' => 'integer',
        ];
    }

    /** The single settings row, created with defaults on first use. */
    public static function current(): self
    {
        $settings = static::query()->oldest('id')->first();

        if (! $settings) {
            $settings = static::query()->create([]);
            $settings->refresh();
        }

        return $settings;
    }

    /** Contact details from mahalestravels.in; only fills fields that are still empty. */
    public function fillDefaults(): self
    {
        $defaults = [
            'phone' => '+91 97656 79191',
            'email' => 'mahale.veeru1@gmail.com',
            'address' => 'Navale Colony, Nashik–Pune Highway, near Shivaji Maharaj Statue, Nashik Road, Maharashtra',
        ];

        foreach ($defaults as $field => $value) {
            if (blank($this->{$field})) {
                $this->{$field} = $value;
            }
        }

        $this->save();

        return $this;
    }
}
