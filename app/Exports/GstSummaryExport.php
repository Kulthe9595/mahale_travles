<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GstSummaryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Invoice no.', 'Date', 'Customer', 'GSTIN', 'State', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Invoice total'];
    }

    public function map($row): array
    {
        return [
            $row['invoice_no'], $row['invoice_date'], $row['customer'], $row['gstin'] ?? '', $row['state'],
            (float) $row['taxable'], (float) $row['cgst'], (float) $row['sgst'], (float) $row['igst'], (float) $row['total'],
        ];
    }
}
