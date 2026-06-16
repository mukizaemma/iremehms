@props([
    'invoice',
    'caption' => null,
    'footerLabel' => 'Total amount',
    'compact' => false,
])

@php
    /** @var array $invoice */
    $lines = $invoice['lines'] ?? [];
@endphp

@if(count($lines) > 0)
    @if($caption)
        <p class="small text-muted mb-2">{{ $caption }}</p>
    @endif
    <div class="table-responsive border rounded bg-white">
        <table class="table table-sm mb-0 align-middle accommodation-invoice-receipt">
            <thead class="table-light">
                <tr>
                    <th scope="col">Item</th>
                    <th class="text-end" scope="col" style="width:5rem">Qty</th>
                    <th class="text-end" scope="col" style="width:7.5rem">Unit price</th>
                    <th class="text-end" scope="col" style="width:8rem">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $row)
                    <tr>
                        <td class="small">{{ $row['item'] }}</td>
                        <td class="text-end small text-nowrap">{{ (int) $row['qty'] }}</td>
                        <td class="text-end small text-nowrap">{{ $invoice['currency'] }} {{ number_format((float) $row['unit_price'], 2) }}</td>
                        <td class="text-end small text-nowrap">{{ $invoice['currency'] }} {{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-top fw-semibold">
                <tr>
                    <td colspan="3" class="text-end small text-uppercase text-muted">{{ $footerLabel }}</td>
                    <td class="text-end">{{ $invoice['currency'] }} {{ number_format((float) ($invoice['total_amount'] ?? 0), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @unless($compact)
        <p class="small text-muted mb-0 mt-1">
            Qty = room-nights ({{ $invoice['room_nights'] ?? '—' }}).
        </p>
    @endunless
@endif
