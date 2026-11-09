<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receiptNumber() }}</title>
    @include('pdf._styles')
</head>
<body>
@include('pdf._header', ['docTitle' => 'RECEIPT', 'showGstin' => false])

<table class="box" style="margin-top: 14px">
    <tr>
        <td style="width: 55%">
            <div class="muted small">RECEIVED FROM</div>
            <strong>{{ $customer->name }}</strong><br>
            @if ($customer->phone)Phone: {{ $customer->phone }}@endif
        </td>
        <td>
            <span class="muted">Receipt no.</span> <strong>{{ $payment->receiptNumber() }}</strong><br>
            <span class="muted">Date</span> {{ $payment->paid_on->format('d M Y') }}<br>
            <span class="muted">Mode</span> {{ $payment->mode->label() }}
            @if ($payment->reference)<br><span class="muted">Ref.</span> {{ $payment->reference }}@endif
        </td>
    </tr>
</table>

<table class="box" style="margin-top: 8px">
    <tr>
        <td>
            <div class="muted small">FOR TRIP</div>
            {{ $trip->car->registration_no }} · {{ $trip->pickup ?: '—' }} → {{ $trip->drop ?: '—' }}
            · {{ $trip->start_at->format('d M Y') }}
            @if ($payment->note)<br>{{ $payment->note }}@endif
        </td>
    </tr>
</table>

<table style="margin-top: 14px">
    <tr>
        <td>
            <div class="muted small">AMOUNT RECEIVED</div>
            <div style="font-size: 20px; font-weight: bold" class="brand">{{ $amount }}</div>
            <div>{{ $words }}</div>
        </td>
        <td class="right" style="vertical-align: bottom">
            <div>For {{ $settings->name }}</div>
            <div class="muted small" style="margin-top: 30px">Authorised signatory</div>
        </td>
    </tr>
</table>
</body>
</html>
