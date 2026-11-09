<?php

namespace App\Http\Controllers;

use App\Exports\GstSummaryExport;
use App\Services\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }

    public function earnings(Request $request): Response
    {
        $month = $this->month($request);
        $from = CarbonImmutable::parse($month.'-01', config('app.timezone'));
        $rows = $this->reports->earningsByCar($from, $from->endOfMonth());

        return Inertia::render('Reports/Earnings', [
            'month' => $month,
            'rows' => $rows->map(fn ($r) => [
                'car' => $r['car']->registration_no.' · '.$r['car']->model,
                'trips' => $r['trips'],
                'km' => $r['km'],
                'revenue' => Money::toDecimal($r['revenue_paise']),
            ]),
            'totals' => [
                'trips' => $rows->sum('trips'),
                'km' => $rows->sum('km'),
                'revenue' => Money::toDecimal((int) $rows->sum('revenue_paise')),
            ],
        ]);
    }

    public function gst(Request $request): Response
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->gstSummary($from, $to);
        $sum = fn (string $key) => Money::toDecimal((int) $rows->sum(fn ($r) => Money::toPaise($r[$key])));

        return Inertia::render('Reports/Gst', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'totals' => [
                'taxable' => $sum('taxable'), 'cgst' => $sum('cgst'), 'sgst' => $sum('sgst'),
                'igst' => $sum('igst'), 'total' => $sum('total'),
            ],
        ]);
    }

    public function gstExport(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);

        return Excel::download(
            new GstSummaryExport($this->reports->gstSummary($from, $to)),
            "gst-summary-{$from->toDateString()}-to-{$to->toDateString()}.xlsx",
        );
    }

    public function outstanding(): Response
    {
        $rows = $this->reports->outstanding();

        return Inertia::render('Reports/Outstanding', [
            'rows' => $rows->map(fn ($r) => [
                'trip_id' => $r['trip_id'],
                'customer' => $r['customer'],
                'phone' => $r['phone'],
                'invoice_no' => $r['invoice_no'],
                'invoice_date' => $r['invoice_date'],
                'total' => Money::toDecimal($r['total_paise']),
                'paid' => Money::toDecimal($r['paid_paise']),
                'balance' => Money::toDecimal($r['balance_paise']),
                'age_days' => $r['age_days'],
            ]),
            'total' => Money::toDecimal((int) $rows->sum('balance_paise')),
        ]);
    }

    private function month(Request $request): string
    {
        $month = (string) $request->query('month');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : CarbonImmutable::now(config('app.timezone'))->format('Y-m');
    }

    /** @return array{CarbonImmutable, CarbonImmutable} defaults to the current month */
    private function range(Request $request): array
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        $from = $request->query('from') ? CarbonImmutable::parse($request->query('from'), $tz) : $now->startOfMonth();
        $to = $request->query('to') ? CarbonImmutable::parse($request->query('to'), $tz) : $now->endOfMonth();

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
