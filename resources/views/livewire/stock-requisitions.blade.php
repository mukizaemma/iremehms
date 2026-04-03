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
                <strong>Stock Requisitions</strong> allows you to request stock from:
                <ul class="mb-0 mt-2">
                    <li><strong>New Purchases:</strong> Request new stock items to be purchased</li>
                    <li><strong>From Substocks:</strong> Request stock from substock locations</li>
                    <li><strong>From Other Departments:</strong> Request stock transfers from other departments</li>
                </ul>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Requisition Types</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="fa fa-shopping-cart fa-3x text-primary mb-3"></i>
                            <h6>New Purchase</h6>
                            <p class="text-muted small">Request new stock items to be purchased from suppliers</p>
                            <a href="{{ route('purchase.requisitions') }}" class="btn btn-primary btn-sm">
                                Create Requisition
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <i class="fa fa-warehouse fa-3x text-info mb-3"></i>
                            <h6>From Substock</h6>
                            <p class="text-muted small">Request items from substock locations. If substock is empty, route request to main stock.</p>
                            <a href="{{ route('stock.management', ['filter_stock_type' => 'substock']) }}" class="btn btn-info btn-sm">
                                Create Requisition
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="fa fa-exchange-alt fa-3x text-success mb-3"></i>
                            <h6>From Other Department</h6>
                            <p class="text-muted small">Departments (e.g. Housekeeping, Events) can request items like water or hygiene supplies from main stock.</p>
                            <a href="{{ route('stock.management', ['filter_stock_type' => 'substock']) }}" class="btn btn-success btn-sm">
                                Create Requisition
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
            <h6 class="mb-0">Requisition History</h6>
        </div>
        <div class="card-body">
            <p class="text-muted text-center py-4">Requisition functionality will be implemented here. This is a placeholder for future development.</p>
        </div>
    </div>
    @endif
</div>
