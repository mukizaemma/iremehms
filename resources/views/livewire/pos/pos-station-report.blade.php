<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div>
            <h5 class="mb-0">{{ $stationName }} — Report</h5>
            <p class="text-muted small mb-0">Items prepared/provided, by waiter and amount. Filter by date.</p>
        </div>
        <a href="{{ route('pos.station', ['station' => $station]) }}" class="btn btn-secondary btn-sm flex-shrink-0">
            <i class="fa fa-arrow-left me-2"></i>Back to {{ $stationName }} display
        </a>
    </div>
    @include('livewire.pos.partials.pos-quick-links', ['active' => ''])

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Period</label>
                    <select class="form-select" wire:model.live="datePreset">
                        @foreach($datePresetOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($datePreset === 'range')
                    <div class="col-md-2">
                        <label class="form-label small">From</label>
                        <input type="date" class="form-control" wire:model.live="dateFrom">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">To</label>
                        <input type="date" class="form-control" wire:model.live="dateTo">
                    </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label small">Station</label>
                    <select class="form-select" wire:model.live="station">
                        @foreach($preparationStations as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary" wire:click="applyPreset">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <p class="small text-muted mb-2">
        Period: <strong>{{ $dateRange[0] }}</strong> to <strong>{{ $dateRange[1] }}</strong>
        · Total amount: <strong>{{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong>
    </p>

    <div class="card">
        <div class="card-body">
            @php $hotelDoc = \App\Models\Hotel::getHotel(); @endphp
            @if($hotelDoc)
                <div class="mb-3">
                    <x-hotel-document-header :hotel="$hotelDoc" :subtitle="$stationName.' — report'" />
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0">Report data</h6>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fa fa-print me-1"></i>Print
                    </button>
                    <a href="mailto:?subject={{ urlencode($stationName . ' Report ' . $dateRange[0] . ' to ' . $dateRange[1]) }}&body={{ urlencode($shareText) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-envelope me-1"></i>Email
                    </a>
                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-whatsapp me-1"></i>WhatsApp
                    </a>
                </div>
            </div>
            @if($rows->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Order #</th>
                                <th>Table</th>
                                <th>Waiter</th>
                                <th>Menu item</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit price</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $item)
                                <tr>
                                    <td>{{ $item->created_at->format('H:i') }}</td>
                                    <td>{{ $item->order_id }}</td>
                                    <td>{{ $item->order && $item->order->table ? $item->order->table->table_number : '—' }}</td>
                                    <td>{{ $item->order && $item->order->waiter ? $item->order->waiter->name : '—' }}</td>
                                    <td>{{ $item->menuItem->name ?? 'N/A' }}</td>
                                    <td class="text-end">{{ number_format($item->quantity, 0) }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($item->unit_price) }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($item->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="7" class="text-end">Total amount</th>
                                <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-2">{{ $rows->links() }}</div>
            @else
                <p class="text-muted text-center py-4 mb-0">No data for the selected period.</p>
            @endif
        </div>
    </div>

    {{-- Signature footer --}}
    <div class="row mt-4 no-print">
        <div class="col-md-6">
            <label class="form-label small mb-1">Verified by (optional)</label>
            <input type="text" class="form-control form-control-sm" wire:model.defer="verified_by_name" placeholder="Name for verification/signature">
        </div>
        <div class="col-md-6 mt-3 mt-md-0">
            <label class="form-label small mb-1">Approved by (optional)</label>
            <input type="text" class="form-control form-control-sm" wire:model.defer="approved_by_name" placeholder="Name for approval/signature">
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="small text-muted mb-1">Prepared by</div>
            <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                {{ Auth::user()?->name ?? '' }}
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="small text-muted mb-1">Verified by</div>
            <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                {{ $verified_by_name !== '' ? $verified_by_name : '________________________' }}
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="small text-muted mb-1">Approved by</div>
            <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                {{ $approved_by_name !== '' ? $approved_by_name : '________________________' }}
            </div>
        </div>
    </div>

    <style>
    @media print { .btn, .card-body .d-flex.gap-2, .no-print, .no-print * { display: none !important; } }
    </style>
</div>
