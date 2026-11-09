<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\InvoiceSequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Continuous invoice numbers per financial year (April–March, India time). */
class InvoiceNumberService
{
    public function financialYear(CarbonImmutable $date): string
    {
        $local = $date->setTimezone(config('app.timezone'));
        $start = $local->month >= 4 ? $local->year : $local->year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    public function next(CarbonImmutable $date, ?string $prefix = null): string
    {
        $prefix ??= BusinessSetting::current()->invoice_prefix;
        $fy = $this->financialYear($date);

        $number = DB::transaction(function () use ($fy, $prefix) {
            InvoiceSequence::query()->firstOrCreate(['financial_year' => $fy, 'prefix' => $prefix]);

            $seq = InvoiceSequence::query()
                ->where(['financial_year' => $fy, 'prefix' => $prefix])
                ->lockForUpdate()
                ->firstOrFail();
            $seq->increment('last_number');

            return $seq->last_number;
        });

        return sprintf('%s/%s/%04d', $prefix, $fy, $number);
    }
}
