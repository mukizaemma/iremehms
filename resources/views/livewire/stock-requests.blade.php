<div class="bg-light rounded p-4">
    <style>.cursor-pointer { cursor: pointer; }</style>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Stock Requests</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.management') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-2"></i>Manage stock</a>
            <button class="btn btn-primary btn-sm" wire:click="openCreateForm()">
                <i class="fa fa-plus me-2"></i>New request
            </button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info small">
        <i class="fa fa-info-circle me-2"></i>
        Transfers to sub-locations, issues to departments, and item edits are done by <strong>request</strong>. You can add multiple items to one request. A user with permission to authorize stock requests will approve or reject it.
        @if($this->authorizers->isNotEmpty())
            <span class="d-block mt-1 text-muted">Authorizers: {{ $this->authorizers->pluck('name')->join(', ') }}</span>
        @endif
    </div>

    <ul class="nav nav-tabs mb-3">
        @if($canSeeAllRequests && !$isRestaurantManagerOnly)
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'all_requests' ? 'active' : '' }}" href="#" wire:click="$set('tab', 'all_requests')">All requests</a>
            </li>
        @endif
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'create' ? 'active' : '' }}" href="#" wire:click="$set('tab', 'create')">{{ $isRestaurantManagerOnly ? 'New Bar & Restaurant requisition' : 'New request' }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'my_requests' ? 'active' : '' }}" href="#" wire:click="$set('tab', 'my_requests')">My requests</a>
        </li>
        @if(($canAuthorize || $canAuthorizeBarRestaurant) && !$isRestaurantManagerOnly)
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'pending' ? 'active' : '' }}" href="#" wire:click="$set('tab', 'pending')">Pending approval</a>
            </li>
        @endif
    </ul>

    @if($tab === 'my_requests')
        <div class="card">
            <div class="card-body">
                @if($this->myRequests->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">You have not submitted any requests yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Date</th>
                                    <th>Approved/Rejected by</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->myRequests as $req)
                                    <tr role="button" tabindex="0" class="cursor-pointer" wire:click="viewRequest({{ $req->id }})" wire:key="my-req-{{ $req->id }}">
                                        <td>{{ \App\Models\StockRequest::typeLabels()[$req->type] ?? $req->type }}</td>
                                        <td>
                                            <span class="badge bg-{{ $req->status === 'pending' ? 'warning' : ($req->status === 'approved' ? 'success' : 'danger') }}">{{ ucfirst($req->status) }}</span>
                                        </td>
                                        <td>{{ $req->items->count() }} item(s)</td>
                                        <td>{{ $req->created_at->format('M j, Y H:i') }}</td>
                                        <td>
                                            @if($req->approvedBy)
                                                {{ $req->approvedBy->name }} ({{ $req->approved_at?->format('M j') }})
                                                @if($req->rejected_reason)
                                                    <br><small class="text-danger">{{ $req->rejected_reason }}</small>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($tab === 'pending' && ($canAuthorize || $canAuthorizeBarRestaurant))
        <div class="card">
            <div class="card-body">
                @if($this->pendingRequests->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">No pending requests.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Requested by</th>
                                    <th>Items</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->pendingRequests as $req)
                                    @php $canApproveThis = $req->isBarRestaurantRequisition() ? $canAuthorizeBarRestaurant : $canAuthorize; @endphp
                                    <tr role="button" tabindex="0" class="cursor-pointer" wire:click="viewRequest({{ $req->id }})" wire:key="pend-req-{{ $req->id }}">
                                        <td>{{ \App\Models\StockRequest::typeLabels()[$req->type] ?? $req->type }}</td>
                                        <td>{{ $req->requestedBy->name ?? '—' }}</td>
                                        <td>
                                            @foreach($req->items as $it)
                                                <span class="d-block small">{{ $it->stock->name ?? 'N/A' }}: {{ $it->quantity }}</span>
                                            @endforeach
                                        </td>
                                        <td>{{ $req->created_at->format('M j, Y H:i') }}</td>
                                        <td onclick="event.stopPropagation();">
                                            @if($canApproveThis)
                                                <button class="btn btn-sm btn-success" wire:click.stop="approveRequest({{ $req->id }})" wire:loading.attr="disabled">Approve</button>
                                                <button class="btn btn-sm btn-danger" wire:click.stop="openRejectModal({{ $req->id }})">Reject</button>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($tab === 'all_requests' && $canSeeAllRequests)
        <div class="card">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-0">Requested by</label>
                        <select class="form-select form-select-sm" wire:model.live="filterRequestedBy">
                            <option value="">All users</option>
                            @foreach($this->requesters as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-0">Department</label>
                        <select class="form-select form-select-sm" wire:model.live="filterDepartmentId">
                            <option value="">All departments</option>
                            <option value="none">No department (sub-location / N/A)</option>
                            @foreach($this->filterDepartments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if($this->allRequests->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">No requests match the filters.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Requested by</th>
                                    <th>Status</th>
                                    <th>To location / department</th>
                                    <th>Items</th>
                                    <th>Date</th>
                                    <th>Approved/Rejected by</th>
                        @if($canAuthorize || $canAuthorizeBarRestaurant)
                                        <th>Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->allRequests as $req)
                                    <tr role="button" tabindex="0" class="cursor-pointer" wire:click="viewRequest({{ $req->id }})" wire:key="all-req-{{ $req->id }}">
                                        <td>{{ \App\Models\StockRequest::typeLabels()[$req->type] ?? $req->type }}</td>
                                        <td>{{ $req->requestedBy->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $req->status === 'pending' ? 'warning' : ($req->status === 'approved' ? 'success' : 'danger') }}">{{ ucfirst($req->status) }}</span>
                                            @if($req->isDeletionRequested())
                                                <span class="badge bg-dark ms-1" title="Deletion requested">Deletion requested</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($req->toStockLocation)
                                                {{ $req->toStockLocation->name }}
                                            @elseif($req->toDepartment)
                                                {{ $req->toDepartment->name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $req->items->count() }} item(s)</td>
                                        <td>{{ $req->created_at->format('M j, Y H:i') }}</td>
                                        <td>
                                            @if($req->approvedBy)
                                                {{ $req->approvedBy->name }} ({{ $req->approved_at?->format('M j') }})
                                                @if($req->rejected_reason)
                                                    <br><small class="text-danger">{{ $req->rejected_reason }}</small>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        @php $canApproveThisReq = $req->isBarRestaurantRequisition() ? $canAuthorizeBarRestaurant : $canAuthorize; @endphp
                                        @if($canAuthorize || $canAuthorizeBarRestaurant)
                                            <td onclick="event.stopPropagation();">
                                                @if($req->status === 'pending' && $canApproveThisReq)
                                                    <button class="btn btn-sm btn-success" wire:click.stop="approveRequest({{ $req->id }})" wire:loading.attr="disabled">Approve</button>
                                                    <button class="btn btn-sm btn-danger" wire:click.stop="openRejectModal({{ $req->id }})">Reject</button>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($showCreateForm)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingRequestId ? 'Edit stock request' : 'New stock request' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeCreateForm"></button>
                    </div>
                    <form wire:submit.prevent="submitRequest">
                        <div class="modal-body">
                            <div class="mb-3">
                                @if($isRestaurantManagerOnly)
                                    <label class="form-label">Requisition type</label>
                                    <p class="form-control-plaintext mb-0"><strong>Bar &amp; Restaurant (from main stock)</strong></p>
                                    <small class="text-muted">Request items from main stock for the bar or restaurant. Requires approval.</small>
                                    <input type="hidden" wire:model="request_type" value="issue_bar_restaurant">
                                @else
                                    <label class="form-label">Request type</label>
                                    <select class="form-select" wire:model.live="request_type">
                                        @foreach(\App\Models\StockRequest::typeLabels() as $value => $label)
                                            @if($value !== 'item_edit' || $canEditStockItems)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            @if($request_type === 'transfer_substock')
                                <div class="mb-3">
                                    <label class="form-label">To sub-location (default)</label>
                                    <select class="form-select" wire:model="to_stock_location_id">
                                        <option value="">Select sub-location</option>
                                        @foreach($stockLocations->where('is_main_location', false) as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if(in_array($request_type, ['transfer_department', 'issue_department', 'issue_bar_restaurant']))
                                <div class="mb-3">
                                    <label class="form-label">To department <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model="to_department_id" required>
                                        <option value="">Select department</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                    @if($isRestaurantManagerOnly)
                                        <small class="text-muted">Select the Bar or Restaurant department that will receive the items.</small>
                                    @endif
                                </div>
                            @endif
                            <label class="form-label">Items</label>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Stock item</th>
                                            @if($request_type !== 'item_edit')
                                                <th>Quantity</th>
                                            @endif
                                            @if($request_type === 'transfer_substock')
                                                <th>To sub-location (optional)</th>
                                            @endif
                                            @if(in_array($request_type, ['transfer_department', 'issue_department', 'issue_bar_restaurant']))
                                                <th>To dept (optional)</th>
                                            @endif
                                            <th width="80"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($request_items as $idx => $item)
                                            <tr wire:key="req-item-{{ $idx }}">
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model="request_items.{{ $idx }}.stock_id" required>
                                                        <option value="">Select item</option>
                                                        @foreach($mainStocks as $s)
                                                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->stockLocation->name ?? '' }})</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                @if($request_type !== 'item_edit')
                                                    <td>
                                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" wire:model="request_items.{{ $idx }}.quantity" placeholder="Qty">
                                                    </td>
                                                @endif
                                                @if($request_type === 'transfer_substock')
                                                    <td>
                                                        <select class="form-select form-select-sm" wire:model="request_items.{{ $idx }}.to_stock_location_id">
                                                            <option value="">Same as above</option>
                                                            @foreach($stockLocations->where('is_main_location', false) as $loc)
                                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                @endif
                                                @if(in_array($request_type, ['transfer_department', 'issue_department', 'issue_bar_restaurant']))
                                                    <td>
                                                        <select class="form-select form-select-sm" wire:model="request_items.{{ $idx }}.to_department_id">
                                                            <option value="">Same as above</option>
                                                            @foreach($departments as $dept)
                                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                @endif
                                                <td>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeRequestItem({{ $idx }})"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="addRequestItem"><i class="fa fa-plus me-1"></i>Add item</button>
                            <div class="mb-0">
                                <label class="form-label">Notes (optional)</label>
                                <textarea class="form-control" wire:model="request_notes" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeCreateForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $editingRequestId ? 'Save changes' : 'Submit request' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- View request detail modal --}}
    @if($viewingRequestId && $this->viewingRequest)
        @php $req = $this->viewingRequest; @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Request #{{ $req->id }} – {{ \App\Models\StockRequest::typeLabels()[$req->type] ?? $req->type }}</h5>
                        <button type="button" class="btn-close" wire:click="closeViewModal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2"><strong>Status:</strong> <span class="badge bg-{{ $req->status === 'pending' ? 'warning' : ($req->status === 'approved' ? 'success' : 'danger') }}">{{ ucfirst($req->status) }}</span></p>
                        <p class="mb-2"><strong>Requested by:</strong> {{ $req->requestedBy->name ?? '—' }} on {{ $req->created_at->format('M j, Y H:i') }}</p>
                        @if($req->toStockLocation)
                            <p class="mb-2"><strong>To location:</strong> {{ $req->toStockLocation->name }}</p>
                        @endif
                        @if($req->toDepartment)
                            <p class="mb-2"><strong>To department:</strong> {{ $req->toDepartment->name }}</p>
                        @endif
                        @if($req->notes)
                            <p class="mb-3"><strong>Notes:</strong> {{ $req->notes }}</p>
                        @endif
                        <h6 class="mb-2">Requested items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        @if($req->type === 'transfer_substock')
                                            <th>To sub-location</th>
                                        @endif
                                        @if(in_array($req->type, ['transfer_department', 'issue_department', 'issue_bar_restaurant']))
                                            <th>To department</th>
                                        @endif
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($req->items as $it)
                                        <tr>
                                            <td>{{ $it->stock->name ?? 'N/A' }}</td>
                                            <td>{{ number_format($it->quantity, 2) }} {{ $it->stock->qty_unit ?? $it->stock->unit ?? '' }}</td>
                                            @if($req->type === 'transfer_substock')
                                                <td>{{ $it->toStockLocation->name ?? $req->toStockLocation->name ?? '—' }}</td>
                                            @endif
                                            @if(in_array($req->type, ['transfer_department', 'issue_department', 'issue_bar_restaurant']))
                                                <td>{{ $it->toDepartment->name ?? $req->toDepartment->name ?? '—' }}</td>
                                            @endif
                                            <td>{{ $it->notes ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($req->approvedBy)
                            <p class="mb-0 mt-2 text-muted small"><strong>Processed by:</strong> {{ $req->approvedBy->name }} ({{ $req->approved_at?->format('M j, Y H:i') }})@if($req->rejected_reason) – {{ $req->rejected_reason }}@endif</p>
                        @endif

                        @if($req->isDeletionRequested())
                            <div class="alert alert-warning mt-3 py-2 small mb-0">
                                <strong>Deletion requested</strong> by {{ $req->deletionRequestedBy->name ?? '—' }}. Only Super Admin can approve or reject.
                            </div>
                        @endif

                        {{-- Comments: Manager/Super Admin or requester or Bar/Restaurant approver can add --}}
                        @php $canComment = $canAuthorize || $canAuthorizeBarRestaurant || $req->requested_by_id === auth()->id(); @endphp
                        <div class="border-top pt-3 mt-3">
                            <h6 class="mb-2">Comments ({{ $req->comments->count() }})</h6>
                            @if($req->comments->isNotEmpty())
                                <div class="d-flex flex-column gap-2 mb-3">
                                    @foreach($req->comments as $comment)
                                        <div class="card border-0 bg-light py-2 px-3">
                                            <p class="mb-0 small">
                                                <strong>{{ $comment->user->name ?? '—' }}</strong>
                                                <span class="text-muted ms-1">· {{ $comment->created_at->format('M j, Y H:i') }}</span>
                                            </p>
                                            <p class="mb-0 mt-1">{{ $comment->body }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted small mb-2">No comments yet.</p>
                            @endif
                            @if($canComment)
                                <div class="input-group">
                                    <textarea class="form-control form-control-sm" wire:model.defer="commentBody" rows="2" placeholder="Add a comment or reply..."></textarea>
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="addComment" wire:loading.attr="disabled">Post</button>
                                </div>
                                @error('commentBody') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer flex-wrap gap-1">
                        @php $canApproveThis = $req->isBarRestaurantRequisition() ? $canAuthorizeBarRestaurant : $canAuthorize; @endphp
                        @if($req->status === 'pending' && $canApproveThis)
                            <button type="button" class="btn btn-success btn-sm" wire:click="approveRequest({{ $req->id }})" wire:loading.attr="disabled">Approve</button>
                            <button type="button" class="btn btn-danger btn-sm" wire:click="openRejectModal({{ $req->id }})">Reject</button>
                        @endif
                        @if($req->status !== 'pending' && auth()->user()->isSuperAdmin())
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setRequestToPending({{ $req->id }})" wire:loading.attr="disabled">Set to pending</button>
                        @endif
                        @if($req->status === 'pending')
                            @php
                                $canEditReq = $req->requested_by_id === auth()->id() || ($canAuthorize && auth()->user()->isManager()) || ($req->isBarRestaurantRequisition() && $canAuthorizeBarRestaurant && auth()->user()->isManager());
                            @endphp
                            @if($canEditReq)
                                <button type="button" class="btn btn-primary btn-sm" wire:click="editRequest({{ $req->id }})">Edit request</button>
                            @endif
                        @endif
                        @if(!$req->isDeletionRequested())
                            @if(auth()->user()->isSuperAdmin())
                                {{-- Super Admin does not request deletion; they approve/reject --}}
                            @else
                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="requestDeletion({{ $req->id }})" wire:confirm="Request deletion of this stock request? Only Super Admin can approve.">Request deletion</button>
                            @endif
                        @else
                            @if(auth()->user()->isSuperAdmin())
                                <button type="button" class="btn btn-danger btn-sm" wire:click="approveDeletion({{ $req->id }})" wire:confirm="Permanently delete this request and its items?">Approve deletion</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="rejectDeletion({{ $req->id }})">Reject deletion</button>
                            @endif
                        @endif
                        <button type="button" class="btn btn-secondary" wire:click="closeViewModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showRejectModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject request</h5>
                        <button type="button" class="btn-close" wire:click="closeRejectModal"></button>
                    </div>
                    <form wire:submit.prevent="rejectRequest">
                        <div class="modal-body">
                            <label class="form-label">Reason (required)</label>
                            <textarea class="form-control @error('rejected_reason') is-invalid @enderror" wire:model="rejected_reason" rows="3" required></textarea>
                            @error('rejected_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeRejectModal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
