<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Http\Response;

/** Customers open their bill from the WhatsApp/email link without logging in. */
class PublicBillController extends Controller
{
    public function __invoke(string $token, InvoicePdfService $pdf): Response
    {
        $invoice = Invoice::query()
            ->where('share_token', $token)
            ->where('share_enabled', true)
            ->firstOrFail();

        return $pdf->invoice($invoice)->stream($pdf->invoiceFilename($invoice));
    }
}
