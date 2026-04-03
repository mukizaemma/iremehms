<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt — {{ $hotel->name ?? config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #f4f4f5;
            margin: 0;
            padding: 16px;
        }
        .receipt {
            max-width: 520px;
            margin: 0 auto;
            padding: 28px 28px 24px;
            background: #fff;
            border: 1px solid #e2e4e8;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #0d6efd;
            margin-bottom: 14px;
        }
        .header-brand { flex: 1; min-width: 0; }
        .hotel-contacts {
            font-size: 12px;
            line-height: 1.6;
            color: #374151;
            margin-top: 10px;
            width: 100%;
        }
        .hotel-contacts .contact-line {
            margin: 4px 0 0 0;
        }
        .hotel-contacts .contact-line:first-child {
            margin-top: 0;
        }
        .hotel-contacts .contact-line strong {
            color: #111;
            font-weight: 700;
            margin-right: 4px;
        }
        .hotel-contacts .address {
            margin-top: 4px;
            white-space: pre-line;
        }
        .doc-meta {
            text-align: right;
            font-size: 12px;
            color: #64748b;
        }
        .doc-meta .doc-kind {
            font-weight: 700;
            color: #0d6efd;
            font-size: 13px;
        }
        .doc-meta .doc-number {
            margin-top: 4px;
            font-family: ui-monospace, monospace;
            font-size: 11px;
            word-break: break-all;
        }
        .subtitle {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 6px;
        }
        .status {
            display: inline-block;
            margin: 8px 0 14px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
        }
        .status.ok { background: #e7f1ff; color: #0b5ed7; }
        .status.warn { background: #fdecec; color: #b02a37; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #eef0f3;
            vertical-align: top;
        }
        th {
            font-weight: 600;
            text-align: left;
            color: #374151;
            width: 38%;
        }
        td.text-end, th.text-end { text-align: right; }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin: 18px 0 6px;
        }
        .payment-table thead th {
            border-bottom: 2px solid #e2e4e8;
            font-size: 12px;
        }
        .thank-you {
            margin-top: 22px;
            padding: 16px 14px;
            border-top: 1px dashed #d1d5db;
            text-align: center;
            font-size: 13px;
            line-height: 1.55;
            color: #374151;
        }
        .footer {
            margin-top: 14px;
            color: #94a3b8;
            font-size: 11px;
            text-align: center;
        }
        .no-print { text-align: center; margin-top: 14px; }
        .no-print button {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            font-size: 13px;
            font-weight: 500;
        }
        .no-print button:hover { background: #f1f5f9; }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt {
                border: none;
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                padding: 12px;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $thankYou = trim((string) ($hotel->receipt_thank_you_text ?? ''));
    if ($thankYou === '') {
        $thankYou = __('Thank you for choosing us. We look forward to welcoming you again.');
    }
@endphp
<div class="receipt">
    <div class="header">
        <div class="header-brand">
            <x-hotel-document-header :hotel="$hotel">
                <div class="hotel-contacts">
                    @if(filled($hotel->address))
                        <div class="contact-line address"><strong>{{ __('Address') }}:</strong> {{ $hotel->address }}</div>
                    @endif
                    @if(filled($hotel->contact))
                        <div class="contact-line"><strong>{{ __('Phone') }}:</strong> {{ $hotel->contact }}</div>
                    @endif
                    @if(filled($hotel->reservation_phone) && $hotel->reservation_phone !== $hotel->contact)
                        <div class="contact-line"><strong>{{ __('Reservations') }}:</strong> {{ $hotel->reservation_phone }}</div>
                    @endif
                    @if(filled($hotel->email))
                        <div class="contact-line"><strong>{{ __('Email') }}:</strong> {{ $hotel->email }}</div>
                    @endif
                    @if(filled($hotel->fax))
                        <div class="contact-line"><strong>{{ __('Fax') }}:</strong> {{ $hotel->fax }}</div>
                    @endif
                    @if(! filled($hotel->contact) && ! filled($hotel->email) && ! filled($hotel->address) && ! filled($hotel->reservation_phone) && ! filled($hotel->fax))
                        @if(filled($hotel->reservation_contacts))
                            <div class="contact-line address"><strong>{{ __('Contact') }}:</strong> {{ $hotel->reservation_contacts }}</div>
                        @endif
                    @endif
                </div>
            </x-hotel-document-header>
        </div>
        <div class="doc-meta">
            <div class="doc-kind">{{ __('Receipt') }}</div>
            <div class="doc-number">{{ $preview ? 'PREVIEW' : ($payment->receipt_number ?? '—') }}</div>
        </div>
    </div>

    <div class="subtitle">{{ __('Hotel payment receipt') }}</div>

    <div>
        <span class="status {{ ($preview) ? 'warn' : (($receiptStatusLabel === 'FULL PAID') ? 'ok' : 'warn') }}">
            {{ $receiptStatusLabel }}
        </span>
    </div>

    <div class="section-title">{{ __('Guest & stay') }}</div>
    <table>
        <tbody>
            <tr>
                <th>{{ __('Guest') }}</th>
                <td>{{ $reservation->guest_name ?? '—' }}</td>
            </tr>
            <tr>
                <th>{{ __('Reservation #') }}</th>
                <td>{{ $reservation->reservation_number ?? '—' }}</td>
            </tr>
            <tr>
                <th>{{ __('Room') }}</th>
                <td>{{ $reservation->roomUnits->first()?->label ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">{{ __('Payment details') }}</div>
    <table class="payment-table">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Payment') }} {{ $preview ? '('.__('preview').')' : '' }}</td>
                <td class="text-end">
                    {{ $hotel->currency ?? ($payment?->currency ?? 'RWF') }}
                    {{ number_format((float) ($preview ? ($previewAmount ?? 0) : ($payment->amount ?? 0)), 2, '.', '') }}
                </td>
            </tr>
            <tr>
                <td>{{ __('Balance') }}</td>
                <td class="text-end">
                    {{ $hotel->currency ?? ($payment?->currency ?? 'RWF') }}
                    {{ number_format((float) ($preview ? ($previewBalanceDue ?? 0) : ($payment->balance_after ?? 0)), 2, '.', '') }}
                </td>
            </tr>
            @unless($preview)
                <tr>
                    <td>{{ __('Method') }}</td>
                    <td class="text-end">{{ \App\Support\PaymentCatalog::normalizeReservationMethod($payment->payment_method ?? '') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Payment status') }}</td>
                    <td class="text-end">{{ \App\Support\PaymentCatalog::normalizeStatus($payment->payment_status ?? \App\Support\PaymentCatalog::STATUS_PAID) }}</td>
                </tr>
                <tr>
                    <td>{{ __('Received by') }}</td>
                    <td class="text-end">{{ $payment->receivedBy?->name ?? '—' }}</td>
                </tr>
            @endunless
        </tbody>
    </table>

    @if(!empty($payment?->comment))
        <div class="hotel-contacts" style="margin-top: 12px;">
            <strong>{{ __('Comment') }}:</strong> {{ $payment->comment }}
        </div>
    @endif

    <div class="thank-you">
        {{ $thankYou }}
    </div>

    <div class="footer">
        {{ __('Printed') }}: {{ $printedAt?->format('Y-m-d H:i:s') }}
    </div>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>
</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
