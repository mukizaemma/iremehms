<div class="bg-light rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Stock Movements History</h5>
                <a href="{{ route('stock.dashboard') }}" class="btn btn-secondary btn-sm">
                    <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="filter_stock" wire:model.live="filter_stock">
                            <option value="">All Items</option>
                            @foreach($stocks as $stock)
                                <option value="{{ $stock->id }}">{{ $stock->name }}</option>
                            @endforeach
                        </select>
                        <label for="filter_stock">Stock Item</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="filter_movement_type" wire:model.live="filter_movement_type">
                            <option value="">All Types</option>
                            <option value="OPENING">Opening</option>
                            <option value="PURCHASE">Purchase</option>
                            <option value="TRANSFER">Transfer</option>
                            <option value="WASTE">Waste</option>
                            <option value="ADJUST">Adjustment</option>
                            <option value="SALE">Sale</option>
                        </select>
                        <label for="filter_movement_type">Movement Type</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select" id="filter_department" wire:model.live="filter_department">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                        <label for="filter_department">Department</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <input type="date" class="form-control" id="date_from" wire:model.live="date_from">
                        <label for="date_from">From</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <input type="date" class="form-control" id="date_to" wire:model.live="date_to">
                        <label for="date_to">To</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-floating">
                        <select class="form-select" id="filter_item_type" wire:model.live="filter_item_type">
                            <option value="">All Types</option>
                            @foreach($itemTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <label for="filter_item_type">Item Type</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card">
        <div class="card-body">
            @if(count($movements) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Stock Item</th>
                                <th>Item Type</th>
                                <th>Movement Type</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>From/To</th>
                                <th>User</th>
                                <th>Reason/Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movements as $movement)
                                <tr>
                                    <td>
                                        {{ isset($movement['business_date']) ? \Carbon\Carbon::parse($movement['business_date'])->format('Y-m-d') : (\Carbon\Carbon::parse($movement['created_at'])->format('Y-m-d')) }}
                                    </td>
                                    <td><strong>{{ $movement['stock']['name'] ?? 'N/A' }}</strong></td>
                                    <td><span class="badge bg-info">{{ $movement['stock']['item_type']['name'] ?? 'N/A' }}</span></td>
                                    <td>
                                        <span class="badge bg-{{ 
                                            $movement['movement_type'] === 'PURCHASE' ? 'success' : 
                                            ($movement['movement_type'] === 'WASTE' ? 'danger' : 
                                            ($movement['movement_type'] === 'TRANSFER' ? 'info' : 'warning')) 
                                        }}">
                                            {{ $movement['movement_type'] }}
                                        </span>
                                    </td>
                                    <td class="{{ $movement['quantity'] > 0 ? 'text-success' : 'text-danger' }}">
                                        <strong>{{ $movement['quantity'] > 0 ? '+' : '' }}{{ number_format($movement['quantity'], 2) }}</strong>
                                    </td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($movement['unit_price'] ?? 0) }}</td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($movement['total_value'] ?? 0) }}</td>
                                    <td>
                                        @if($movement['movement_type'] === 'TRANSFER')
                                            <small>
                                                <strong>From:</strong> {{ $movement['from_department']['name'] ?? 'N/A' }}<br>
                                                <strong>To:</strong> {{ $movement['to_department']['name'] ?? 'N/A' }}
                                            </small>
                                        @else
                                            {{ $movement['stock']['department']['name'] ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>{{ $movement['user']['name'] ?? 'N/A' }}</td>
                                    <td>
                                        @if($movement['reason'])
                                            <small class="text-muted">{{ Str::limit($movement['reason'], 50) }}</small>
                                        @endif
                                        @if($movement['notes'])
                                            <br><small class="text-muted">{{ Str::limit($movement['notes'], 50) }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted text-center py-4">No movements found matching your filters.</p>
            @endif
        </div>
    </div>
</div>
