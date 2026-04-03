<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <h5 class="mb-0">Restaurant Tables</h5>
                        <p class="text-muted small mb-0">Manage dine-in tables. Assign tables to orders.</p>
                    </div>
                    @if($canManageTables)
                        <div class="flex-shrink-0">
                            <button class="btn btn-primary" wire:click="openForm()">
                                <i class="fa fa-plus me-2"></i>Add Table
                            </button>
                        </div>
                    @endif
                </div>
                @include('livewire.pos.partials.pos-quick-links', ['active' => ''])

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        @if(count($tables) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Table #</th>
                                            <th>Capacity</th>
                                            <th>Status</th>
                                            <th>Order</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tables as $t)
                                            @php
                                                $isOccupied = $t['is_occupied'] ?? false;
                                            @endphp
                                            <tr @if($isOccupied && $t['active_order_id']) onclick="window.location='{{ route('pos.orders', ['order' => $t['active_order_id']]) }}'" style="cursor: pointer;" @endif>
                                                <td><strong>{{ $t['table_number'] }}</strong></td>
                                                <td>{{ $t['capacity'] ?? '—' }}</td>
                                                <td>
                                                    @if($isOccupied)
                                                        <span class="badge bg-warning text-dark">Occupied</span>
                                                    @elseif($t['is_active'])
                                                        <span class="badge bg-success">Available</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isOccupied && $t['active_order_id'])
                                                        <div class="small">
                                                            <div>Order #{{ $t['active_order_id'] ?? '—' }} ({{ $t['active_order_status'] ?? 'N/A' }})</div>
                                                            <div>
                                                                Waiter: {{ $t['active_order_waiter'] ?? '—' }}
                                                                @if(!empty($t['active_order_total']))
                                                                    · {{ \App\Helpers\CurrencyHelper::format($t['active_order_total']) }}
                                                                @endif
                                                                @if(!empty($t['active_order_time']))
                                                                    · {{ $t['active_order_time'] }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">No active order</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isOccupied && $t['active_order_id'])
                                                        <a href="{{ route('pos.orders', ['order' => $t['active_order_id']]) }}" class="btn btn-sm btn-outline-primary me-1" title="Open order" wire:navigate>
                                                            Open
                                                        </a>
                                                    @endif
                                                    @if($canManageTables)
                                                        <button class="btn btn-sm btn-primary me-1" wire:click="openForm({{ $t['id'] }})" title="Edit"><i class="fa fa-edit"></i></button>
                                                        <button class="btn btn-sm btn-danger" wire:click="delete({{ $t['id'] }})" wire:confirm="Delete this table?" title="Delete"><i class="fa fa-trash"></i></button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No tables yet. Add tables for dine-in orders.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($showForm && $canManageTables)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Edit' : 'New' }} Table</h5>
                        <button type="button" class="btn-close" wire:click="closeForm"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="table_number" wire:model.defer="table_number" placeholder="e.g. T1" required>
                                <label for="table_number">Table number</label>
                            </div>
                                @error('table_number')
                                    <div class="alert alert-danger py-1 mb-3">{{ $message }}</div>
                                @enderror
                            <div class="form-floating mb-3">
                                <input type="number" min="1" class="form-control" id="capacity" wire:model.defer="capacity" placeholder="Optional">
                                <label for="capacity">Capacity (optional)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
