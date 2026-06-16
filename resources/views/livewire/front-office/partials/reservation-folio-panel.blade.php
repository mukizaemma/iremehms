@props([
    'folio' => [],
    'printUrl' => '#',
])

@php
    $cur = $folio['currency'] ?? 'RWF';
    $f = $folio['folio'] ?? ['total' => 0, 'paid' => 0, 'balance' => 0];
@endphp

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Folio &amp; invoices</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">View printable</a>
            <a href="{{ $printUrl }}?auto=1" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fa fa-print me-1"></i>Print folio</a>
        </div>
    </div>
    <div class="card-body py-3">
        <p class="small text-muted mb-2">Reservation charge, linked POS/extras, and payments recorded against this booking.</p>

        <h6 class="text-uppercase text-muted fw-semibold small mb-1">Accommodation stay</h6>
        @if(!empty($folio['accommodation_invoice']['lines']))
            @include('livewire.front-office.partials.accommodation-invoice-table', [
                'invoice' => $folio['accommodation_invoice'],
                'caption' => 'Room charge breakdown (qty = room-nights).',
            ])
        @else
            @foreach($folio['stay_lines'] ?? [] as $line)
                <p class="small mb-2">{{ $line['description'] }} — {{ $cur }} {{ number_format($line['amount'], 2) }}</p>
            @endforeach
        @endif

        <div class="rounded bg-light px-2 py-2 mb-3 small mt-3">
            <div class="d-flex justify-content-between"><span class="text-muted">Paid toward stay</span><span>{{ $cur }} {{ number_format($f['paid'], 2) }}</span></div>
            <div class="d-flex justify-content-between border-top mt-2 pt-2">
                <span class="text-muted">Balance (stay)</span>
                <strong class="{{ ($f['balance'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $cur }} {{ number_format($f['balance'], 2) }}</strong>
            </div>
        </div>

        @if(count($folio['invoices'] ?? []) > 0)
            <h6 class="text-uppercase text-muted fw-semibold small mb-1">Extras &amp; linked invoices</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice</th>
                            <th class="text-end">{{ $cur }}</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($folio['invoices'] as $inv)
                            <tr>
                                <td class="small">
                                    {{ $inv['invoice_number'] }}
                                    <span class="d-block text-muted" style="font-size:11px">{{ $inv['charge_type_label'] }}</span>
                                    @if(!empty($inv['context']))
                                        <span class="text-muted" style="font-size:11px">{{ $inv['context'] }}</span>
                                    @endif
                                </td>
                                <td class="text-end small">{{ number_format($inv['total_amount'], 2) }}</td>
                                <td class="small"><span class="badge bg-secondary bg-opacity-25 text-dark">{{ $inv['payment_label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="small text-muted mb-3 mb-0">No restaurant or extras posted to this reservation yet.</p>
        @endif

        <h6 class="text-uppercase text-muted fw-semibold small mb-1">Payments</h6>
        @if(count($folio['payments'] ?? []) > 0)
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Receipt</th>
                            <th class="text-end">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($folio['payments'] as $pay)
                            <tr>
                                <td class="small">
                                    {{ $pay['receipt_number'] }}
                                    <span class="d-block text-muted" style="font-size:11px">{{ $pay['received_at'] ?: '—' }} · {{ $pay['payment_method'] ?: $pay['payment_type'] ?: '—' }}</span>
                                </td>
                                <td class="text-end small">{{ number_format($pay['amount'], 2) }}</td>
                                <td class="text-end small">
                                    <a href="{{ $pay['receipt_url'] }}" target="_blank" rel="noopener" class="text-nowrap">Receipt</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="small text-muted mb-0">No payments recorded yet.</p>
        @endif
    </div>
</div>
