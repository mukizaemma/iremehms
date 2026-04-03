<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Suppliers {{ $canManageSuppliers ? '' : '(View only)' }}</h5>
        @if($canManageSuppliers)
            <button class="btn btn-primary" wire:click="openSupplierForm">
                <i class="fa fa-plus me-2"></i>Add Supplier
            </button>
        @endif
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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search by Name, Contact, Phone, or Email</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="filter_active" wire:model.live="filter_active">
                            <option value="">All Statuses</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                        <label for="filter_active">Status</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canManageSuppliers && $unpaidDeliveredReceipts->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Unpaid delivered items (create invoice)</h6>
                <span class="badge bg-secondary">{{ $unpaidDeliveredReceipts->count() }} receipt(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Receipt</th>
                                <th>Supplier</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidDeliveredReceipts as $receipt)
                                <tr>
                                    <td>#{{ $receipt->receipt_id }}</td>
                                    <td>{{ $receipt->supplier->name ?? '—' }}</td>
                                    <td>{{ $receipt->department->name ?? '—' }}</td>
                                    <td>{{ $receipt->business_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ number_format($receipt->total_cost ?? 0, 0) }} {{ \App\Models\Hotel::getHotel()->getCurrency() }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="createInvoiceForReceipt({{ $receipt->receipt_id }})" title="Create invoice for this delivery">
                                            <i class="fa fa-file-invoice me-1"></i>Make invoice
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Suppliers List -->
    <div class="card">
        <div class="card-body">
            @if(count($suppliers) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Currency</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($suppliers as $supplier)
                                <tr>
                                    <td><strong>{{ $supplier['name'] }}</strong></td>
                                    <td>{{ $supplier['contact_person'] ?? 'N/A' }}</td>
                                    <td>{{ $supplier['phone'] ?? 'N/A' }}</td>
                                    <td>{{ $supplier['email'] ?? 'N/A' }}</td>
                                    <td>{{ \App\Helpers\CurrencyHelper::getCurrency() }}</td>
                                    <td>
                                        @if($supplier['is_active'])
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($canManageSuppliers)
                                            <button class="btn btn-sm btn-info" wire:click="openSupplierForm({{ $supplier['supplier_id'] }})" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-{{ $supplier['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $supplier['supplier_id'] }})" title="{{ $supplier['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                <i class="fa fa-{{ $supplier['is_active'] ? 'ban' : 'check' }}"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" wire:click="deleteSupplier({{ $supplier['supplier_id'] }})" title="Delete" onclick="return confirm('Are you sure? This will only work if supplier has no associated records.')">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">No suppliers found.</div>
            @endif
        </div>
    </div>

    <!-- Supplier Form Modal -->
    @if($showSupplierForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingSupplierId ? 'Edit' : 'New' }} Supplier</h5>
                        <button type="button" class="btn-close" wire:click="closeSupplierForm"></button>
                    </div>
                    <form wire:submit.prevent="saveSupplier">
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="name" wire:model.defer="name" placeholder="Supplier Name" required>
                                <label for="name">Supplier Name <span class="text-danger">*</span></label>
                                @error('name')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="contact_person" wire:model.defer="contact_person" placeholder="Contact Person">
                                <label for="contact_person">Contact Person</label>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" wire:model.defer="phone" placeholder="Phone">
                                        <label for="phone">Phone</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" wire:model.defer="email" placeholder="Email">
                                        <label for="email">Email</label>
                                        @error('email')
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="default_currency" wire:model.defer="default_currency" required>
                                            <option value="RWF">RWF - Rwandan Franc</option>
                                            <option value="USD">USD - US Dollar</option>
                                            <option value="EUR">EUR - Euro</option>
                                            <option value="GBP">GBP - British Pound</option>
                                            <option value="KES">KES - Kenyan Shilling</option>
                                            <option value="UGX">UGX - Ugandan Shilling</option>
                                            <option value="TZS">TZS - Tanzanian Shilling</option>
                                            <option value="ETB">ETB - Ethiopian Birr</option>
                                        </select>
                                        <label for="default_currency">Default Currency <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeSupplierForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Save</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
