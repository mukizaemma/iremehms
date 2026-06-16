<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout — {{ $reservation->reservation_number }}</title>
    <style>
        @media print { @page { size: A4; margin: 12mm; } .no-print { display: none; } body { padding: 0; } }
        body { font-family: sans-serif; font-size: 11px; line-height: 1.35; color: #222; max-width: 640px; margin: 16px auto; padding: 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-weight: 600; }
        .text-end { text-align: right; }
        .muted { color: #555; margin: 4px 0 10px; }
        h2 { font-size: 14px; margin: 14px 0 6px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .badge-ok { background: #d1e7dd; color: #0f5132; }
        .badge-warn { background: #fff3cd; color: #664d03; }
        tfoot td { font-weight: 700; background: #fafafa; }
    </style>
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>
    <x-hotel-document-header :hotel="$hotel" />

    <h1 style="font-size: 17px; margin-bottom: 6px;">Accommodation checkout statement</h1>
    <p class="muted">
        {{ $reservation->guest_name ?? 'Guest' }}
        · {{ $reservation->reservation_number }}
        · Room {{ $reservation->roomUnits->first()?->label ?? '—' }}
        <br>
        Check-in {{ $ctx['check_in_ymd'] }}
        · Booked checkout {{ $ctx['booked_departure_ymd'] }}
        · <strong>Departure (this statement) {{ $ctx['departure_ymd'] }}</strong>
        <br>
        Nights billed: <strong>{{ $ctx['stayed_nights'] }}</strong> of {{ $ctx['booked_nights'] }} booked
        · Printed {{ $printedAt }}
    </p>

    <p class="mb-2">
        <span class="badge {{ $settled_label === 'FULLY SETTLED' ? 'badge-ok' : 'badge-warn' }}">{{ $settled_label }}</span>
    </p>

    @if(!empty($ctx['clamp_note']))
        <p class="muted" style="font-size:10px">{{ $ctx['clamp_note'] }}</p>
    @endif

    <h2>Accommodation charges</h2>
    @php $inv = $ctx['accommodation_invoice']; @endphp
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Unit price</th>
                <th class="text-end">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($inv['lines'] ?? [] as $row)
                <tr>
                    <td>{{ $row['item'] }}</td>
                    <td class="text-end">{{ (int) $row['qty'] }}</td>
                    <td class="text-end">{{ $inv['currency'] ?? 'RWF' }} {{ number_format((float) $row['unit_price'], 2) }}</td>
                    <td class="text-end">{{ $inv['currency'] ?? 'RWF' }} {{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-end">Stay folio total</td>
                <td class="text-end">{{ $inv['currency'] ?? 'RWF' }} {{ number_format((float) ($inv['total_amount'] ?? 0), 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <p class="muted" style="font-size:10px">Qty reflects room-nights for the shortened or full stay shown above.</p>

    @if(($ctx['is_proration'] ?? false) && $ctx['originally_booked_total'] != $ctx['adjusted_total_amount'])
        <p class="muted">Originally booked accommodation total {{ $inv['currency'] ?? 'RWF' }} {{ number_format($ctx['originally_booked_total'], 2) }} — adjusted for {{ $ctx['stayed_nights'] }} night(s).</p>
    @endif

    <h2>Settlement</h2>
    <table>
        <tbody>
            <tr><th>Payments recorded (hotel)</th><td class="text-end">{{ $inv['currency'] ?? 'RWF' }} {{ number_format($paid, 2) }}</td></tr>
            <tr><th>Balance due (stay folio)</th><td class="text-end">{{ $inv['currency'] ?? 'RWF' }} {{ number_format($balance, 2) }}</td></tr>
        </tbody>
    </table>
    @if($balance_signed < -0.02)
        <p class="muted">Credit/overpayment versus stay folio: {{ $inv['currency'] ?? 'RWF' }} {{ number_format(abs($balance_signed), 2) }} — handle refunds per hotel policy.</p>
    @endif

    <p class="muted no-print" style="margin-top:16px;font-size:10px">
        Confirming checkout from the Front Office saves this shortened stay &amp; prorated totals (when departing before the booked checkout date).
    </p>
    <script>if (new URLSearchParams(location.search).get('auto') === '1') window.addEventListener('load', () => window.print());</script>
</body>
</html>
