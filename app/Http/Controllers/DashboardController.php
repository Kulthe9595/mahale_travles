<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Money;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): Response
    {
        $d = $reports->dashboard();

        return Inertia::render('Dashboard', [
            'stats' => [
                'pending' => Money::toDecimal($d['pending_paise']),
                'month_revenue' => Money::toDecimal($d['month_revenue_paise']),
            ],
            'todayTrips' => $d['today_trips'],
            'upcomingTrips' => $d['upcoming_trips'],
        ]);
    }
}
