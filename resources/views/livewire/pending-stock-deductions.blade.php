<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <h5 class="mb-3">Sales not in stock (pending deductions)</h5>
                <p class="text-muted small mb-4">
                    These are items sold at POS when stock was insufficient or when the hotel allows selling without deducting stock. Apply when stock is available, or write off.
                </p>

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

                <div class="d-flex gap-2 mb-3">
                    <select class="form-select form-select-sm w-auto" wire:model.live="filter_status">
                        <option value="PENDING">Pending</option>
                        <option value="DEDUCTED">Deducted</option>
                        <option value="WRITTEN_OFF">Written off</option>
                        <option value="">All</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Menu item</th>
                                <th>Stock item</th>
                                <th class="text-end">Required</th>
                                <th class="text-end">Had at sale</th>
                                <th>Date</th>
                                <th>Status</th>
                                @if($filter_status === 'PENDING' || $filter_status === '')
                                    <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendings as $p)
                                <tr>
                                    <td>#{{ $p->order_id }}</td>
                                    <td>{{ $p->orderItem->menuItem->name ?? '—' }}</td>
                                    <td>{{ $p->stock->name ?? '—' }} <span class="text-muted">({{ $p->stock->qty_unit ?? $p->stock->unit ?? 'unit' }})</span></td>
                                    <td class="text-end">{{ number_format($p->quantity_required, 2) }}</td>
                                    <td class="text-end">{{ number_format($p->quantity_available_at_sale, 2) }}</td>
                                    <td>{{ $p->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $p->status === 'PENDING' ? 'warning' : ($p->status === 'DEDUCTED' ? 'success' : 'secondary') }}">{{ $p->status }}</span>
                                    </td>
                                    @if($filter_status === 'PENDING' || $filter_status === '')
                                        <td>
                                            @if($p->status === 'PENDING')
                                                <button type="button" class="btn btn-sm btn-outline-success me-1" wire:click="applyDeduction({{ $p->id }})">Apply</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="writeOff({{ $p->id }})" wire:confirm="Write off this pending deduction?">Write off</button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($filter_status === 'PENDING' || $filter_status === '') ? 8 : 7 }}" class="text-muted text-center py-4">No pending stock deductions.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-3">
                    {{ $pendings->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
