<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complimentary services — {{ $hotel->name }}</title>
    <style>
        @media print { @page { size: A4 landscape; margin: 10mm; } .no-print { display: none; } }
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .text-end { text-align: right; }
        .summary { margin: 10px 0; }
    </style>
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>
    <x-hotel-document-header :hotel="$hotel" />
    <h2>Complimentary services report</h2>
    <p>{{ $dateFrom }} — {{ $dateTo }} · Printed {{ $printedAt }}</p>
    <p class="summary">
        <strong>{{ $summary['count'] }}</strong> stay(s) ·
        Room waived: <strong>{{ $currency }} {{ number_format($summary['room_waived'], 2) }}</strong> ·
        Meal waived: <strong>{{ $currency }} {{ number_format($summary['meal_waived'], 2) }}</strong> ·
        Total: <strong>{{ $currency }} {{ number_format($summary['total_waived'], 2) }}</strong>
    </p>
    <table>
        <thead>
            <tr>
                <th>Res #</th><th>Guest</th><th>Room</th><th>Stay</th><th>Services</th><th>Reason</th>
                <th class="text-end">Room waived</th><th class="text-end">Meal waived</th><th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['reservation_number'] }}</td>
                    <td>{{ $row['guest_name'] }}</td>
                    <td>{{ $row['room'] }}</td>
                    <td>{{ $row['check_in'] }} – {{ $row['check_out'] }} ({{ $row['nights'] }}n)</td>
                    <td>{{ $row['services'] }}</td>
                    <td>{{ $row['reason'] }}</td>
                    <td class="text-end">{{ $row['is_room_complimentary'] ? number_format($row['room_value_waived'], 2) : '—' }}</td>
                    <td class="text-end">{{ $row['is_meal_complimentary'] ? number_format($row['meal_value_waived'], 2) : '—' }}</td>
                    <td class="text-end">{{ number_format($row['total_value_waived'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No records.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>if (new URLSearchParams(location.search).get('auto') === '1') window.addEventListener('load', () => window.print());</script>
</body>
</html>
