<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h5 class="mb-0">Stock activity by location</h5>
            <p class="text-muted small mb-0">In/out movements in the selected period, grouped by main stock and substocks. Package and base units when a purchase pack size is set.</p>
        </div>
        <a href="{{ route('stock.reports') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i>Back to Stock Reports
        </a>
    </div>

    <div class="card mb-4 no-print-actions">
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
                <div class="col-md-4">
                    <label class="form-label small">Stock location</label>
                    <select class="form-select" wire:model.live="stockLocationId">
                        <option value="">All locations</option>
                        @foreach($locationOptions as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }} @if($loc->is_main_location)(Main)@else(Sub)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" wire:click="applyPreset">Apply dates</button>
                </div>
            </div>
        </div>
    </div>

    <p class="small text-muted mb-3">
        Period: <strong>{{ $dateRange[0] }}</strong> to <strong>{{ $dateRange[1] }}</strong>
        @if(count($sections) > 1)
            · All locations net movement value: <strong>{{ \App\Helpers\CurrencyHelper::format($grandTotals['net_value']) }}</strong>
        @endif
    </p>

    <div class="card no-print-actions">
        <div class="card-body report-print-area">
            @php $hotelDoc = \App\Models\Hotel::getHotel(); @endphp
            @if($hotelDoc)
                <div class="mb-3">
                    <x-hotel-document-header :hotel="$hotelDoc" subtitle="Stock activity by location" />
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0">Movement detail</h6>
                <div class="d-flex gap-2 print-hide">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fa fa-print me-1"></i>Print
                    </button>
                </div>
            </div>

            @forelse($sections as $section)
                @php
                    $loc = $section['location'];
                    $isMain = $loc->is_main_location && $loc->parent_location_id === null;
                @endphp
                <div class="mb-4 page-break-inside-avoid">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 border-bottom pb-2 mb-2">
                        <div>
                            <span class="badge bg-{{ $isMain ? 'primary' : 'secondary' }} me-1">{{ $isMain ? 'Main' : 'Sub' }}</span>
                            <strong>{{ $loc->name }}</strong>
                            @if($loc->code)
                                <code class="ms-1 small">{{ $loc->code }}</code>
                            @endif
                        </div>
                        <div class="small text-muted text-md-end">
                            <span class="text-dark">Received (GRN / PURCHASE):</span>
                            {{ number_format($section['totals']['received_qty_base'] ?? 0, 2) }} base qty
                            · {{ \App\Helpers\CurrencyHelper::format($section['totals']['received_value'] ?? 0) }}
                            <span class="d-none d-md-inline"> · </span>
                            <br class="d-md-none">
                            Period value (in): {{ \App\Helpers\CurrencyHelper::format($section['totals']['in_value']) }}
                            · (out): {{ \App\Helpers\CurrencyHelper::format($section['totals']['out_value']) }}
                            · Net: <strong>{{ \App\Helpers\CurrencyHelper::format($section['totals']['net_value']) }}</strong>
                            <span class="d-none d-md-inline"> · </span>
                            <br class="d-md-none">
                            Current stock value (at cost): <strong>{{ \App\Helpers\CurrencyHelper::format($section['inventory_value']) }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-hover report-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Dir</th>
                                    <th>Item</th>
                                    <th>Item type</th>
                                    <th class="text-end">Qty (base)</th>
                                    <th class="text-end">Qty (package)</th>
                                    <th class="text-end">Value</th>
                                    <th>User</th>
                                    <th>Notes / ref.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($section['rows'] as $row)
                                    <tr>
                                        <td class="text-nowrap">{{ $row['date'] }}</td>
                                        <td class="text-nowrap">{{ $row['type'] }}</td>
                                        <td>
                                            @if($row['direction'] === 'IN')
                                                <span class="badge bg-success">IN</span>
                                            @elseif($row['direction'] === 'OUT')
                                                <span class="badge bg-danger">OUT</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><strong>{{ $row['item'] }}</strong></td>
                                        <td><span class="badge bg-info text-dark">{{ $row['item_type'] }}</span></td>
                                        <td class="text-end text-nowrap">
                                            {{ number_format($row['qty_base'], 2) }}
                                            @if($row['base_unit'])
                                                <small class="text-muted">{{ $row['base_unit'] }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @if(!empty($row['show_package']))
                                                {{ number_format($row['qty_package'], 2) }}
                                                @if($row['package_unit'])
                                                    <small class="text-muted">{{ $row['package_unit'] }}</small>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">{{ \App\Helpers\CurrencyHelper::format($row['value']) }}</td>
                                        <td class="small">{{ $row['user'] }}</td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($row['reference'], 80) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-3">No movements in this period for this location.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center py-4 mb-0">No stock locations or no data for the selected filters.</p>
            @endforelse

            @if(count($sections) > 1)
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered report-table">
                        <thead class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Grand total (all listed locations)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Received value (GRN / PURCHASE only)</td>
                                <td class="text-end" colspan="2">{{ \App\Helpers\CurrencyHelper::format($grandTotals['received_value_all'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td>Movement value in (period)</td>
                                <td class="text-end" colspan="2">{{ \App\Helpers\CurrencyHelper::format($grandTotals['in_value']) }}</td>
                            </tr>
                            <tr>
                                <td>Movement value out (period)</td>
                                <td class="text-end" colspan="2">{{ \App\Helpers\CurrencyHelper::format($grandTotals['out_value']) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Net movement value</strong></td>
                                <td class="text-end" colspan="2"><strong>{{ \App\Helpers\CurrencyHelper::format($grandTotals['net_value']) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Current inventory value at cost (all listed)</td>
                                <td class="text-end" colspan="2">{{ \App\Helpers\CurrencyHelper::format($grandTotals['inventory_value_all']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="row mt-4 no-print">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Verified by (optional)</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="verified_by_name" placeholder="Name for verification">
                </div>
                <div class="col-md-6 mt-3 mt-md-0">
                    <label class="form-label small mb-1">Approved by (optional)</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="approved_by_name" placeholder="Name for approval">
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
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        .print-hide, .no-print, .no-print * , .no-print-actions .btn, .no-print-actions { display: none !important; }
        .report-table { font-size: 9px; }
        .report-print-area { font-size: 10px; }
        .page-break-inside-avoid { page-break-inside: avoid; }
        a { color: inherit !important; text-decoration: none !important; }
    }
</style>
@endpush
