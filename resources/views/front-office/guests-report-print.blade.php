<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests Report – {{ $hotel->name }}</title>
    <style>
        @media print {
            @page { size: A4; margin: 15mm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        body { font-family: sans-serif; font-size: 11px; color: #333; max-width: 210mm; margin: 0 auto; padding: 10px; }
        .header { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 12px; }
        .header .contacts { color: #555; font-size: 10px; line-height: 1.5; margin-top: 8px; width: 100%; }
        .header .contacts > div { margin-top: 4px; }
        .header .contacts > div:first-child { margin-top: 0; }
        .header .contacts strong { color: #111; font-weight: 700; }
        .header .contacts .address { white-space: pre-line; }
        .report-title { font-size: 12px; font-weight: 600; margin-bottom: 8px; }
        .report-dates { color: #666; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; }
        .text-end { text-align: right; }
        .no-print { display: none; }

        .signature-block { margin-top: 28px; display: flex; justify-content: space-between; gap: 28px; page-break-inside: avoid; flex-wrap: wrap; }
        .sig-col { flex: 1 1 30%; font-size: 10px; }
        .sig-col .sig-heading { font-weight: 700; margin-bottom: 12px; font-size: 11px; }
        .sig-field { margin-bottom: 16px; }
        .sig-field .sig-label { color: #555; margin-bottom: 4px; }
        .sig-field .sig-line { border-bottom: 1px solid #333; min-height: 26px; padding-top: 4px; color: #111; }
    </style>
</head>
<body>
    <div class="header">
        <x-hotel-document-header :hotel="$hotel">
            <div class="contacts">
                @if(filled($hotel->address))
                    <div class="address"><strong>{{ __('Address') }}:</strong> {{ $hotel->address }}</div>
                @endif
                @if(filled($hotel->contact))
                    <div><strong>{{ __('Phone') }}:</strong> {{ $hotel->contact }}</div>
                @endif
                @if(filled($hotel->reservation_phone) && $hotel->reservation_phone !== $hotel->contact)
                    <div><strong>{{ __('Reservations') }}:</strong> {{ $hotel->reservation_phone }}</div>
                @endif
                @if(filled($hotel->email))
                    <div><strong>{{ __('Email') }}:</strong> {{ $hotel->email }}</div>
                @endif
                @if(filled($hotel->fax))
                    <div><strong>{{ __('Fax') }}:</strong> {{ $hotel->fax }}</div>
                @endif
                @if(! filled($hotel->contact) && ! filled($hotel->email) && ! filled($hotel->address) && ! filled($hotel->reservation_phone) && ! filled($hotel->fax))
                    @if(filled($hotel->reservation_contacts))
                        <div class="address"><strong>{{ __('Contact') }}:</strong> {{ $hotel->reservation_contacts }}</div>
                    @endif
                @endif
            </div>
        </x-hotel-document-header>
    </div>

    <div class="report-title">Guests Report</div>
    <div class="report-dates">Period: {{ $date_from }} @if($date_from !== $date_to) to {{ $date_to }} @endif</div>

    @if($guests->isEmpty())
        <p>No guests found for the selected date range.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Guest Name</th>
                    <th>Phone / Email</th>
                    <th>ID/Passport Number</th>
                    <th>Country</th>
                    <th>Profession</th>
                    <th>Stay Purpose</th>
                    <th>Check-in Date</th>
                    <th class="text-end">Number of Days</th>
                </tr>
            </thead>
            <tbody>
                @foreach($guests as $g)
                    <tr>
                        <td>{{ $g['guest_name'] }}</td>
                        <td style="white-space: pre-line;">{{ $g['phone_email'] }}</td>
                        <td>{{ $g['guest_id_number'] }}</td>
                        <td>{{ $g['guest_country'] }}</td>
                        <td>{{ $g['guest_profession'] }}</td>
                        <td>{{ $g['guest_stay_purpose'] }}</td>
                        <td>{{ $g['check_in_date'] }}</td>
                        <td class="text-end">{{ $g['nights'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="signature-block">
        <div class="sig-col">
            <div class="sig-heading">Prepared by</div>
            <div class="sig-field">
                <div class="sig-label">Name</div>
                <div class="sig-line">{{ $prepared_by_name ?? '' }}</div>
            </div>
            <div class="sig-field">
                <div class="sig-label">Signature</div>
                <div class="sig-line"></div>
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-heading">Verified by</div>
            <div class="sig-field">
                <div class="sig-label">Name</div>
                <div class="sig-line">{{ $verified_by_name ?? '' }}</div>
            </div>
            <div class="sig-field">
                <div class="sig-label">Signature</div>
                <div class="sig-line"></div>
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-heading">Approved by</div>
            <div class="sig-field">
                <div class="sig-label">Name</div>
                <div class="sig-line">{{ $approved_by_name ?? '' }}</div>
            </div>
            <div class="sig-field">
                <div class="sig-label">Signature</div>
                <div class="sig-line"></div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
