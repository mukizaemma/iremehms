<div class="bg-light rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Stock Dashboard</h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" wire:model.live="departmentFilter" style="max-width: 200px;">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" wire:model.live="itemTypeFilter" style="max-width: 200px;">
                        <option value="">All Item Types</option>
                        @foreach($itemTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

    <!-- Key Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="bg-white rounded d-flex align-items-center justify-content-between p-4 shadow-sm">
                <div>
                    <p class="mb-2 text-muted">Total Items</p>
                    <h3 class="mb-0">{{ number_format($totalItems) }}</h3>
                </div>
                <i class="fa fa-boxes fa-3x text-primary opacity-50"></i>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="bg-white rounded d-flex align-items-center justify-content-between p-4 shadow-sm">
                <div>
                    <p class="mb-2 text-muted">Total Stock Value</p>
                    <h3 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($totalValue) }}</h3>
                </div>
                <i class="fa fa-dollar-sign fa-3x text-success opacity-50"></i>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="bg-white rounded d-flex align-items-center justify-content-between p-4 shadow-sm">
                <div>
                    <p class="mb-2 text-muted">Low Stock Items</p>
                    <h3 class="mb-0 text-danger">{{ count($lowStockItems) }}</h3>
                </div>
                <i class="fa fa-exclamation-triangle fa-3x text-danger opacity-50"></i>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="bg-white rounded d-flex align-items-center justify-content-between p-4 shadow-sm">
                <div>
                    <p class="mb-2 text-muted">Recent Movements</p>
                    <h3 class="mb-0">{{ count($recentMovements) }}</h3>
                </div>
                <i class="fa fa-exchange-alt fa-3x text-info opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Low Stock Alerts -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0"><i class="fa fa-exclamation-triangle me-2"></i>Low Stock Alerts</h6>
                </div>
                <div class="card-body">
                    @if(count($lowStockItems) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Current</th>
                                        <th>Reorder Level</th>
                                        <th>Department</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lowStockItems as $item)
                                        <tr>
                                            <td><strong>{{ $item['name'] }}</strong></td>
                                            <td class="text-danger">{{ number_format($item['quantity'], 2) }}</td>
                                            <td>{{ number_format($item['reorder_level'], 2) }}</td>
                                            <td>{{ $item['department']['name'] ?? 'N/A' }}</td>
                                            <td>
                                                <a href="{{ route('stock.management') }}?search={{ $item['name'] }}" class="btn btn-sm btn-primary">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">No low stock items. All items are above reorder level.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Movements -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fa fa-history me-2"></i>Recent Movements</h6>
                </div>
                <div class="card-body">
                    @if(count($recentMovements) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>User</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentMovements as $movement)
                                        <tr>
                                            <td>{{ $movement['stock']['name'] ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $movement['movement_type'] === 'PURCHASE' ? 'success' : ($movement['movement_type'] === 'WASTE' ? 'danger' : 'info') }}">
                                                    {{ $movement['movement_type'] }}
                                                </span>
                                            </td>
                                            <td class="{{ $movement['quantity'] > 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $movement['quantity'] > 0 ? '+' : '' }}{{ number_format($movement['quantity'], 2) }}
                                            </td>
                                            <td>{{ $movement['user']['name'] ?? 'N/A' }}</td>
                                            <td>
                                                {{ isset($movement['business_date']) ? \Carbon\Carbon::parse($movement['business_date'])->format('M d') : (\Carbon\Carbon::parse($movement['created_at'])->format('M d')) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('stock.movements') }}" class="btn btn-sm btn-outline-info">View All Movements</a>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">No recent movements found.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Top Items by Value -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa fa-chart-line me-2"></i>Top Items by Value</h6>
                </div>
                <div class="card-body">
                    @if(count($topItems) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topItems as $item)
                                        <tr>
                                            <td><strong>{{ $item['name'] }}</strong><br><small class="text-muted">{{ $item['item_type'] }}</small></td>
                                            <td>{{ number_format($item['quantity'], 2) }}</td>
                                            <td>{{ \App\Helpers\CurrencyHelper::format($item['unit_price']) }}</td>
                                            <td><strong>{{ \App\Helpers\CurrencyHelper::format($item['total_value']) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">No items found.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Movements by Type (Last 30 Days) -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa fa-chart-pie me-2"></i>Movements by Type (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    @if(count($movementsByType) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Movement Type</th>
                                        <th>Count</th>
                                        <th>Total Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movementsByType as $type => $data)
                                        <tr>
                                            <td><span class="badge bg-secondary">{{ $type }}</span></td>
                                            <td>{{ $data['count'] }}</td>
                                            <td>{{ number_format($data['total_quantity'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center py-3">No movements in the last 30 days.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
