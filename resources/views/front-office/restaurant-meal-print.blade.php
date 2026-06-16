<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $mealLabel }} list — {{ $hotel->name }}</title>
    <style>
        @media print { @page { size: A4; margin: 12mm; } .no-print { display: none; } }
        body { font-family: sans-serif; font-size: 11px; max-width: 210mm; margin: 0 auto; padding: 12px; color: #333; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 14px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>

    <x-hotel-document-header :hotel="$hotel" />
    <h1>{{ $mealLabel }} service list</h1>
    <p class="meta">{{ $date }} · {{ count($rows) }} reservation(s) · {{ $totalCovers }} cover(s) · Printed {{ $printedAt }}</p>

    <table>
        <thead>
            <tr>
                <th>Room</th>
                <th>Guest</th>
                <th>Res. #</th>
                <th>Board</th>
                <th class="text-center">Covers</th>
                <th>Time / location</th>
                <th>Notes</th>
                <th>Check-out</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><strong>{{ $row['room'] }}</strong></td>
                    <td>{{ $row['guest_name'] }}</td>
                    <td>{{ $row['reservation_number'] }}</td>
                    <td>{{ $row['meal_plan'] }}</td>
                    <td class="text-center">{{ $row['covers'] }}</td>
                    <td>{{ $row['preferences'] }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                    <td>{{ $row['check_out'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted">No guests for this meal.</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.addEventListener('load', () => { if (new URLSearchParams(location.search).get('auto') === '1') window.print(); });</script>
</body>
</html>
