<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proforma {{ $proforma->proforma_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; max-width: 800px; margin: 24px auto; padding: 0 16px; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .num { text-align: right; }
        .totals { margin-top: 16px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><a href="javascript:window.print()">Print</a> · <a href="{{ url()->previous() }}">Back</a></p>

    <div style="margin-bottom:8px;">
        @if($proforma->hotel?->logo)
            <div style="margin-bottom:8px;">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($proforma->hotel->logo) }}" alt="Hotel logo" style="height:64px; width:auto;">
            </div>
        @endif
        <div>
            <h1 style="margin:0;">Proforma {{ $proforma->proforma_number }}</h1>
            <p class="muted" style="margin:2px 0 0;">{{ $proforma->hotel?->name }} · {{ $proforma->status }}</p>
        </div>
    </div>

    <p>
        @if($proforma->client_organization)<strong>{{ $proforma->client_organization }}</strong><br>@endif
        {{ $proforma->client_name }}<br>
        @if($proforma->client_email){{ $proforma->client_email }}<br>@endif
        @if($proforma->client_phone){{ $proforma->client_phone }}@endif
    </p>

    @if($proforma->event_title)
        <p><strong>Event:</strong> {{ $proforma->event_title }}</p>
    @endif
    @if($proforma->service_start_date && $proforma->service_end_date)
        <p><strong>Service period:</strong> {{ \Carbon\Carbon::parse($proforma->service_start_date)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($proforma->service_end_date)->format('M j, Y') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unity price</th>
                <th class="num">Total amount</th>
                <th>Comment</th>
            </tr>
        </thead>
        <tbody>
            @php($itemNames = \App\Support\ProformaCatalog::lineTypes())
            @foreach($proforma->lines as $line)
                <tr>
                    <td>{{ $itemNames[$line->line_type] ?? ucfirst(str_replace('_', ' ', (string) $line->line_type)) }}</td>
                    <td class="num">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="num">{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $line->line_total, 2) }}</td>
                    <td>{{ $line->description ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p>Subtotal: {{ number_format((float) $proforma->subtotal, 2) }} {{ $proforma->currency }}</p>
        @if((float) $proforma->discount_amount > 0)
            <p>Discount: −{{ number_format((float) $proforma->discount_amount, 2) }}</p>
        @endif
        @if((float) $proforma->tax_amount > 0)
            <p>Tax: {{ number_format((float) $proforma->tax_amount, 2) }}</p>
        @endif
        <p><strong>Grand total: {{ number_format((float) $proforma->grand_total, 2) }} {{ $proforma->currency }}</strong></p>
    </div>

    @if($proforma->payment_terms)
        <p><strong>Payment terms</strong></p>
        <p>{{ $proforma->payment_terms }}</p>
    @endif
    @if($proforma->notes)
        <p><strong>Notes</strong></p>
        <p>{{ $proforma->notes }}</p>
    @endif

    <script>window.onload = function() { if (new URLSearchParams(location.search).get('autoprint') === '1') { window.print(); } };</script>
</body>
</html>
