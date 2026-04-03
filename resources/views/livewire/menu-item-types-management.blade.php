<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Menu Management</h5>
                        <p class="text-muted small mb-0">Every menu item must have a type. Types control pricing, stock impact, and Bill of Menu rules.</p>
                    </div>
                    <button class="btn btn-primary" wire:click="openTypeForm()">
                        <i class="fa fa-plus me-2"></i>Add Type
                    </button>
                </div>

                <!-- Tabs: Menu items, Categories, Item types (active) -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.items') ? 'active' : '' }}" href="{{ route('menu.items') }}"><i class="fa fa-utensils me-1"></i>Menu items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.categories') ? 'active' : '' }}" href="{{ route('menu.categories') }}"><i class="fa fa-folder me-1"></i>Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('menu.item-types') ? 'active' : '' }}" href="{{ route('menu.item-types') }}">Item types</a>
                    </li>
                </ul>

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

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="search" wire:model.live="search" placeholder="Search types...">
                                    <label for="search">Search by Code or Name</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @if(count($types) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Requires BoM</th>
                                            <th>Allows BoM</th>
                                            <th>Affects Stock</th>
                                            <th>Status</th>
                                            <th>Menu Items</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($types as $type)
                                            <tr>
                                                <td><code>{{ $type['code'] }}</code></td>
                                                <td><strong>{{ $type['name'] }}</strong></td>
                                                <td>
                                                    @if($type['requires_bom'])
                                                        <span class="badge bg-info">Yes</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($type['allows_bom'])
                                                        <span class="badge bg-success">Yes</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($type['affects_stock'])
                                                        <span class="badge bg-warning text-dark">Yes</span>
                                                    @else
                                                        <span class="badge bg-secondary">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($type['is_active'])
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php $count = $type['menu_items_count'] ?? 0; @endphp
                                                    @if($count > 0)
                                                        <button type="button" class="btn btn-link btn-sm p-0 text-primary text-decoration-none" wire:click="viewMenuItems({{ $type['type_id'] }})" title="View menu items">
                                                            {{ $count }}
                                                        </button>
                                                    @else
                                                        {{ $count }}
                                                    @endif
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" wire:click="openTypeForm({{ $type['type_id'] }})" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-{{ $type['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $type['type_id'] }})" title="{{ $type['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fa fa-{{ $type['is_active'] ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" wire:click="deleteType({{ $type['type_id'] }})" wire:confirm="Are you sure you want to delete this type?" title="Delete">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>No item types found. Add FINISHED_GOOD, PREPARED_ITEM, SERVICE, EQUIPMENT to get started.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Menu Items modal -->
    @if($showMenuItemsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Menu Items — {{ $viewingTypeName }}</h5>
                        <button type="button" class="btn-close" wire:click="closeMenuItemsModal"></button>
                    </div>
                    <div class="modal-body">
                        @if(count($menuItemsForType) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Unit</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($menuItemsForType as $item)
                                            <tr>
                                                <td><strong>{{ $item['name'] ?? 'N/A' }}</strong></td>
                                                <td>{{ $item['category']['name'] ?? 'N/A' }}</td>
                                                <td>{{ \App\Helpers\CurrencyHelper::format($item['sale_price'] ?? 0) }}</td>
                                                <td>{{ $item['sale_unit'] ?? '—' }}</td>
                                                <td>
                                                    @if($item['is_active'] ?? true)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('menu.items', ['filter_type' => $viewingTypeId]) }}" class="btn btn-sm btn-outline-primary" title="Open in Menu Items">
                                                        <i class="fa fa-external-link-alt"></i>
                                                    </a>
                                                    <a href="{{ route('menu.bill-of-menu', ['menu' => $item['menu_item_id']]) }}" class="btn btn-sm btn-outline-info" title="Bill of Menu">
                                                        <i class="fa fa-list-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mb-0 mt-2 small text-muted">
                                <a href="{{ route('menu.items', ['filter_type' => $viewingTypeId]) }}">Open all in Menu Items <i class="fa fa-arrow-right"></i></a>
                            </p>
                        @else
                            <p class="text-muted mb-0">No menu items use this type.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeMenuItemsModal">Close</button>
                        @if($viewingTypeId)
                            <a href="{{ route('menu.items', ['filter_type' => $viewingTypeId]) }}" class="btn btn-primary">Open in Menu Items</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showTypeForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingTypeId ? 'Edit' : 'New' }} Menu Item Type</h5>
                        <button type="button" class="btn-close" wire:click="closeTypeForm"></button>
                    </div>
                    <form wire:submit.prevent="saveType">
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
                                <input type="text" class="form-control" id="code" wire:model.defer="code" placeholder="e.g. FINISHED_GOOD" required>
                                <label for="code">Code <span class="text-danger">*</span></label>
                                <small class="text-muted">e.g. FINISHED_GOOD, PREPARED_ITEM, SERVICE, EQUIPMENT</small>
                                @error('code')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="name" wire:model.defer="name" placeholder="Display name" required>
                                <label for="name">Name <span class="text-danger">*</span></label>
                                @error('name')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" wire:model.defer="description" placeholder="Description" style="height: 80px"></textarea>
                                <label for="description">Description</label>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="requires_bom" wire:model.defer="requires_bom">
                                <label class="form-check-label" for="requires_bom">Requires Bill of Menu (must have BoM to sell)</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="allows_bom" wire:model.defer="allows_bom">
                                <label class="form-check-label" for="allows_bom">Allows Bill of Menu</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="affects_stock" wire:model.defer="affects_stock">
                                <label class="form-check-label" for="affects_stock">Affects stock (deduct on sale)</label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeTypeForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveType"><i class="fa fa-save me-2"></i>Save</span>
                                <span wire:loading wire:target="saveType"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
