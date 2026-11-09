<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Trip;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function store(Trip $trip): RedirectResponse
    {
        $this->invoices->generate($trip);

        return redirect("/trips/{$trip->id}")->with('success', 'Bill created.');
    }

    public function refresh(Invoice $invoice): RedirectResponse
    {
        $this->invoices->refresh($invoice);

        return redirect("/trips/{$invoice->trip_id}")->with('success', 'Bill updated from trip details.');
    }

    public function reissue(Invoice $invoice): RedirectResponse
    {
        $this->invoices->cancelAndReissue($invoice);

        return redirect("/trips/{$invoice->trip_id}")->with('success', 'Old bill cancelled and a new bill issued.');
    }
}
