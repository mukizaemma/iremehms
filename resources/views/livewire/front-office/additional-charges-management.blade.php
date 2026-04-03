<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Additional Charges</h5>
                        <p class="text-muted small mb-0">{{ $this->canManageCharges() ? 'Manage extra charges (Late check-out, Extra bed, Laundry, etc.).' : 'View extra charges applied to reservations.' }}</p>
                    </div>
                    @if ($this->canManageCharges())
                        <button class="btn btn-primary" wire:click="openForm()">
                            <i class="fa fa-plus me-2"></i>Add Charge
                        </button>
                    @endif
                </div>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="search" wire:model.live="search" placeholder="Search...">
                                    <label for="search">Search by name or code</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @if (count($charges) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                            <th>Charge rule</th>
                                            <th>Status</th>
                                            @if ($this->canManageCharges())
                                                <th>Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($charges as $c)
                                            <tr>
                                                <td><strong>{{ $c['name'] }}</strong></td>
                                                <td><span class="badge bg-secondary">{{ $chargeTypes[$c['type'] ?? 'service'] ?? $c['type'] }}</span></td>
                                                <td>{{ $c['default_amount'] !== null ? \App\Helpers\CurrencyHelper::format($c['default_amount']) : '—' }}</td>
                                                <td><span class="text-muted small">{{ Str::limit($c['description'] ?? '', 40) }}</span></td>
                                                <td>{{ $chargeRules[$c['charge_rule']] ?? $c['charge_rule'] }}</td>
                                                <td>
                                                    @if ($c['is_active'])
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                @if ($this->canManageCharges())
                                                <td>
                                                    <button class="btn btn-sm btn-primary" wire:click="openForm({{ $c['id'] }})" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-{{ $c['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $c['id'] }})" title="{{ $c['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fa fa-{{ $c['is_active'] ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" wire:click="deleteCharge({{ $c['id'] }})" wire:confirm="Delete this charge?" title="Delete">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>No additional charges yet. Add one (e.g. Late check-out, Extra bed).
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Edit' : 'New' }} Additional Charge</h5>
                        <button type="button" class="btn-close" wire:click="closeForm"></button>
                    </div>
                    <form wire:submit.prevent="save">
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
                            <div class="mb-3">
                                <label for="name" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" wire:model.defer="name" required placeholder="e.g. Late checkout">
                                @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" wire:model.defer="type" required>
                                    @foreach ($chargeTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="default_amount" class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="default_amount" wire:model.defer="default_amount" placeholder="Optional default amount">
                                @error('default_amount')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" wire:model.defer="description" rows="2" placeholder="Optional description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="charge_rule" class="form-label">Charge rule</label>
                                <select class="form-select" id="charge_rule" wire:model.defer="charge_rule">
                                    @foreach ($chargeRules as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="is_tax_inclusive" wire:model.defer="is_tax_inclusive">
                                <label class="form-check-label" for="is_tax_inclusive">Tax inclusive</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save"><i class="fa fa-save me-2"></i>Save</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
