<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    @include('pdf._styles')
</head>
<body>
@if ($isCancelled)
    <div class="stamp cancelled">CANCELLED</div>
@elseif ($isPaid)
    <div class="stamp paid">PAID</div>
@endif

@include('pdf._header', ['docTitle' => $isGst ? 'TAX INVOICE' : 'BILL', 'showGstin' => $isGst])

<table class="box" style="margin-top: 12px">
    <tr>
        <td style="width: 55%">
            <div class="muted small">BILL TO</div>
            <strong>{{ $customer->name }}</strong><br>
            @if ($customer->address){!! nl2br(e($customer->address)) !!}<br>@endif
            @if ($customer->phone)Phone: {{ $customer->phone }}<br>@endif
            @if ($isGst)
                State: {{ $customer->state }}<br>
                @if ($customer->gstin)GSTIN: {{ $customer->gstin }}@endif
            @endif
        </td>
        <td>
            <table>
                <tr><td style="border:0;padding:1px 0" class="muted">Invoice no.</td><td style="border:0;padding:1px 0" class="right"><strong>{{ $invoice->invoice_no }}</strong></td></tr>
                <tr><td style="border:0;padding:1px 0" class="muted">Date</td><td style="border:0;padding:1px 0" class="right">{{ $invoice->invoice_date->format('d M Y') }}</td></tr>
                <tr><td style="border:0;padding:1px 0" class="muted">Trip</td><td style="border:0;padding:1px 0" class="right">{{ $trip->start_at->format('d M Y') }}@if ($trip->end_at && ! $trip->end_at->isSameDay($trip->start_at)) – {{ $trip->end_at->format('d M Y') }}@endif</td></tr>
                @if ($isGst)
                    <tr><td style="border:0;padding:1px 0" class="muted">SAC</td><td style="border:0;padding:1px 0" class="right">{{ $settings->sac_code }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="box" style="margin-top: 8px">
    <tr>
        <td style="width: 34%">
            <div class="muted small">VEHICLE</div>
            {{ $car->registration_no }}<br>{{ $car->model }}
            @if ($driver)<br><span class="muted">Driver:</span> {{ $driver->name }}@endif
        </td>
        <td style="width: 33%">
            <div class="muted small">ROUTE</div>
            {{ $trip->pickup ?: '—' }} → {{ $trip->drop ?: '—' }}<br>
            <span class="muted">Start:</span> {{ $trip->start_at->format('d M Y, h:i A') }}<br>
            <span class="muted">End:</span> {{ $trip->end_at?->format('d M Y, h:i A') }}
        </td>
        <td>
            <div class="muted small">KILOMETRES</div>
            <span class="muted">Start:</span> {{ number_format($trip->start_km) }} &nbsp;
            <span class="muted">End:</span> {{ number_format($trip->end_km) }}<br>
            <span class="muted">Total:</span> {{ number_format($invoice->meta['actual_km'] ?? 0) }} km
            @if (($invoice->meta['billable_km'] ?? 0) > ($invoice->meta['actual_km'] ?? 0))
                <br><span class="muted">Billed (minimum):</span> {{ number_format($invoice->meta['billable_km']) }} km
            @endif
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 12px">
    <thead>
        <tr>
            <th style="width: 5%">#</th>
            <th style="text-align: left">Description</th>
            <th class="right" style="width: 15%">Qty</th>
            <th class="right" style="width: 17%">Rate</th>
            <th class="right" style="width: 18%">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->items as $i => $item)
            @php($u = $unit($item->description, $item->qty))
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->description }}@if ($isGst && ! $item->is_taxable) <span class="muted small">(reimbursement)</span>@endif</td>
                <td class="right">{{ rtrim(rtrim($item->qty, '0'), '.') }}{{ $u ? ' '.$u : '' }}</td>
                <td class="right">{{ $fmt($item->rate) }}</td>
                <td class="right">{{ $fmt($item->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 8px">
    <tr>
        <td style="width: 52%; vertical-align: top; padding-right: 12px">
            <div class="muted small">AMOUNT IN WORDS</div>
            <div><strong>{{ $words }}</strong></div>

            @if ($payments->isNotEmpty())
                <table class="totals" style="margin-top: 10px; border: 1px solid #d9d9d9">
                    @foreach ($payments as $p)
                        <tr>
                            <td>{{ $p['label'] }} <span class="muted small">({{ $p['date'] }}, {{ $p['mode'] }})</span></td>
                            <td class="right">{{ $p['amount'] }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($qr)
                <table style="margin-top: 10px"><tr>
                    <td style="width: 92px"><img src="{{ $qr }}" style="width: 88px; height: 88px" alt=""></td>
                    <td class="small" style="vertical-align: middle">Scan to pay the balance<br>by UPI: <strong>{{ $settings->upi_id }}</strong></td>
                </tr></table>
            @endif
        </td>
        <td style="vertical-align: top">
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ $fmt($invoice->subtotal) }}</td></tr>
                @if ($isGst)
                    <tr><td class="muted">Taxable value</td><td class="right muted">{{ $fmt($invoice->taxable_amount) }}</td></tr>
                    @if ($invoice->tax_type->value === 'igst')
                        <tr><td>IGST @ {{ $fullRate }}%</td><td class="right">{{ $fmt($invoice->igst) }}</td></tr>
                    @else
                        <tr><td>CGST @ {{ $halfRate }}%</td><td class="right">{{ $fmt($invoice->cgst) }}</td></tr>
                        <tr><td>SGST @ {{ $halfRate }}%</td><td class="right">{{ $fmt($invoice->sgst) }}</td></tr>
                    @endif
                @endif
                @if ((float) $invoice->round_off !== 0.0)
                    <tr><td>Round off</td><td class="right">{{ $fmt($invoice->round_off) }}</td></tr>
                @endif
                <tr class="grand"><td>Grand Total</td><td class="right">{{ $fmt($invoice->total) }}</td></tr>
                @if ($payments->isNotEmpty())
                    <tr><td>Received</td><td class="right">{{ \App\Support\Money::format($paid) }}</td></tr>
                    @if ($excess > 0)
                        <tr><td><strong>Excess received</strong></td><td class="right"><strong>{{ \App\Support\Money::format($excess) }}</strong></td></tr>
                    @else
                        <tr><td><strong>Balance due</strong></td><td class="right"><strong>{{ \App\Support\Money::format($balance) }}</strong></td></tr>
                    @endif
                @endif
            </table>
        </td>
    </tr>
</table>

<table style="margin-top: 22px">
    <tr>
        <td style="width: 60%; vertical-align: bottom" class="small">
            @if ($settings->bank_details)
                <div class="muted">BANK DETAILS</div>
                <div>{!! nl2br(e($settings->bank_details)) !!}</div>
            @endif
            @if ($settings->terms)
                <div class="muted" style="margin-top: 6px">{!! nl2br(e($settings->terms)) !!}</div>
            @endif
        </td>
        <td class="right" style="vertical-align: bottom">
            <div style="margin-top: 36px">For {{ $settings->name }}</div>
            <div class="muted small" style="margin-top: 30px">Authorised signatory</div>
        </td>
    </tr>
</table>
</body>
</html>
