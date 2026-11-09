<table>
    <tr>
        <td style="width: 70%">
            @if ($logo)
                <img src="{{ $logo }}" style="height: 64px; float: left; margin-right: 12px" alt="">
            @endif
            <div class="ink" style="font-size: 18px; font-weight: bold">{{ $settings->name }}</div>
            <div class="muted">{!! nl2br(e($settings->address)) !!}</div>
            <div class="muted">
                @if ($settings->phone) Phone: {{ $settings->phone }} @endif
                @if ($settings->email) &nbsp;·&nbsp; {{ $settings->email }} @endif
            </div>
            @if (! empty($showGstin) && $settings->gstin)
                <div><strong>GSTIN:</strong> {{ $settings->gstin }}</div>
            @endif
        </td>
        <td class="right" style="vertical-align: top">
            <div class="title brand">{{ $docTitle }}</div>
        </td>
    </tr>
</table>
<div class="accent"></div>
