<p>Dear {{ $customerName }},</p>
<p>Thank you for travelling with {{ $business->name }}.</p>
<p>Please find attached bill <strong>{{ $invoiceNo }}</strong> for <strong>{{ $total }}</strong>.
   You can also view it online: <a href="{{ $link }}">{{ $link }}</a></p>
<p>Regards,<br>{{ $business->name }}@if ($business->phone)<br>{{ $business->phone }}@endif</p>
