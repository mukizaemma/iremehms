<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Stock Out – Requested items</h5>
        <a href="{{ route('stock.dashboard') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-2"></i>Dashboard</a>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <p class="text-muted small">Approved requests are listed below. Issue items when stock is available, or add unavailable items to a purchase requisition for approval and purchasing.</p>

    @if($this->pendingRequests->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted py-5">No requested items pending issue.</div>
        </div>
    @else
        @foreach($this->pendingRequests as $request)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <strong>#{{ $request->id }}</strong> {{ \App\Models\StockRequest::typeLabels()[$request->type] ?? $request->type }}
                        — Requested by {{ $request->requestedBy->name ?? 'N/A' }}
                        @if($request->toStockLocation)
                            → {{ $request->toStockLocation->name }}
                        @endif
                        @if($request->toDepartment)
                            → {{ $request->toDepartment->name }}
                        @endif
                    </span>
                    <button class="btn btn-sm btn-success" wire:click="issueAllForRequest({{ $request->id }})" wire:loading.attr="disabled">Issue all (where available)</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Requested</th>
                                    <th>Issued</th>
                                    <th>Remaining</th>
                                    <th>Unit</th>
                                    <th>To Department</th>
                                    <th>To Location</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($request->items as $item)
                                    @if(!$item->isPendingIssue() || $item->isOnRequisition())
                                        @continue
                                    @endif
                                    @php
                                        $remaining = (float)$item->quantity - (float)($item->quantity_issued ?? 0);
                                        $available = $this->getAvailableQuantity($item);
                                        $canIssue = $available > 0 && $remaining > 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $item->stock->name ?? 'N/A' }}</td>
                                        <td>{{ number_format($item->quantity, 2) }}</td>
                                        <td>{{ number_format($item->quantity_issued ?? 0, 2) }}</td>
                                        <td>{{ number_format($remaining, 2) }}</td>
                                        <td>{{ $item->stock->qty_unit ?? $item->stock->unit ?? '—' }}</td>
                                        <td>{{ $request->toDepartment->name ?? '—' }}</td>
                                        <td>{{ $request->toStockLocation->name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $expiry = ($item->stock && ($item->stock->use_expiration ?? false)) ? $item->stock->expiration_date : null;
                                                $expiryFormatted = $expiry ? \Carbon\Carbon::parse($expiry)->format('Y-m-d') : '—';
                                            @endphp
                                            <span class="text-muted">{{ $expiryFormatted }}</span>
                                        </td>
                                        <td><span class="badge bg-{{ $item->issue_status === 'partial' ? 'info' : 'secondary' }}">{{ $item->issue_status }}</span></td>
                                        <td>
                                            @if($canIssue)
                                                <button class="btn btn-sm btn-primary" wire:click="issueItem({{ $item->id }})" wire:loading.attr="disabled">Issue</button>
                                            @endif
                                            @if($remaining > 0 && $available < $remaining)
                                                <button class="btn btn-sm btn-outline-warning" wire:click="openAddToRequisitionModal([{{ $item->id }}])">Add to requisition</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @if($showAddToRequisitionModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add to purchase requisition</h5>
                        <button type="button" class="btn-close" wire:click="closeAddToRequisitionModal"></button>
                    </div>
                    <form wire:submit.prevent="addToRequisition">
                        <div class="modal-body">
                            <p class="small text-muted">Selected requested items will be added to a new purchase requisition for approval. Purchaser can then confirm when purchased so items are added to main stock and issued.</p>
                            <div class="mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" wire:model="requisition_department_id" required>
                                    @foreach($this->departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Supplier (optional)</label>
                                <select class="form-select" wire:model="requisition_supplier_id">
                                    <option value="">To be assigned</option>
                                    @foreach($this->suppliers as $s)
                                        <option value="{{ $s->supplier_id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" wire:model="requisition_notes" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeAddToRequisitionModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create requisition</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
