<div class="bg-light rounded p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Stock Management</h5>
                @if($canEditStockItems)
                    <button class="btn btn-primary" wire:click="openStockForm">
                        <i class="fa fa-plus me-2"></i>Add Stock Item
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

    @if($filter_stock_location_id && $filterStockLocationName)
        <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" role="status">
            <span>Showing stock for location: <strong>{{ $filterStockLocationName }}</strong></span>
            <button type="button" class="btn btn-sm btn-outline-dark shrink-0" wire:click="clearStockLocationFilter">Show all locations</button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search by Name or Code</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="filter_inventory_category" wire:model.live="filter_inventory_category">
                            <option value="">All inventory categories</option>
                            @foreach(\App\Enums\InventoryCategory::ordered() as $invCat)
                                <option value="{{ $invCat->value }}">{{ $invCat->label() }}</option>
                            @endforeach
                        </select>
                        <label for="filter_inventory_category">Inventory category</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select" id="filter_stock_type" wire:model.live="filter_stock_type">
                            <option value="all">All Types</option>
                            <option value="main">Main Stocks</option>
                            <option value="substock">Substocks</option>
                        </select>
                        <label for="filter_stock_type">Stock Type</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Short report (scoped to filters + stock type) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <button type="button"
                class="card h-100 w-100 text-start border-0 shadow-sm p-3 btn btn-light @if($focus === null && $summaryTotalItems > 0) border border-primary border-2 @endif"
                wire:click="clearFocus"
                @if($summaryTotalItems === 0) disabled @endif>
                <div class="text-muted small">Items in stock</div>
                <div class="fs-4 fw-semibold">{{ number_format($summaryTotalItems) }}</div>
                <div class="small text-muted mt-1">All lines matching filters</div>
            </button>
        </div>
        <div class="col-6 col-lg-3">
            <button type="button"
                class="card h-100 w-100 text-start border-0 shadow-sm p-3 btn btn-light @if($focus === 'low') border border-warning border-2 @endif"
                wire:click="setFocus('low')"
                @if($summaryLowCount === 0) disabled @endif>
                <div class="text-muted small">Low stock</div>
                <div class="fs-4 fw-semibold text-warning">{{ number_format($summaryLowCount) }}</div>
                <div class="small text-muted mt-1">Below safety or reorder level</div>
            </button>
        </div>
        <div class="col-6 col-lg-3">
            <button type="button"
                class="card h-100 w-100 text-start border-0 shadow-sm p-3 btn btn-light @if($focus === 'expired') border border-danger border-2 @endif"
                wire:click="setFocus('expired')"
                @if($summaryExpiredCount === 0) disabled @endif>
                <div class="text-muted small">Expired (on hand)</div>
                <div class="fs-4 fw-semibold text-danger">{{ number_format($summaryExpiredCount) }}</div>
                <div class="small text-muted mt-1">Past expiry with quantity &gt; 0</div>
            </button>
        </div>
        <div class="col-6 col-lg-3">
            <button type="button"
                class="card h-100 w-100 text-start border-0 shadow-sm p-3 btn btn-light"
                wire:click="clearFocus"
                @if($summaryTotalItems === 0) disabled @endif>
                <div class="text-muted small">Total stock value</div>
                <div class="fs-4 fw-semibold">{{ \App\Helpers\CurrencyHelper::format($summaryTotalValue) }}</div>
                <div class="small text-muted mt-1">Qty × purchase price (filtered scope)</div>
            </button>
        </div>
    </div>

    @if($focus)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" role="status">
            <span>
                @if($focus === 'low')
                    Showing <strong>low stock</strong> items only (below safety stock or at/below reorder level).
                @else
                    Showing <strong>expired</strong> lines with stock on hand (past expiry date).
                @endif
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary shrink-0" wire:click="clearFocus">Show all items</button>
        </div>
    @endif

    <!-- Stock List -->
    <div class="card" id="stock-table">
        <div class="card-body">
            @if(count($stocks) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Inventory category</th>
                                <th>Pack &amp; measure</th>
                                <th>Stock Location</th>
                                <th>Current stock</th>
                                <th>Expiry</th>
                                <th>purchase price </th>
                                <th>Purchase value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stocks as $stock)
                                @php
                                    // Base quantity in stock (authoritative). Purchase units are derived; avoid 2-decimal rounding that contradicts base × units/package.
                                    $qty = (float) ($stock['current_stock'] ?? $stock['quantity'] ?? 0);
                                    $pkgSize = isset($stock['package_size']) && $stock['package_size'] > 0 ? (float) $stock['package_size'] : 0;
                                    $pkgUnit = $stock['package_unit'] ?? '';
                                    $qtyUnit = $stock['qty_unit'] ?? $stock['unit'] ?? '';
                                    $pkgQty = $pkgSize > 0 ? $qty / $pkgSize : null;
                                    $purchasePrice = (float) ($stock['purchase_price'] ?? 0);
                                    $purchaseValue = round($qty * $purchasePrice, 2);
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $stock['name'] }}</strong>
                                        @if(isset($stock['code']) && $stock['code'])
                                            <br><small class="text-muted">Code: {{ $stock['code'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $ic = $stock['inventory_category'] ?? 'dry_goods';
                                            $icEnum = \App\Enums\InventoryCategory::tryFrom($ic);
                                        @endphp
                                        <span class="badge bg-secondary">{{ $icEnum?->label() ?? $ic }}</span>
                                    </td>
                                    <td class="small">
                                        @if($pkgSize > 0 && $pkgUnit !== '')
                                            <span class="d-block fw-semibold">{{ $pkgUnit }}</span>
                                            @if($qtyUnit !== '')
                                                <span class="text-muted">1 {{ $pkgUnit }} = {{ rtrim(rtrim(number_format($pkgSize, 4), '0'), '.') }} {{ $qtyUnit }}</span>
                                            @else
                                                <span class="text-muted">{{ rtrim(rtrim(number_format($pkgSize, 4), '0'), '.') }} units / {{ $pkgUnit }}</span>
                                            @endif
                                        @elseif($qtyUnit !== '')
                                            <span class="text-muted">{{ $qtyUnit }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($stock['stock_location']))
                                            <span class="badge bg-{{ $stock['stock_location']['is_main_location'] ? 'primary' : 'secondary' }}">
                                                {{ $stock['stock_location']['name'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $isMain = isset($stock['stock_location']['is_main_location']) && $stock['stock_location']['is_main_location'];
                                            $substockTotal = $isMain ? ($substockTotalsByName[$stock['name']] ?? null) : null;
                                        @endphp
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-{{ $isMain ? 'primary' : 'secondary' }} align-self-start">{{ $isMain ? 'Main' : 'Substock' }}</span>
                                            @if($isMain)
                                                {{-- Main stock: show purchase/package units first; base qty is secondary (storage) --}}
                                                @if($pkgSize > 0 && $pkgUnit !== '')
                                                    <span class="{{ $qty < ($stock['safety_stock'] ?? 0) ? 'text-danger fw-bold' : '' }}">
                                                        <strong>On hand:</strong> {{ number_format($pkgQty, 4) }} {{ $pkgUnit }}
                                                    </span>
                                                    <span class="text-muted small d-block">
                                                        {{ number_format($qty, 2) }} {{ $qtyUnit ?: '—' }} stored
                                                    </span>
                                                @else
                                                    <span class="{{ $qty < ($stock['safety_stock'] ?? 0) ? 'text-danger fw-bold' : '' }}">
                                                        <strong>Quantity:</strong> {{ number_format($qty, 2) }} {{ $qtyUnit ?: '—' }}
                                                    </span>
                                                @endif
                                                @if($substockTotal && ($substockTotal['base_qty'] > 0 || ($substockTotal['pkg_qty'] ?? 0) > 0))
                                                    <hr class="my-1">
                                                    <span class="small text-muted">
                                                        <strong>In substocks:</strong>
                                                        @if($substockTotal['pkg_size'] > 0 && ($substockTotal['pkg_unit'] ?? ''))
                                                            {{ number_format($substockTotal['pkg_qty'], 4) }} {{ $substockTotal['pkg_unit'] }}
                                                            <span class="text-muted">({{ number_format($substockTotal['base_qty'], 2) }} {{ $substockTotal['qty_unit'] ?: '—' }})</span>
                                                        @else
                                                            {{ number_format($substockTotal['base_qty'], 2) }} {{ $substockTotal['qty_unit'] ?: '—' }}
                                                        @endif
                                                    </span>
                                                    @php
                                                        $totalBase = $qty + $substockTotal['base_qty'];
                                                    @endphp
                                                    <span class="small">
                                                        <strong>Total (main + substocks):</strong>
                                                        @if($pkgSize > 0 && $pkgUnit !== '')
                                                            {{ number_format($totalBase / $pkgSize, 4) }} {{ $pkgUnit }}
                                                            <span class="text-muted">({{ number_format($totalBase, 2) }} {{ $qtyUnit ?: '—' }})</span>
                                                        @else
                                                            {{ number_format($totalBase, 2) }} {{ $qtyUnit ?: '—' }}
                                                        @endif
                                                    </span>
                                                @endif
                                            @else
                                                {{-- Substock: same as main — package units first when configured --}}
                                                @if($pkgSize > 0 && $pkgUnit !== '')
                                                    <span class="{{ $qty < ($stock['safety_stock'] ?? 0) ? 'text-danger fw-bold' : '' }}">
                                                        <strong>On hand:</strong> {{ number_format($pkgQty, 4) }} {{ $pkgUnit }}
                                                    </span>
                                                    <span class="text-muted small d-block">
                                                        {{ number_format($qty, 2) }} {{ $qtyUnit ?: '—' }} stored
                                                    </span>
                                                @else
                                                    <span class="{{ $qty < ($stock['safety_stock'] ?? 0) ? 'text-danger fw-bold' : '' }}">
                                                        <strong>Quantity:</strong> {{ number_format($qty, 2) }} {{ $qtyUnit ?: '—' }}
                                                    </span>
                                                @endif
                                            @endif
                                            @if(isset($stock['safety_stock']) && $stock['safety_stock'] > 0)
                                                @if($pkgSize > 0 && $pkgUnit !== '')
                                                    <small class="text-muted d-block">Safety: {{ number_format((float) $stock['safety_stock'] / $pkgSize, 4) }} {{ $pkgUnit }} ({{ number_format($stock['safety_stock'], 2) }} {{ $qtyUnit ?: '—' }})</small>
                                                @else
                                                    <small class="text-muted">Safety: {{ number_format($stock['safety_stock'], 2) }} {{ $qtyUnit ?: '' }}</small>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $expiry = ($stock['use_expiration'] ?? false) ? ($stock['expiration_date'] ?? null) : null;
                                            $expiryFormatted = $expiry ? \Carbon\Carbon::parse($expiry)->format('Y-m-d') : '—';
                                        @endphp
                                        <span class="text-muted">{{ $expiryFormatted }}</span>
                                    </td>
                                    <td>{{ $purchasePrice > 0 ? \App\Helpers\CurrencyHelper::format($purchasePrice) : '—' }}</td>
                                    <td>{{ $purchasePrice > 0 ? \App\Helpers\CurrencyHelper::format($purchaseValue) : '—' }}</td>
                                    <td class="align-middle text-nowrap">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="stockActions{{ $stock['id'] }}" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" aria-haspopup="true">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="stockActions{{ $stock['id'] }}">
                                                @if(isset($stock['stock_location']) && $stock['stock_location']['is_main_location'])
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('stock.requests', ['action' => 'create', 'type' => 'transfer_substock', 'stock_id' => $stock['id']]) }}">
                                                            <i class="fa fa-paper-plane fa-fw me-1 text-primary"></i> Request transfer
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('stock.requests', ['action' => 'create', 'type' => 'issue_department']) }}">
                                                            <i class="fa fa-external-link-alt fa-fw me-1 text-primary"></i> Request issue
                                                        </a>
                                                    </li>
                                                    @if($canAuthorizeStockRequests)
                                                        <li>
                                                            <button type="button" class="dropdown-item" wire:click="openMainToSubstockTransfer({{ $stock['id'] }})">
                                                                <i class="fa fa-arrow-right fa-fw me-1"></i> Transfer now
                                                            </button>
                                                        </li>
                                                    @endif
                                                    @if($canEditStockItems || $canAuthorizeStockRequests)
                                                        <li><hr class="dropdown-divider"></li>
                                                    @endif
                                                @endif
                                                @if($canEditStockItems)
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('stock.requests', ['action' => 'create', 'type' => 'item_edit', 'stock_id' => $stock['id']]) }}">
                                                            <i class="fa fa-clipboard fa-fw me-1"></i> Request edit
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item" wire:click="openStockForm({{ $stock['id'] }})">
                                                            <i class="fa fa-edit fa-fw me-1"></i> Edit item
                                                        </button>
                                                    </li>
                                                @endif
                                                <li>
                                                    <button type="button" class="dropdown-item" wire:click="openMovementForm({{ $stock['id'] }})">
                                                        <i class="fa fa-exchange-alt fa-fw me-1 text-success"></i> Record movement
                                                    </button>
                                                </li>
                                                @if($canEditStockItems)
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item text-danger" wire:click="deleteStock({{ $stock['id'] }})" wire:confirm="Are you sure you want to delete this stock item?">
                                                            <i class="fa fa-trash fa-fw me-1"></i> Delete
                                                        </button>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center text-muted py-4">
                    <p class="mb-2">No stock items match the current filters@if($focus) for this view@endif.</p>
                    @if($focus)
                        <p class="small mb-2">Nothing in this drill-down right now. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" wire:click="clearFocus">Show all items</button></p>
                    @endif
                    @if($filter_stock_type === 'main')
                        <p class="small mb-0">If you just added an item in a <strong>sub-stock</strong> location, set <strong>Stock Type</strong> to <strong>All Types</strong> or <strong>Substocks</strong>. Main stock rows only show items stored in a <strong>main</strong> location.</p>
                    @elseif($filter_stock_type === 'substock')
                        <p class="small mb-0">Try <strong>All Types</strong> or <strong>Main Stocks</strong>, or clear the search.</p>
                    @else
                        <p class="small mb-0">Clear the search box or change item type, or add a stock item.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Stock Form Modal -->
    @if($showStockForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingStockId ? 'Edit Stock Item' : 'Add Stock Item' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeStockForm"></button>
                    </div>
                    <form wire:submit.prevent="saveStock">
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

                            <!-- Item Name -->
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model.defer="name" required>
                                <label for="name">Item Name <span class="text-danger">*</span></label>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <!-- Barcode Section -->
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="use_barcode" wire:model.live="use_barcode" value="1">
                                        <label class="form-check-label" for="use_barcode">
                                            Use Barcode
                                        </label>
                                    </div>
                                </div>
                                @if($use_barcode)
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control @error('barcode') is-invalid @enderror" id="barcode" wire:model.defer="barcode" placeholder="Barcode">
                                            <label for="barcode">Barcode <span class="text-danger">*</span></label>
                                            @error('barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="form-floating mb-3">
                                <select class="form-select @error('inventory_category') is-invalid @enderror" id="inventory_category" wire:model.defer="inventory_category" required>
                                    @foreach(\App\Enums\InventoryCategory::ordered() as $invCat)
                                        <option value="{{ $invCat->value }}">{{ $invCat->label() }}</option>
                                    @endforeach
                                </select>
                                <label for="inventory_category">Inventory category <span class="text-danger">*</span></label>
                                @error('inventory_category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">Used for daily stock summary reports (Dry Goods, Beverage, etc.).</small>
                            </div>

                            <!-- Units & Conversions -->
                            <div class="alert alert-info py-2 mb-3">
                                <i class="fa fa-info-circle me-2"></i>
                                <strong>How units work:</strong>
                                Set <strong>package unit</strong>, <strong>units per package</strong>, and <strong>measure (qty unit)</strong> so the stock list can show <strong>on hand in packages</strong> (e.g. cases) and the pack definition (e.g. 1 case = 24 bottles). Quantities are still stored in the system as base units for transfers and valuation.
                                <strong>Purchase price</strong> is per base (measure) unit. Menu selling prices are set on menu items in Restaurant/POS.
                            </div>

                            <!-- Package Unit, Units per Package, and Qty Unit -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="package_unit" wire:model.defer="package_unit">
                                            <option value="">Select package unit</option>
                                            <option value="Box">Box</option>
                                            <option value="Carton">Carton</option>
                                            <option value="Pack">Pack</option>
                                            <option value="Case">Case</option>
                                            <option value="Bundle">Bundle</option>
                                            <option value="Pallet">Pallet</option>
                                            <option value="Bag">Bag</option>
                                            <option value="Bottle">Bottle</option>
                                            <option value="Can">Can</option>
                                            <option value="Jar">Jar</option>
                                            <option value="Tube">Tube</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <label for="package_unit">Package unit (how you buy)</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Supplier or wholesale unit (case, carton). This is <strong>not</strong> the unit used for on-hand stock.</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control @error('package_size') is-invalid @enderror" id="package_size" wire:model.defer="package_size" min="0" step="0.0001">
                                        <label for="package_size">Units per package</label>
                                        @error('package_size') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <small class="text-muted d-block mt-1">Number of <strong>base units</strong> in one purchase unit (e.g. 24 = one case holds 24 bottles). Used only to convert purchase-unit entry into stored quantity.</small>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="qty_unit" wire:model.defer="qty_unit">
                                            <option value="">Select measure unit</option>
                                            <option value="pcs">pcs - Pieces</option>
                                            <option value="kg">kg - Kilograms</option>
                                            <option value="g">g - Grams</option>
                                            <option value="l">l - Liters</option>
                                            <option value="ml">ml - Milliliters</option>
                                            <option value="m">m - Meters</option>
                                            <option value="cm">cm - Centimeters</option>
                                            <option value="m²">m² - Square Meters</option>
                                            <option value="m³">m³ - Cubic Meters</option>
                                            <option value="dozen">dozen - Dozen</option>
                                            <option value="pair">pair - Pair</option>
                                            <option value="set">set - Set</option>
                                            <option value="roll">roll - Roll</option>
                                            <option value="sheet">sheet - Sheet</option>
                                            <option value="unit">unit - Unit</option>
                                            <option value="box">box - Box</option>
                                            <option value="bottle">bottle - Bottle</option>
                                            <option value="can">can - Can</option>
                                            <option value="pack">pack - Pack</option>
                                            <option value="bag">bag - Bag</option>
                                            <option value="carton">carton - Carton</option>
                                            <option value="case">case - Case</option>
                                            <option value="barrel">barrel - Barrel</option>
                                            <option value="drum">drum - Drum</option>
                                            <option value="gallon">gallon - Gallon</option>
                                            <option value="ounce">ounce - Ounce</option>
                                            <option value="pound">pound - Pound</option>
                                            <option value="ton">ton - Ton</option>
                                        </select>
                                        <label for="qty_unit">Measure unit (per package / stored qty)</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">The unit each package contains (e.g. bottles) and the unit stored for movements and valuation. The list shows <strong>packages</strong> on hand when package settings are set.</small>
                                </div>
                            </div>

                            <!-- Prices: Purchase and Tax only. Selling price is configured in Restaurant/POS (menu items). -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control @error('purchase_price') is-invalid @enderror" id="purchase_price" wire:model.defer="purchase_price" min="0" step="0.01" required>
                                        <label for="purchase_price">Purchase Price <span class="text-danger">*</span></label>
                                        @error('purchase_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <small class="text-muted">Per <strong>base unit</strong> (qty unit below). Stock value = current base quantity × this price.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select @error('tax_type') is-invalid @enderror" id="tax_type" wire:model.defer="tax_type" required>
                                            <option value="0%">0%</option>
                                            <option value="18%">18%</option>
                                        </select>
                                        <label for="tax_type">Tax Type <span class="text-danger">*</span></label>
                                        @error('tax_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Stock Quantities: in purchase units (e.g. cases) when package is set; stored as base units (e.g. bottles). -->
                            @php
                                $__pkg = (float) ($package_size ?? 0);
                                $__usePurchaseQty = $__pkg > 0 && ! empty($package_unit);
                            @endphp
                            @if($__usePurchaseQty)
                                <div class="alert alert-secondary py-2 small mb-3">
                                    <strong>Quantities in {{ $package_unit }}s.</strong> The system stores stock in <strong>base units</strong> ({{ $qty_unit ?: 'base unit' }}): {{ $__pkg }} {{ $qty_unit ?: 'units' }} per {{ $package_unit }} → multiply before save.
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('beginning_stock_purchase') is-invalid @enderror" id="beginning_stock_purchase" wire:model.live="beginning_stock_purchase" min="0" step="0.0001" required>
                                            <label for="beginning_stock_purchase">Beginning ({{ $package_unit }}s) <span class="text-danger">*</span></label>
                                            @error('beginning_stock_purchase') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('current_stock_purchase') is-invalid @enderror" id="current_stock_purchase" wire:model.live="current_stock_purchase" min="0" step="0.0001" required>
                                            <label for="current_stock_purchase">Current ({{ $package_unit }}s) <span class="text-danger">*</span></label>
                                            @error('current_stock_purchase') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <small class="text-muted">e.g. 5 cases × {{ $__pkg }} {{ $qty_unit ?: 'units' }}/{{ $package_unit }} = {{ number_format((float) ($current_stock_purchase ?? 0) * $__pkg, 2) }} {{ $qty_unit }} on hand.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('safety_stock_purchase') is-invalid @enderror" id="safety_stock_purchase" wire:model.live="safety_stock_purchase" min="0" step="0.0001">
                                            <label for="safety_stock_purchase">Safety ({{ $package_unit }}s)</label>
                                            @error('safety_stock_purchase') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <div class="card bg-light border-0">
                                            <div class="card-body py-2 small">
                                                <strong class="d-block mb-1">Saved in database (base units)</strong>
                                                <span class="text-muted">Beginning:</span> {{ number_format((float) ($beginning_stock_purchase ?? 0) * $__pkg, 2) }} {{ $qty_unit ?: '—' }}
                                                &nbsp;·&nbsp;
                                                <span class="text-muted">Current:</span> {{ number_format((float) ($current_stock_purchase ?? 0) * $__pkg, 2) }} {{ $qty_unit ?: '—' }}
                                                &nbsp;·&nbsp;
                                                <span class="text-muted">Safety:</span> {{ number_format((float) ($safety_stock_purchase ?? 0) * $__pkg, 2) }} {{ $qty_unit ?: '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('beginning_stock_qty') is-invalid @enderror" id="beginning_stock_qty" wire:model.defer="beginning_stock_qty" min="0" step="0.01" required>
                                            <label for="beginning_stock_qty">Beginning stock (base unit) <span class="text-danger">*</span></label>
                                            @error('beginning_stock_qty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('current_stock') is-invalid @enderror" id="current_stock" wire:model.defer="current_stock" min="0" step="0.01" required>
                                            <label for="current_stock">Current stock (base unit) <span class="text-danger">*</span></label>
                                            @error('current_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <small class="text-muted">Set package unit + units per package above to enter cases/cartons instead.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control @error('safety_stock') is-invalid @enderror" id="safety_stock" wire:model.defer="safety_stock" min="0" step="0.01">
                                            <label for="safety_stock">Safety stock (base unit)</label>
                                            @error('safety_stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Expiration: show date only when Use expiration is checked -->
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="use_expiration" wire:model.live="use_expiration" value="1">
                                        <label class="form-check-label" for="use_expiration">
                                            Use Expiration
                                        </label>
                                    </div>
                                </div>
                                @if($use_expiration)
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="date" class="form-control @error('expiration_date') is-invalid @enderror" id="expiration_date" wire:model.defer="expiration_date">
                                            <label for="expiration_date">Expiration Date <span class="text-danger">*</span></label>
                                            @error('expiration_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Description -->
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" wire:model.defer="description" style="height: 100px"></textarea>
                                <label for="description">Description</label>
                            </div>

                            <!-- Stock Location -->
                            <div class="form-floating mb-3">
                                <select class="form-select @error('stock_location_id') is-invalid @enderror" id="stock_location_id" wire:model.defer="stock_location_id" required>
                                    <option value="">Select Stock Location</option>
                                    @foreach($stockLocations as $location)
                                        <option value="{{ $location->id }}">{{ $location->is_main_location ? 'Main: ' : 'Sub: ' }}{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <label for="stock_location_id">Stock Location (Main or Sub-stock) <span class="text-danger">*</span></label>
                                @error('stock_location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">New items should be stored first in main stock. Create locations in Stock Locations if needed.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeStockForm" wire:loading.attr="disabled">Cancel</button>
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

    <!-- Movement Form Modal -->
    @if($showMovementForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Stock Movement</h5>
                        <button type="button" class="btn-close" wire:click="closeMovementForm"></button>
                    </div>
                    <form wire:submit.prevent="saveMovement">
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

                            @php
                                $selectedStock = collect($stocks)->firstWhere('id', $selectedStockId);
                            @endphp

                            @if($selectedStock)
                                @php
                                    $__mQty = (float) ($selectedStock['current_stock'] ?? $selectedStock['quantity'] ?? 0);
                                    $__mPkg = isset($selectedStock['package_size']) && $selectedStock['package_size'] > 0 ? (float) $selectedStock['package_size'] : 0;
                                    $__mPkgUnit = $selectedStock['package_unit'] ?? '';
                                    $__mQtyUnit = $selectedStock['qty_unit'] ?? $selectedStock['unit'] ?? '';
                                    $__mPkgQty = $__mPkg > 0 ? $__mQty / $__mPkg : null;
                                @endphp
                                <div class="alert alert-info">
                                    <strong>Stock Item:</strong> {{ $selectedStock['name'] }}<br>
                                    @if($__mPkg > 0 && $__mPkgUnit !== '')
                                        <strong>On hand:</strong> {{ number_format($__mPkgQty, 4) }} {{ $__mPkgUnit }}
                                        <span class="text-muted">({{ number_format($__mQty, 2) }} {{ $__mQtyUnit ?: '—' }} stored)</span><br>
                                    @else
                                        <strong>Current Quantity:</strong> {{ number_format($__mQty, 2) }} {{ $__mQtyUnit ?: '—' }}<br>
                                    @endif
                                    <strong>Item Type:</strong> {{ $selectedStock['item_type']['name'] ?? 'N/A' }}
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <select class="form-select @error('movement_type') is-invalid @enderror" id="movement_type" wire:model.live="movement_type" required>
                                    <option value="OPENING">Opening Stock</option>
                                    <option value="PURCHASE">Purchase</option>
                                    <option value="TRANSFER">Transfer</option>
                                    <option value="WASTE">Waste</option>
                                    <option value="ADJUST">Adjustment</option>
                                    <option value="SALE" disabled>Sale (Phase 4)</option>
                                </select>
                                <label for="movement_type">Movement Type <span class="text-danger">*</span></label>
                                @error('movement_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="number" class="form-control @error('movement_quantity') is-invalid @enderror" id="movement_quantity" wire:model.defer="movement_quantity" step="0.01" required>
                                <label for="movement_quantity">Quantity <span class="text-danger">*</span></label>
                                <small class="text-muted">Use positive for IN, negative for OUT</small>
                                @error('movement_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if($movement_type === 'TRANSFER')
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="from_department_id" wire:model.defer="from_department_id">
                                                <option value="">Select Source Department</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                @endforeach
                                            </select>
                                            <label for="from_department_id">From Department</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating mb-3">
                                            <select class="form-select" id="to_department_id" wire:model.defer="to_department_id">
                                                <option value="">Select Destination Department</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                                @endforeach
                                            </select>
                                            <label for="to_department_id">To Department</label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(in_array($movement_type, ['WASTE', 'ADJUST']))
                                <div class="form-floating mb-3">
                                    <textarea class="form-control @error('movement_reason') is-invalid @enderror" id="movement_reason" wire:model.defer="movement_reason" style="height: 100px" required></textarea>
                                    <label for="movement_reason">Reason <span class="text-danger">*</span></label>
                                    @error('movement_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="movement_unit_price" wire:model.defer="movement_unit_price" min="0" step="0.01">
                                <label for="movement_unit_price">Unit Price</label>
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="movement_notes" wire:model.defer="movement_notes" style="height: 100px"></textarea>
                                <label for="movement_notes">Notes</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeMovementForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Record Movement</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Recording...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Old Substock Form Modal - Removed (using stock_locations now) -->
    @if(false)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Substock</h5>
                        <button type="button" class="btn-close" wire:click="closeSubstockForm"></button>
                    </div>
                    <form wire:submit.prevent="saveStock">
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

                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>
                                Creating a substock for: <strong>{{ $name }}</strong>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('substock_name') is-invalid @enderror" id="substock_name" wire:model.defer="substock_name" required>
                                <label for="substock_name">Substock Name/Location <span class="text-danger">*</span></label>
                                @error('substock_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">e.g., "Kitchen", "Bar", "Event Hall", "External Client"</small>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="code" wire:model.defer="code" readonly>
                                <label for="code">Code (Auto-generated)</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="quantity" wire:model.defer="quantity" min="0" step="0.01" value="0" readonly>
                                <label for="quantity">Initial Quantity</label>
                                <small class="text-muted">Substock starts with 0. Transfer items from main stock to add quantity.</small>
                            </div>

                            <input type="hidden" wire:model="is_main_stock" value="0">
                            <input type="hidden" wire:model="parent_stock_id">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeSubstockForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Create Substock</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Creating...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Old Main to Substock Transfer Modal - Removed (using stock_locations now) -->
    @if(false)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Transfer from Main Stock to Substock</h5>
                        <button type="button" class="btn-close" wire:click="closeMainToSubstockTransferForm"></button>
                    </div>
                    <form wire:submit.prevent="saveMainToSubstockTransfer">
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

                            @php
                                $mainStock = \App\Models\Stock::find($selectedMainStockId);
                                $substocks = $mainStock ? $mainStock->substocks : collect();
                            @endphp

                            @if($mainStock)
                                <div class="alert alert-info">
                                    <strong>Main Stock:</strong> {{ $mainStock->name }}<br>
                                    <strong>Available Quantity:</strong> {{ number_format($mainStock->quantity, 2) }} {{ $mainStock->unit ?? '' }}
                                </div>

                                <div class="form-floating mb-3">
                                    <select class="form-select @error('selectedSubstockId') is-invalid @enderror" id="selectedSubstockId" wire:model.defer="selectedSubstockId" required>
                                        <option value="">Select Substock</option>
                                        @foreach($substocks as $substock)
                                            <option value="{{ $substock->id }}">
                                                {{ $substock->substock_name }} (Current: {{ number_format($substock->quantity, 2) }} {{ $substock->unit ?? '' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <label for="selectedSubstockId">To Substock <span class="text-danger">*</span></label>
                                    @error('selectedSubstockId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control @error('transfer_quantity') is-invalid @enderror" id="transfer_quantity" wire:model.defer="transfer_quantity" min="0.01" step="0.01" max="{{ $mainStock->quantity }}" required>
                                    <label for="transfer_quantity">Quantity to Transfer <span class="text-danger">*</span></label>
                                    @error('transfer_quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="transfer_notes" wire:model.defer="transfer_notes" style="height: 100px"></textarea>
                                    <label for="transfer_notes">Notes (Optional)</label>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeMainToSubstockTransferForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Transfer</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Transferring...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Old External Transfer Modal - Removed (using stock_locations now) -->
    @if(false)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">External Transfer / Delivery</h5>
                        <button type="button" class="btn-close" wire:click="closeExternalTransferForm"></button>
                    </div>
                    <form wire:submit.prevent="saveExternalTransfer">
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

                            @php
                                $substock = \App\Models\Stock::find($selectedSubstockId);
                                $mainStock = $substock ? \App\Models\Stock::find($substock->parent_stock_id) : null;
                                $availableStocks = collect();
                                if ($mainStock) {
                                    // Get all substocks from the same parent, including the current substock
                                    $availableStocks = \App\Models\Stock::where('parent_stock_id', $mainStock->id)
                                        ->orWhere('id', $substock->id)
                                        ->get();
                                }
                            @endphp

                            @if($substock)
                                <div class="alert alert-info">
                                    <strong>Substock:</strong> {{ $substock->substock_name ?? $substock->name }}<br>
                                    <strong>Main Stock:</strong> {{ $mainStock->name ?? 'N/A' }}
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select @error('external_transfer_type') is-invalid @enderror" id="external_transfer_type" wire:model.defer="external_transfer_type" required>
                                                <option value="client">Client</option>
                                                <option value="event">Event</option>
                                            </select>
                                            <label for="external_transfer_type">Transfer Type <span class="text-danger">*</span></label>
                                            @error('external_transfer_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control @error('recipient_name') is-invalid @enderror" id="recipient_name" wire:model.defer="recipient_name" required>
                                            <label for="recipient_name">{{ $external_transfer_type == 'client' ? 'Client Name' : 'Event Name' }} <span class="text-danger">*</span></label>
                                            @error('recipient_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="recipient_details" wire:model.defer="recipient_details" style="height: 80px"></textarea>
                                    <label for="recipient_details">{{ $external_transfer_type == 'client' ? 'Client Details (Address, Contact)' : 'Event Details' }}</label>
                                </div>

                                <hr>
                                <h6>Items to Transfer</h6>
                                
                                <div id="external-transfer-items">
                                    @if(count($external_transfer_items) > 0)
                                        @foreach($external_transfer_items as $index => $item)
                                            <div class="card mb-3" wire:key="item-{{ $index }}">
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-5">
                                                            <div class="form-floating">
                                                                <select class="form-select" wire:model="external_transfer_items.{{ $index }}.stock_id" required>
                                                                    <option value="">Select Item</option>
                                                                    @foreach($availableStocks as $stock)
                                                                        <option value="{{ $stock->id }}">
                                                                            {{ $stock->name }} (Available: {{ number_format($stock->quantity, 2) }} {{ $stock->unit ?? '' }})
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                <label>Item</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="form-floating">
                                                                <input type="number" class="form-control" wire:model="external_transfer_items.{{ $index }}.quantity" min="0.01" step="0.01" required>
                                                                <label>Qty</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="form-floating">
                                                                <input type="number" class="form-control" wire:model="external_transfer_items.{{ $index }}.unit_price" min="0" step="0.01" required>
                                                                <label>Unit Price</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            @if(count($external_transfer_items) > 1)
                                                                <button type="button" class="btn btn-danger btn-sm mt-3" wire:click="removeExternalTransferItem({{ $index }})">
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="alert alert-warning">No items added. Click "Add Item" to start.</div>
                                    @endif
                                </div>

                                <button type="button" class="btn btn-sm btn-success mb-3" wire:click="addExternalTransferItem">
                                    <i class="fa fa-plus me-2"></i>Add Item
                                </button>

                                <div class="alert alert-warning">
                                    <strong>Total Amount:</strong> {{ \App\Helpers\CurrencyHelper::format($external_transfer_total) }}
                                </div>

                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="transfer_notes" wire:model.defer="transfer_notes" style="height: 100px"></textarea>
                                    <label for="transfer_notes">Notes (Optional)</label>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeExternalTransferForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Record Transfer</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Recording...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Main to Sub-Stock Transfer Modal -->
    @if($showMainToSubstockTransferForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Transfer Stock to Sub-Location</h5>
                        <button type="button" class="btn-close" wire:click="closeMainToSubstockTransferForm"></button>
                    </div>
                    <form wire:submit.prevent="saveMainToSubstockTransfer">
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

                            @php
                                $mainStock = collect($stocks)->firstWhere('id', $selectedMainStockId);
                                $mainLocation = null;
                                $subLocations = collect([]);
                                
                                if ($mainStock && isset($mainStock['stock_location'])) {
                                    $mainLocation = $mainStock['stock_location'];
                                    $subLocations = collect($stockLocations)
                                        ->where('parent_location_id', $mainLocation['id'])
                                        ->where('is_main_location', false)
                                        ->where('is_active', true);
                                }
                            @endphp

                            @if($mainStock)
                                <div class="alert alert-info">
                                    <strong>Stock Item:</strong> {{ $mainStock['name'] }}<br>
                                    <strong>Current Quantity:</strong> {{ number_format($mainStock['current_stock'] ?? $mainStock['quantity'] ?? 0, 2) }} {{ $mainStock['qty_unit'] ?? $mainStock['unit'] ?? '' }}<br>
                                    <strong>Main Location:</strong> {{ $mainLocation['name'] ?? 'N/A' }}
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <select class="form-select" id="selectedSubstockLocationId" wire:model.defer="selectedSubstockLocationId" required>
                                    <option value="">Select Sub-Location</option>
                                    @foreach($subLocations as $subLocation)
                                        <option value="{{ $subLocation['id'] }}">{{ $subLocation['name'] }} ({{ $subLocation['code'] }})</option>
                                    @endforeach
                                </select>
                                <label for="selectedSubstockLocationId">Sub-Location <span class="text-danger">*</span></label>
                                @error('selectedSubstockLocationId')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            @if($subLocations->isEmpty())
                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    No sub-locations found for this main location. Please create sub-locations first.
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <input type="number" step="0.01" min="0.01" class="form-control" id="transfer_quantity" wire:model.defer="transfer_quantity" placeholder="0.00" required>
                                <label for="transfer_quantity">Transfer Quantity <span class="text-danger">*</span></label>
                                @error('transfer_quantity')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="transfer_notes" wire:model.defer="transfer_notes" style="height: 100px" placeholder="Optional notes"></textarea>
                                <label for="transfer_notes">Notes (Optional)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeMainToSubstockTransferForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" {{ $subLocations->isEmpty() ? 'disabled' : '' }}>
                                <span wire:loading.remove>Transfer</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Transferring...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
