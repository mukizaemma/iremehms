<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-0">Void requests</h5>
                    <p class="text-muted small mb-0">
                        @if($canApproveVoid)
                            All item void requests from waiters and other users. You can approve or reject.
                        @else
                            Your void requests. Only managers or authorized users can approve or reject.
                        @endif
                    </p>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => 'void-requests'])
                </div>

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

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small">Status</label>
                                <select class="form-select form-select-sm" wire:model.live="status">
                                    <option value="all">All</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            @if($canApproveVoid)
                            <div class="col-md-3">
                                <label class="form-label small">Waiter</label>
                                <select class="form-select form-select-sm" wire:model.live="waiter_id">
                                    <option value="">All</option>
                                    @foreach($waiters as $w)
                                        <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->role->name ?? 'No role' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 2rem;"></th>
                                <th>Date</th>
                                <th>Order / Table</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>Requested by</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Approved / resolved by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $req)
                                @php
                                    $item = $req->orderItem;
                                    $order = $item?->order;
                                    $isPending = $req->status === 'pending';
                                    $isExpanded = $expandedRequestId === $req->id;
                                @endphp
                                <tr role="button"
                                    class="{{ $isExpanded ? 'table-primary' : '' }}"
                                    style="cursor: {{ $canApproveVoid && $isPending ? 'pointer' : 'default' }};"
                                    @if($canApproveVoid && $isPending) wire:click="toggleExpand({{ $req->id }})" @endif>
                                    <td>
                                        @if($isPending && $canApproveVoid)
                                            <i class="fa fa-chevron-{{ $isExpanded ? 'down' : 'right' }} text-muted small"></i>
                                        @endif
                                    </td>
                                    <td>{{ $req->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($order)
                                            <a href="{{ route('pos.orders', ['order' => $order->id]) }}" wire:click.stop>#{{ $order->id }}</a>
                                            @if($order->table)
                                                <div class="small text-muted">Table {{ $order->table->table_number }}</div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $item?->menuItem->name ?? 'Item' }}</td>
                                    <td class="text-end">{{ (int) ($item->quantity ?? 0) }}</td>
                                    <td>
                                        {{ $req->requestedBy->name ?? '—' }}
                                        <div class="small text-muted">{{ $req->requestedBy?->role->name ?? '' }}</div>
                                    </td>
                                    <td class="small">{{ $req->reason ?: '—' }}</td>
                                    <td>
                                        @php
                                            $status = $req->status;
                                            $color = $status === 'approved' ? 'success' : ($status === 'pending' ? 'warning text-dark' : 'danger');
                                        @endphp
                                        <span class="badge bg-{{ $color }}">{{ strtoupper($status) }}</span>
                                    </td>
                                    <td>
                                        @if($req->approvedBy)
                                            <div>{{ $req->approvedBy->name }}</div>
                                            <div class="small text-muted">
                                                {{ $req->resolved_at ? $req->resolved_at->format('Y-m-d H:i') : '' }}
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($isExpanded && $isPending && $canApproveVoid)
                                    <tr class="table-light">
                                        <td colspan="9" class="py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="small text-muted">Confirm or reject this void request:</span>
                                                <button type="button" class="btn btn-sm btn-success" wire:click="approveVoidRequest({{ $req->id }})" wire:loading.attr="disabled">
                                                    <i class="fa fa-check me-1"></i>Confirm void
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" wire:click="rejectVoidRequest({{ $req->id }})" wire:loading.attr="disabled">
                                                    <i class="fa fa-times me-1"></i>Reject
                                                </button>
                                                @if($order)
                                                    <a href="{{ route('pos.orders', ['order' => $order->id]) }}" class="btn btn-sm btn-outline-primary" wire:click.stop>
                                                        <i class="fa fa-external-link-alt me-1"></i>Open order
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No void requests match the selected filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    {{ $requests->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

