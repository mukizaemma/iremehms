<div class="bg-light rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Stock Reports & Analytics</h5>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="btn btn-primary btn-sm" style="pointer-events: none; cursor: default;" title="You are on the main stock reports screen" aria-current="page">
                        <i class="fa fa-chart-bar me-2"></i>Stock reports
                    </span>
                    <a href="{{ route('stock.daily-by-category') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-layer-group me-2"></i>Daily stock by category
                    </a>
                    <a href="{{ route('stock.opening-closing-report') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-balance-scale me-2"></i>Opening & Closing Report
                    </a>
                    <a href="{{ route('stock.location-activity-report') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-warehouse me-2"></i>Activity by location
                    </a>
                    <a href="{{ route('stock.dashboard') }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>

    <div class="d-flex justify-content-end mb-2 print-hide">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fa fa-print me-1"></i>Print
        </button>
    </div>

    <!-- Report Type Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="reportType" wire:model.live="reportType">
                            <option value="summary">Summary Report</option>
                            <option value="movements">Movements Report</option>
                            <option value="waste">Waste Report</option>
                            <option value="value">Value Report</option>
                        </select>
                        <label for="reportType">Report Type</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="departmentFilter" wire:model.live="departmentFilter">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                        <label for="departmentFilter">Department</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="itemTypeFilter" wire:model.live="itemTypeFilter">
                            <option value="">All Item Types</option>
                            @foreach($itemTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <label for="itemTypeFilter">Item Type</label>
                    </div>
                </div>
                @if(in_array($reportType, ['movements', 'waste']))
                    <div class="col-md-1">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="dateFrom" wire:model.live="dateFrom">
                            <label for="dateFrom">From</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="dateTo" wire:model.live="dateTo">
                            <label for="dateTo">To</label>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Summary Report -->
    @if($reportType === 'summary')
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3>{{ number_format($summaryData['total_items'] ?? 0) }}</h3>
                        <p class="mb-0">Total Items</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3>{{ number_format($summaryData['total_quantity'] ?? 0, 2) }}</h3>
                        <p class="mb-0">Total Quantity</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3>{{ \App\Helpers\CurrencyHelper::format($summaryData['total_value'] ?? 0) }}</h3>
                        <p class="mb-0">Total Value</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3>{{ number_format($summaryData['low_stock_count'] ?? 0) }}</h3>
                        <p class="mb-0">Low Stock Items</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="stock-summary-inventory-category" class="card border-primary">
            <div class="card-header bg-white border-primary py-3">
                <h6 class="mb-0 text-primary fw-semibold">Summary by inventory category</h6>
                <small class="text-muted">Primary stock overview — categories match daily stock sheets (Dry Goods, Beverage, etc.). Edit each stock item to change category.</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Count</th>
                                <th>Total value (at cost)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($summaryData['by_inventory_category'] ?? [] as $row)
                                <tr>
                                    <td><strong>{{ $row['name'] }}</strong></td>
                                    <td>{{ $row['count'] }}</td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($row['total_value']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted text-center py-4">No stock rows match the current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Summary by Item Type</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item Type</th>
                                <th>Count</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summaryData['by_item_type'] ?? [] as $type)
                                <tr>
                                    <td><strong>{{ $type['name'] }}</strong></td>
                                    <td>{{ $type['count'] }}</td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($type['total_value']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Movements Report -->
    @if($reportType === 'movements')
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Movements Report ({{ $dateFrom }} to {{ $dateTo }})</h6>
            </div>
            <div class="card-body">
                @if(count($movementReport) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Movement Type</th>
                                    <th>Count</th>
                                    <th>Total Quantity</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($movementReport as $movement)
                                    <tr>
                                        <td><span class="badge bg-secondary">{{ $movement['movement_type'] }}</span></td>
                                        <td>{{ $movement['count'] }}</td>
                                        <td>{{ number_format($movement['total_quantity'], 2) }}</td>
                                        <td>{{ \App\Helpers\CurrencyHelper::format($movement['total_value'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center py-4">No movements found for the selected period.</p>
                @endif
            </div>
        </div>
    @endif

    <!-- Waste Report -->
    @if($reportType === 'waste')
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">Waste Report ({{ $dateFrom }} to {{ $dateTo }})</h6>
            </div>
            <div class="card-body">
                @if(count($wasteReport) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Stock Item</th>
                                    <th>Item Type</th>
                                    <th>Quantity</th>
                                    <th>Value</th>
                                    <th>Reason</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wasteReport as $waste)
                                    <tr>
                                        <td>{{ $waste['date'] }}</td>
                                        <td><strong>{{ $waste['stock_name'] }}</strong></td>
                                        <td><span class="badge bg-info">{{ $waste['item_type'] }}</span></td>
                                        <td class="text-danger">{{ number_format($waste['quantity'], 2) }}</td>
                                        <td class="text-danger">{{ \App\Helpers\CurrencyHelper::format($waste['value']) }}</td>
                                        <td><small>{{ $waste['reason'] }}</small></td>
                                        <td>{{ $waste['user'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center py-4">No waste recorded for the selected period.</p>
                @endif
            </div>
        </div>
    @endif

    <!-- Value Report -->
    @if($reportType === 'value')
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Stock Value Report</h6>
            </div>
            <div class="card-body">
                @if(count($valueReport) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Stock Item</th>
                                    <th>Item Type</th>
                                    <th>Department</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($valueReport as $item)
                                    <tr>
                                        <td><strong>{{ $item['name'] }}</strong></td>
                                        <td><span class="badge bg-info">{{ $item['item_type'] }}</span></td>
                                        <td>{{ $item['department'] }}</td>
                                        <td>{{ number_format($item['quantity'], 2) }}</td>
                                        <td>{{ \App\Helpers\CurrencyHelper::format($item['unit_price']) }}</td>
                                        <td><strong>{{ \App\Helpers\CurrencyHelper::format($item['total_value']) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted text-center py-4">No items found.</p>
                @endif
            </div>
        </div>
    @endif
</div>

@push('styles')
<style>
    @media print {
        .print-hide { display: none !important; }
        .table { font-size: 10px; }
    }
</style>
@endpush
