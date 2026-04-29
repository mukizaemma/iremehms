<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h5 class="mb-0">Stock Opening & Closing Report</h5>
            <p class="text-muted small mb-0">Opening, received, issued/sold, and closing by stock (base + package units where configured).</p>
        </div>
        <a href="{{ route('stock.reports') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i>Back to Stock Reports
        </a>
    </div>

    <!-- Filters -->
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
                    <label class="form-label small">View</label>
                    <select class="form-select" wire:model.live="viewMode">
                        <option value="by_stock">By stock (all items)</option>
                        <option value="by_single_item">By single item</option>
                    </select>
                </div>
                @if($viewMode === 'by_single_item')
                    <div class="col-md-3">
                        <label class="form-label small">Stock item</label>
                        <select class="form-select" wire:model.live="singleStockId">
                            <option value="">— Select item —</option>
                            @foreach($stocksForSelect as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->itemType->name ?? '' }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary" wire:click="applyPreset">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report period -->
    <p class="small text-muted mb-2">
        Report period: <strong>{{ $dateRange[0] }}</strong> to <strong>{{ $dateRange[1] }}</strong>
        · Total sales amount: <strong>{{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong>
    </p>

    <!-- Table (print-friendly section) -->
    <div class="card no-print-actions">
        <div class="card-body">
            @php $hotelDoc = \App\Models\Hotel::getHotel(); @endphp
            @if($hotelDoc)
                <div class="mb-3">
                    <x-hotel-document-header :hotel="$hotelDoc" subtitle="Stock opening &amp; closing report" />
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="mb-0">Report data</h6>
                <div class="d-flex gap-2 print-hide">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fa fa-print me-1"></i>Print
                    </button>
                    <a href="mailto:?subject={{ urlencode('Stock Opening & Closing Report ' . $dateRange[0] . ' to ' . $dateRange[1]) }}&body={{ urlencode($shareText) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-envelope me-1"></i>Email
                    </a>
                    <a href="https://wa.me/?text={{ urlencode($shareText) }}" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-whatsapp me-1"></i>WhatsApp
                    </a>
                </div>
            </div>
            @if($rows->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover report-table">
                        <thead class="table-light">
                            <tr>
                                <th>Stock / Item</th>
                                <th>Location</th>
                                <th class="text-end">Unit price</th>
                                <th class="text-end">Opening qty</th>
                                <th class="text-end">Received qty</th>
                                <th class="text-end">Issued qty</th>
                                <th class="text-end">Qty sold</th>
                                <th class="text-end">Amount (sold)</th>
                                <th class="text-end">Closing qty</th>
                                <th class="text-end">Closing value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td><strong>{{ $row['stock_name'] }}</strong></td>
                                    <td>{{ $row['location_name'] }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['unit_price']) }}</td>
                                    <td class="text-end">
                                        {{ number_format($row['opening'], 2) }} {{ $row['qty_unit'] }}
                                        @if($row['opening_packages'] !== null)
                                            <div class="small text-muted">≈ {{ number_format($row['opening_packages'], 4) }} {{ $row['package_unit'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($row['qty_received'], 2) }} {{ $row['qty_unit'] }}
                                        @if($row['qty_received_packages'] !== null)
                                            <div class="small text-muted">≈ {{ number_format($row['qty_received_packages'], 4) }} {{ $row['package_unit'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($row['qty_issued'], 2) }} {{ $row['qty_unit'] }}
                                        @if($row['qty_issued_packages'] !== null)
                                            <div class="small text-muted">≈ {{ number_format($row['qty_issued_packages'], 4) }} {{ $row['package_unit'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($row['qty_sold'], 2) }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['sold_amount']) }}</td>
                                    <td class="text-end">
                                        {{ number_format($row['closing'], 2) }} {{ $row['qty_unit'] }}
                                        @if($row['closing_packages'] !== null)
                                            <div class="small text-muted">≈ {{ number_format($row['closing_packages'], 4) }} {{ $row['package_unit'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['closing_value']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="7" class="text-end">Total amount (sold)</th>
                                <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-2 print-hide">
                    {{ $rows->links() }}
                </div>
            @else
                <p class="text-muted text-center py-4 mb-0">No data for the selected period or filters.</p>
            @endif

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
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        .print-hide, .no-print, .no-print * , .no-print-actions .btn { display: none !important; }
        .report-table { font-size: 10px; }
    }
</style>
@endpush
