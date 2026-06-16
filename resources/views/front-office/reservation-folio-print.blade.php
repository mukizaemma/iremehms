<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Folio — {{ $reservation->reservation_number }}</title>
    <style>
        @media print { @page { size: A4; margin: 12mm; } .no-print { display: none; } }
        body { font-family: sans-serif; font-size: 11px; line-height: 1.35; color: #222; max-width: 720px; margin: 16px auto; padding: 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; font-weight: 600; }
        .text-end { text-align: right; }
        .muted { color: #555; margin: 4px 0 12px; }
        h2 { font-size: 16px; margin: 16px 0 4px; }
        .total-row td { font-weight: 700; border-top: 2px solid #999; background: #fafafa; }
        a { color: #06c; }
    </style>
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>
    <x-hotel-document-header :hotel="$hotel" />

    <h1 style="font-size: 18px; margin-bottom: 4px;">Guest folio summary</h1>
    <p class="muted">
        {{ $reservation->guest_name ?? 'Guest' }}
        · {{ $reservation->reservation_number }}
        · {{ $reservation->check_in_date->format('d M Y') }} – {{ $reservation->check_out_date->format('d M Y') }}
        ({{ $folio['nights'] }} night{{ $folio['nights'] === 1 ? '' : 's' }})
        <br>
        Printed {{ $printedAt }} · Status: {{ str_replace('_', ' ', ucfirst(strtolower((string) $reservation->status))) }}
        @php $rooms = $reservation->roomUnits->pluck('label')->join(', ') @endphp
        @if($rooms !== '') · Room: {{ $rooms }} @endif
    </p>

    <h2>Accommodation (stay)</h2>
    @if(!empty($folio['accommodation_invoice']['lines']))
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
                @foreach($folio['accommodation_invoice']['lines'] as $row)
                    <tr>
                        <td>{{ $row['item'] }}</td>
                        <td class="text-end">{{ (int) $row['qty'] }}</td>
                        <td class="text-end">{{ $folio['currency'] }} {{ number_format((float) $row['unit_price'], 2) }}</td>
                        <td class="text-end">{{ $folio['currency'] }} {{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-end">Total amount</td>
                    <td class="text-end">{{ $folio['currency'] }} {{ number_format((float) $folio['accommodation_invoice']['total_amount'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
        <p class="muted" style="font-size:10px;margin-top:6px">Qty = room-nights ({{ $folio['accommodation_invoice']['nights'] }} night(s) × {{ $folio['accommodation_invoice']['rooms'] }} room(s)).</p>
    @else
        <table>
            <thead>
                <tr><th>Description</th><th class="text-end">{{ $folio['currency'] }}</th></tr>
            </thead>
            <tbody>
                @foreach($folio['stay_lines'] ?? [] as $line)
                    <tr>
                        <td>
                            {{ $line['description'] }}
                            @if(!empty($line['detail']))
                                <br><span class="muted" style="font-size:10px">{{ $line['detail'] }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($line['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top:12px">
        Folio total: <strong>{{ $folio['currency'] }} {{ number_format($folio['folio']['total'], 2) }}</strong>
        · Paid: {{ $folio['currency'] }} {{ number_format($folio['folio']['paid'], 2) }}
        · Balance due: {{ $folio['currency'] }} {{ number_format($folio['folio']['balance'], 2) }}
    </p>

    <h2>Linked invoices (extras)</h2>
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Type</th>
                <th class="text-end">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($folio['invoices'] as $inv)
                <tr>
                    <td>
                        {{ $inv['invoice_number'] }}
                        @if(!empty($inv['context']))
                            <br><span class="muted">{{ $inv['context'] }}</span>
                        @endif
                    </td>
                    <td>{{ $inv['charge_type_label'] }}</td>
                    <td class="text-end">{{ number_format($inv['total_amount'], 2) }}</td>
                    <td>{{ $inv['payment_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No POS or extras posted to this reservation.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Payments received (front office)</h2>
    <table>
        <thead>
            <tr>
                <th>Receipt</th>
                <th>Received</th>
                <th>Method</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Balance after</th>
            </tr>
        </thead>
        <tbody>
            @forelse($folio['payments'] as $pay)
                <tr>
                    <td>{{ $pay['receipt_number'] }}</td>
                    <td>{{ $pay['received_at'] ?: '—' }}<br><span class="muted">{{ $pay['received_by'] }}</span></td>
                    <td>{{ $pay['payment_method'] ?: $pay['payment_type'] ?: '—' }}</td>
                    <td class="text-end">{{ number_format($pay['amount'], 2) }}</td>
                    <td class="text-end">{{ number_format($pay['balance_after'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No recorded payments yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted no-print" style="margin-top:20px">Open individual receipts from the reservation details page when needed.</p>

    <script>if (new URLSearchParams(location.search).get('auto') === '1') window.addEventListener('load', () => window.print());</script>
</body>
</html>
