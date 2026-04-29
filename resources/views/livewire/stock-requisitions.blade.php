<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Stock Requisitions</h5>
        <a href="{{ route('stock.dashboard') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    @if(Auth::user()->getEffectiveRole() && Auth::user()->getEffectiveRole()->slug === 'restaurant-manager')
        <div class="alert alert-info">
            <i class="fa fa-info-circle me-2"></i>
            <strong>Bar &amp; Restaurant requisitions</strong> — Request items from main stock for the bar or restaurant. Your requisitions will be reviewed by Super Admin or a user with approval access.
        </div>
        <div class="card">
            <div class="card-body text-center">
                <i class="fa fa-warehouse fa-3x text-primary mb-3"></i>
                <h6>Request from main stock</h6>
                <p class="text-muted">Create a requisition for items from main stock. Requires approval before items can be issued.</p>
                <a href="{{ route('stock.requests') }}?action=create&type=issue_bar_restaurant" class="btn btn-primary">
                    <i class="fa fa-plus me-2"></i>Create Bar &amp; Restaurant requisition
                </a>
            </div>
        </div>
    @else
    <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>
                Choose the right workflow:
                <ul class="mb-0 mt-2">
                    <li><strong>Purchase (supplier)</strong> — buying new stock: <strong>Purchase requisitions</strong> (approval, then ordering / goods receipt).</li>
                    <li><strong>Transfer / issue (no purchase)</strong> — moving or drawing existing stock (main ↔ sub-location, or main → department): <strong>Stock requests</strong> (approve, then issue on Stock out).</li>
                    <li><strong>Edit / cancel</strong> — change a pending <strong>stock request</strong> with <strong>Edit request</strong>, or <strong>Request deletion</strong>; <strong>Item edit</strong> requests change master stock data.</li>
                </ul>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Start here</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="fa fa-shopping-cart fa-3x text-primary mb-3"></i>
                            <h6>Purchase (supplier)</h6>
                            <p class="text-muted small">New goods from a supplier — not a transfer from your own main stock</p>
                            <a href="{{ route('purchase.requisitions', ['action' => 'add']) }}" class="btn btn-primary btn-sm">
                                Create Requisition
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <i class="fa fa-warehouse fa-3x text-info mb-3"></i>
                            <h6>Transfer (main → sub)</h6>
                            <p class="text-muted small">Stock request: move stock from <strong>main</strong> to a <strong>sub-location</strong> (packages vs base units as set on the item).</p>
                            <a href="{{ route('stock.requests', ['action' => 'create', 'type' => 'transfer_substock']) }}" class="btn btn-info btn-sm">
                                Stock transfer request
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="fa fa-exchange-alt fa-3x text-success mb-3"></i>
                            <h6>Issue to department</h6>
                            <p class="text-muted small">Stock request: issue from <strong>main stock</strong> to a department (e.g. housekeeping, events).</p>
                            <a href="{{ route('stock.requests', ['action' => 'create', 'type' => 'issue_department']) }}" class="btn btn-success btn-sm">
                                Issue request
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!(Auth::user()->getEffectiveRole() && Auth::user()->getEffectiveRole()->slug === 'restaurant-manager'))
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Track requests</h6>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Use <strong>Stock requests</strong> for transfer/issue approvals and status. Use <strong>Purchase requisitions</strong> for supplier purchase drafts and approvals.</p>
            <a href="{{ route('stock.requests') }}" class="btn btn-outline-primary btn-sm me-2">Open stock requests</a>
            <a href="{{ route('purchase.requisitions') }}" class="btn btn-outline-secondary btn-sm">Open purchase requisitions</a>
        </div>
    </div>
    @endif
</div>
