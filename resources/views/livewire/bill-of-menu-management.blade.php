<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">Bill of Menu (BoM)</h5>
            <p class="text-muted small mb-0">Define what is consumed when one unit of a menu item is sold. One active BoM per menu item; version for recipe changes.</p>
        </div>
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

    <div class="row">
        <!-- Menu items list -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Menu Items</strong>
                </div>
                <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                    @if(count($menuItems) > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($menuItems as $item)
                                <li class="list-group-item list-group-item-action {{ $selectedMenuItemId == $item['menu_item_id'] ? 'active' : '' }}" wire:click="selectMenuItem({{ $item['menu_item_id'] }})" style="cursor: pointer;">
                                    <strong>{{ $item['name'] }}</strong>
                                    <br>
                                    <small class="{{ $selectedMenuItemId == $item['menu_item_id'] ? 'text-white-50' : 'text-muted' }}">
                                        {{ $item['category']['name'] ?? '' }} · {{ $item['menu_item_type']['code'] ?? '' }}
                                    </small>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="p-3 text-muted mb-0 small">No menu items. Add items in Menu Items first.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- BoM area (when menu item selected) -->
        <div class="col-md-8">
            @if(!$selectedMenuItemId)
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fa fa-hand-pointer fa-3x mb-3"></i>
                        <p class="mb-0">Select a menu item to view or create its Bill of Menu.</p>
                    </div>
                </div>
            @else
                @php $menuItem = $selectedMenuItem; @endphp
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>{{ $menuItem->name }}</strong>
                        <span class="badge bg-secondary">{{ $menuItem->menuItemType->code ?? 'N/A' }}</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            @if($menuItem->requiresBom())
                                <span class="badge bg-info">Requires BoM</span>
                            @endif
                            @if($menuItem->allowsBom())
                                <span class="badge bg-success">Allows BoM</span>
                            @else
                                <span class="badge bg-warning text-dark">No BoM allowed</span>
                            @endif
                        </p>
                        @if($menuItem->allowsBom())
                            <button type="button" class="btn btn-primary btn-sm" wire:click="openBomForm">
                                <i class="fa fa-plus me-2"></i>New BoM Version
                            </button>
                        @endif
                    </div>
                </div>

                <!-- BoM versions list -->
                <div class="card mb-3">
                    <div class="card-header"><strong>BoM Versions</strong></div>
                    <div class="card-body p-0">
                        @if(count($billOfMenus) > 0)
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Status</th>
                                        <th>Lines</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($billOfMenus as $bom)
                                        <tr class="{{ ($selectedBomId ?? null) == $bom['bom_id'] ? 'table-primary' : '' }}" wire:click="selectBom({{ $bom['bom_id'] }})" style="cursor: pointer;">
                                            <td><strong>v{{ $bom['version'] }}</strong></td>
                                            <td>
                                                @if($bom['is_active'])
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>{{ count($bom['items'] ?? []) }}</td>
                                            <td>{{ isset($bom['created_at']) ? \Carbon\Carbon::parse($bom['created_at'])->format('M d, Y') : '' }}</td>
                                            <td onclick="event.stopPropagation();">
                                                @if(!$bom['is_active'])
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="activateBom({{ $bom['bom_id'] }})" wire:confirm="Activate this BoM? It will become the active recipe for this menu item." title="Activate">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-warning" wire:click="deactivateBom({{ $bom['bom_id'] }})" wire:confirm="Deactivate this BoM?" title="Deactivate">
                                                        <i class="fa fa-ban"></i>
                                                    </button>
                                                @endif
                                                @if(!$bom['is_active'])
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="deleteBom({{ $bom['bom_id'] }})" wire:confirm="Delete this BoM version? This cannot be undone." title="Delete">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="p-3 text-muted mb-0 small">No BoM versions yet. Click "New BoM Version" to create one.</p>
                        @endif
                    </div>
                </div>

                <!-- Selected BoM lines -->
                @if($selectedBomId && $selectedBom)
                    <div class="card">
                        <div class="card-header"><strong>BoM v{{ $selectedBom->version }} — Ingredients</strong></div>
                        <div class="card-body p-0">
                            @if(count($bomItems) > 0)
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Stock Item</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Primary</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bomItems as $line)
                                            <tr>
                                                <td>{{ $line['stock_item']['name'] ?? 'N/A' }}</td>
                                                <td>{{ $line['quantity'] }}</td>
                                                <td>{{ $line['unit'] ?? '—' }}</td>
                                                <td>@if($line['is_primary'] ?? false)<span class="badge bg-info">Yes</span>@else—@endif</td>
                                                <td>{{ $line['notes'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="p-3 text-muted mb-0 small">No lines in this BoM.</p>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- New BoM Modal -->
    @if($showBomForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">New BoM Version — {{ $selectedMenuItem->name ?? '' }} (v{{ $bom_version }})</h5>
                        <button type="button" class="btn-close" wire:click="closeBomForm"></button>
                    </div>
                    <div class="modal-body">
                        @if($selectedMenuItem && $selectedMenuItem->menuItemType)
                            <div class="alert alert-info small">
                                @if($selectedMenuItem->menuItemType->code === 'FINISHED_GOOD')
                                    <strong>FINISHED_GOOD:</strong> BoM must have exactly 1 line (quantity = 1 sale unit equivalent).
                                @elseif($selectedMenuItem->menuItemType->code === 'PREPARED_ITEM')
                                    <strong>PREPARED_ITEM:</strong> Add all ingredients; all deducted at sale time.
                                @elseif($selectedMenuItem->menuItemType->code === 'SERVICE')
                                    <strong>SERVICE:</strong> BoM not allowed.
                                @else
                                    BoM optional for this type.
                                @endif
                            </div>
                        @endif

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="bom_notes" wire:model.defer="bom_notes" placeholder="Version notes" style="height: 60px"></textarea>
                            <label for="bom_notes">Version notes (optional)</label>
                        </div>

                        <h6 class="mb-2">BoM Lines (ingredients)</h6>
                        @if(count($bomItems) > 0)
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Stock Item</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Primary</th>
                                            <th>Notes</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bomItems as $index => $line)
                                            <tr>
                                                <td>{{ $line['stock_name'] ?? 'N/A' }}</td>
                                                <td>{{ $line['quantity'] }}</td>
                                                <td>{{ $line['unit'] ?? '—' }}</td>
                                                <td>@if($line['is_primary'] ?? false)<span class="badge bg-info">Yes</span>@else—@endif</td>
                                                <td>{{ $line['notes'] ?? '—' }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="editBomItem({{ $index }})">Edit</button>
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="removeBomItem({{ $index }})" wire:confirm="Remove this line?">Remove</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($showBomItemForm)
                            <div class="card mb-3">
                                <div class="card-header">{{ $editingBomItemIndex !== null ? 'Edit line' : 'Add line' }}</div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label small">Stock item</label>
                                            <select class="form-select form-select-sm" wire:model.defer="bom_item_stock_id" required>
                                                <option value="">Select stock</option>
                                                @foreach($availableStocks as $s)
                                                    <option value="{{ $s['id'] }}">{{ $s['name'] }} ({{ $s['qty_unit'] ?? $s['unit'] ?? 'pcs' }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Quantity</label>
                                            <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" wire:model.defer="bom_item_quantity" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Unit</label>
                                            <select class="form-select form-select-sm" wire:model.defer="bom_item_unit">
                                                @foreach($availableUnits as $u)
                                                    <option value="{{ $u }}">{{ $u }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">&nbsp;</label>
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="bom_item_is_primary" wire:model.defer="bom_item_is_primary">
                                                <label class="form-check-label small" for="bom_item_is_primary">Primary</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">&nbsp;</label>
                                            @if($editingBomItemIndex !== null)
                                                <button type="button" class="btn btn-sm btn-primary w-100" wire:click="updateBomItem">Update</button>
                                                <button type="button" class="btn btn-sm btn-secondary w-100 mt-1" wire:click="resetBomItemForm">Cancel</button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-success w-100" wire:click="addBomItem">Add</button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <input type="text" class="form-control form-control-sm" wire:model.defer="bom_item_notes" placeholder="Line notes (optional)">
                                    </div>
                                    @error('bom_item_stock_id') <span class="text-danger small">{{ $message }}</span> @enderror
                                    @error('bom_item_quantity') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-primary mb-3" wire:click="openAddBomItemForm">
                                <i class="fa fa-plus me-2"></i>Add line
                            </button>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeBomForm">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveBom" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveBom"><i class="fa fa-save me-2"></i>Create BoM</span>
                            <span wire:loading wire:target="saveBom"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
