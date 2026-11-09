<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoicePdfService;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function __construct(private readonly InvoicePdfService $pdf) {}

    public function invoice(Invoice $invoice): Response
    {
        return $this->pdf->invoice($invoice)->stream($this->pdf->invoiceFilename($invoice));
    }

    public function receipt(Payment $payment): Response
    {
        return $this->pdf->receipt($payment)->stream($this->pdf->receiptFilename($payment));
    }
}
