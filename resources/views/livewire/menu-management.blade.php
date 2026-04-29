<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0">Menu Management</h5>
            <p class="text-muted small mb-0">Define everything that can be sold in POS. Each item has a type, category, selling price, and preparation station.</p>
        </div>
        <button class="btn btn-primary" wire:click="openMenuItemForm()">
            <i class="fa fa-plus me-2"></i>Add Menu Item
        </button>
    </div>

    <!-- Tabs: Menu items (default), Categories, Item types -->
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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="filter_category" wire:model.live="filter_category">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat['category_id'] }}">{{ $cat['name'] }}</option>
                            @endforeach
                        </select>
                        <label for="filter_category">Category</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="filter_type" wire:model.live="filter_type">
                            <option value="">All Types</option>
                            @foreach($menuItemTypes as $type)
                                <option value="{{ $type['type_id'] }}">{{ $type['name'] }} ({{ $type['code'] }})</option>
                            @endforeach
                        </select>
                        <label for="filter_type">Item Type</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="filter_active" wire:model.live="filter_active">
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <label for="filter_active">Status</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu Items List -->
    <div class="card">
        <div class="card-body">
            @if(count($menuItems) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Sales</th>
                                <th>Cost</th>
                                <th>Price</th>
                                <th>Unit</th>
                                <th>Preparation</th>
                                <th>BoM</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($menuItems as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item['name'] ?? '—' }}</div>
                                        <div class="text-muted small">{{ $item['category']['name'] ?? 'N/A' }}</div>
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $item['menu_item_type']['code'] ?? 'N/A' }}</span></td>
                                    <td>
                                        @php $sc = $item['sales_category'] ?? 'food'; @endphp
                                        <span class="badge bg-{{ $sc === 'beverage' ? 'primary' : 'success' }}">{{ ucfirst($sc) }}</span>
                                    </td>
                                    <td>{{ isset($item['menu_cost']) ? \App\Helpers\CurrencyHelper::format($item['menu_cost']) : '—' }}</td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format($item['sale_price'] ?? 0) }}</td>
                                    <td>{{ $item['sale_unit'] ?? '—' }}</td>
                                    <td>
                                        @if(!empty($item['preparation_station']))
                                            <span class="badge bg-info">{{ $preparationStations[$item['preparation_station']] ?? $item['preparation_station'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($item['active_bill_of_menu_relation']))
                                            <span class="badge bg-success">Active v{{ $item['active_bill_of_menu_relation']['version'] ?? '' }}</span>
                                        @elseif($item['allows_bom'] ?? (($item['menu_item_type']['code'] ?? '') === 'FINISHED_GOOD'))
                                            <span class="badge bg-info">BoM Allowed</span>
                                        @else
                                            <span class="badge bg-warning text-dark">No BoM</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['is_active'])
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('menu.bill-of-menu', ['menu' => $item['menu_item_id']]) }}" class="btn btn-sm btn-outline-info" title="Bill of Menu">
                                            <i class="fa fa-list-alt"></i>
                                        </a>
                                        <button class="btn btn-sm btn-primary" wire:click="openMenuItemForm({{ $item['menu_item_id'] }})" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-{{ $item['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $item['menu_item_id'] }})" title="{{ $item['is_active'] ? 'Deactivate' : 'Activate' }}">
                                            <i class="fa fa-{{ $item['is_active'] ? 'ban' : 'check' }}"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" wire:click="deleteMenuItem({{ $item['menu_item_id'] }})" wire:confirm="Delete this menu item? BoM must be removed first." title="Delete">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info mb-0">No menu items found. Add categories and item types first, then add menu items.</div>
            @endif
        </div>
    </div>

    <!-- Menu Item Form Modal -->
    @if($showMenuItemForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content" style="z-index: 1051; max-height: calc(100vh - 2rem);">
                    <div class="modal-header flex-shrink-0">
                        <h5 class="modal-title">{{ $editingMenuItemId ? 'Edit' : 'New' }} Menu Item</h5>
                        <button type="button" class="btn-close" wire:click="closeMenuItemForm"></button>
                    </div>
                    <form wire:submit.prevent="saveMenuItem">
                        <div class="modal-body overflow-y-auto" style="max-height: min(65vh, 600px);">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="name" wire:model.defer="name" placeholder="POS name" required>
                                        <label for="name">Name (POS) <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="code" wire:model.defer="code" placeholder="Code (optional)">
                                        <label for="code">Code (optional)</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="category_id" wire:model.defer="category_id" required>
                                            <option value="">Select Category</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat['category_id'] }}">{{ $cat['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <label for="category_id">Category <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="menu_item_type_id" wire:model.defer="menu_item_type_id" required>
                                            <option value="">Select Type</option>
                                            @foreach($menuItemTypes as $type)
                                                <option value="{{ $type['type_id'] }}">{{ $type['name'] }} ({{ $type['code'] }})</option>
                                            @endforeach
                                        </select>
                                        <label for="menu_item_type_id">Item Type <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" id="sales_category" wire:model.defer="sales_category" required>
                                            @foreach(\App\Enums\SalesCategory::cases() as $sc)
                                                <option value="{{ $sc->value }}">{{ $sc->label() }}</option>
                                            @endforeach
                                        </select>
                                        <label for="sales_category">POS sales category <span class="text-danger">*</span></label>
                                    </div>
                                    <small class="text-muted">Summarized as Food or Beverage on POS sales reports.</small>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="description" wire:model.defer="description" placeholder="Description" style="height: 72px"></textarea>
                                        <label for="description">Description</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" min="0" class="form-control" id="menu_cost" wire:model.defer="menu_cost" placeholder="Cost">
                                        <label for="menu_cost">Cost (optional)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" min="0" class="form-control" id="sale_price" wire:model.defer="sale_price" required>
                                        <label for="sale_price">Selling Price <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="currency" wire:model.defer="currency" placeholder="RWF" maxlength="3">
                                        <label for="currency">Currency</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="sale_unit" wire:model.defer="sale_unit" required>
                                            @foreach($availableUnits as $u)
                                                <option value="{{ $u }}">{{ $u }}</option>
                                            @endforeach
                                        </select>
                                        <label for="sale_unit">Sale Unit <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="preparation_station_id" wire:model.defer="preparation_station_id" required>
                                            <option value="">Select preparation station</option>
                                            @foreach($preparationStationsList as $station)
                                                <option value="{{ $station->id }}">{{ $station->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="preparation_station_id">Preparation Station <span class="text-danger">*</span></label>
                                    </div>
                                    <small class="text-muted">Where this item is prepared (e.g. Kitchen, Bar).</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" min="0" class="form-control" id="display_order" wire:model.defer="display_order">
                                        <label for="display_order">Display Order</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 align-items-end">
                                @if($this->useBomForMenuItems)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="allows_bom" wire:model.defer="allows_bom">
                                        <label class="form-check-label" for="allows_bom">Allow BoM (Bill of Menu)</label>
                                    </div>
                                    <small class="text-muted">When checked, this item can have a Bill of Menu (ingredients/recipe).</small>
                                </div>
                                @endif
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label class="form-label small mb-1">Image (optional)</label>
                                    <input type="file" class="form-control form-control-sm" wire:model="image" accept="image/*">
                                    @error('image') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            @if($imagePreview)
                                <div class="mt-2">
                                    <img src="{{ $imagePreview }}" alt="Preview" style="max-height: 80px;" class="rounded border">
                                </div>
                            @endif

                            <hr class="my-4">

                            <h6 class="mb-2">POS options for this item (optional)</h6>
                            <p class="text-muted small">
                                Configure choices the waiter can select when taking orders (e.g. temperature for drinks, cooking level, extras).
                                Options defined here will later appear in the order item options modal.
                            </p>
                            <div class="mb-3">
                                @forelse($optionGroups as $gIndex => $group)
                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <input type="text"
                                                       class="form-control form-control-sm"
                                                       style="min-width: 180px;"
                                                       placeholder="Group name (e.g. Temperature, Extras)"
                                                       wire:model.defer="optionGroups.{{ $gIndex }}.name">
                                                <select class="form-select form-select-sm"
                                                        style="width: 120px;"
                                                        wire:model.defer="optionGroups.{{ $gIndex }}.type">
                                                    <option value="single">Single choice</option>
                                                    <option value="multi">Multiple choice</option>
                                                </select>
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    wire:click="removeOptionGroup({{ $gIndex }})">
                                                Remove group
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-1">
                                                <thead>
                                                    <tr>
                                                        <th>Label</th>
                                                        <th>Internal value</th>
                                                        <th>Price change</th>
                                                        <th>Default?</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($group['options'] ?? [] as $oIndex => $opt)
                                                        <tr>
                                                            <td>
                                                                <input type="text"
                                                                       class="form-control form-control-sm"
                                                                       wire:model.defer="optionGroups.{{ $gIndex }}.options.{{ $oIndex }}.label"
                                                                       placeholder="e.g. Cold, Well done, Extra cheese">
                                                            </td>
                                                            <td>
                                                                <input type="text"
                                                                       class="form-control form-control-sm"
                                                                       wire:model.defer="optionGroups.{{ $gIndex }}.options.{{ $oIndex }}.value"
                                                                       placeholder="optional (auto from label)">
                                                            </td>
                                                            <td>
                                                                <input type="number"
                                                                       step="0.01"
                                                                       class="form-control form-control-sm"
                                                                       wire:model.defer="optionGroups.{{ $gIndex }}.options.{{ $oIndex }}.price_delta"
                                                                       placeholder="0.00">
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="checkbox"
                                                                       class="form-check-input"
                                                                       wire:model.defer="optionGroups.{{ $gIndex }}.options.{{ $oIndex }}.is_default">
                                                            </td>
                                                            <td class="text-end">
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-danger"
                                                                        wire:click="removeOptionFromGroup({{ $gIndex }}, {{ $oIndex }})">
                                                                    Remove
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                wire:click="addOptionToGroup({{ $gIndex }})">
                                            + Add option
                                        </button>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-1">No POS options defined yet.</p>
                                @endforelse
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary mt-1"
                                        wire:click="addOptionGroup">
                                    + Add option group
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer flex-shrink-0 border-top bg-light">
                            <button type="button" class="btn btn-secondary" wire:click="closeMenuItemForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveMenuItem"><i class="fa fa-save me-2"></i>Save</span>
                                <span wire:loading wire:target="saveMenuItem"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
