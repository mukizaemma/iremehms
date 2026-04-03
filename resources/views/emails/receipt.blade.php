<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.5; }
        .receipt { max-width: 480px; margin: 0 auto; padding: 20px; }
        h2 { margin: 0 0 8px 0; font-size: 18px; }
        .meta { color: #666; font-size: 13px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: 600; }
        .text-end { text-align: right; }
        .total { font-weight: bold; text-align: right; margin-top: 12px; }
        .status { text-align: center; margin-top: 12px; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="receipt">
        @php $hotel = \App\Models\Hotel::getHotel(); @endphp
        <x-hotel-document-header :hotel="$hotel" subtitle="Receipt" />
        @if($hotel && ($hotel->email || $hotel->contact || ($hotel->receipt_momo_value && $hotel->receipt_momo_label)))
            <p class="meta" style="margin-top:12px;">
                @if($hotel->email)<strong>Email:</strong> {{ $hotel->email }}<br>@endif
                @if($hotel->contact)<strong>Phone:</strong> {{ $hotel->contact }}<br>@endif
                @if($hotel->receipt_momo_value && $hotel->receipt_momo_label)<span style="background:#b6e0fe;color:#0a58ca;padding:2px 6px;border-radius:4px;"><strong>{{ $hotel->receipt_momo_label }}:</strong> {{ $hotel->receipt_momo_value }}</span>@endif
            </p>
        @endif
        <p class="meta">
            <strong>Invoice:</strong> {{ $order->invoice->invoice_number ?? '—' }}<br>
            <strong>Table:</strong> {{ $order->table->table_number ?? '—' }}<br>
            <strong>Date:</strong> {{ \App\Helpers\HotelTimeHelper::format($order->created_at, 'Y-m-d H:i') }}<br>
            <strong>Waiter:</strong> {{ $order->waiter->name ?? 'N/A' }}
        </p>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderItems as $oi)
                    <tr>
                        <td>{{ $oi->menuItem->name ?? 'N/A' }}</td>
                        <td class="text-end">{{ $oi->quantity }}</td>
                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($oi->unit_price) }}</td>
                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($oi->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php
            $totalAmount = (float) ($order->invoice->total_amount ?? 0);
            $vatBreakdown = \App\Helpers\VatHelper::fromInclusive($totalAmount);
        @endphp
        @if($hotel->showsVatOnReceipts())
            <div class="text-end meta" style="text-align:right;">
                <p style="margin:4px 0;">Amount: {{ \App\Helpers\CurrencyHelper::format($vatBreakdown['net']) }}</p>
                <p style="margin:4px 0;">VAT ({{ (int)\App\Helpers\VatHelper::getVatRate() }}%): {{ \App\Helpers\CurrencyHelper::format($vatBreakdown['vat']) }}</p>
                @if($order->order_status === 'PAID' && $order->invoice && $order->invoice->isPaid())
                    <p style="margin:4px 0;font-weight:bold;">Total Amount paid: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</p>
                @else
                    <p style="margin:4px 0;font-weight:bold;">Total Amount to Pay: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</p>
                @endif
            </div>
        @else
            <div class="text-end" style="text-align:right;">
                @if($order->order_status === 'PAID' && $order->invoice && $order->invoice->isPaid())
                    <p class="total">Total Amount paid: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</p>
                @else
                    <p class="total">Total Amount to Pay: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</p>
                @endif
            </div>
        @endif
        @if($order->invoice && $order->invoice->payments->isNotEmpty())
            <p class="meta">
                @foreach($order->invoice->payments as $p)
                    {{ $p->payment_method }} ({{ $p->payment_status ?? 'Paid' }}): {{ \App\Helpers\CurrencyHelper::format($p->amount) }} ({{ $p->receiver->name ?? 'N/A' }})<br>
                @endforeach
            </p>
        @endif
        <p class="status">{{ $order->invoice && $order->invoice->isPaid() ? 'PAID' : 'UNPAID' }}</p>
        @if(!empty($hotel->receipt_thank_you_text))
            <p class="meta text-center">{{ $hotel->receipt_thank_you_text }}</p>
        @endif
        <p class="footer">{{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
