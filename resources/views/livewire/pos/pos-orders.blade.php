<div class="container-fluid py-4">
    @livewire('shift-acknowledgment-banner', ['targetScope' => \App\Models\OperationalShift::SCOPE_POS, 'onlyWhenMissing' => true])
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-xl-between gap-3 mb-3">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="mb-0">Orders</h5>
                        <p class="text-muted small mb-0">Create orders, add items, request invoice.</p>
                        @include('livewire.pos.partials.pos-quick-links', ['active' => 'orders'])
                    </div>
                    @if(!$showOrderForm && !$showOrderDetail)
                        <div class="flex-shrink-0">
                            <button class="btn btn-primary" wire:click="$set('showOrderForm', true)">
                                <i class="fa fa-plus me-2"></i>New Order
                            </button>
                        </div>
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

                @if($showOrderForm)
                    <div class="card mb-4">
                        <div class="card-header">New Order</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Table (optional)</label>
                                    <select class="form-select" wire:model.defer="table_id">
                                        <option value="">— No table —</option>
                                        @foreach($this->tables as $t)
                                            <option value="{{ $t->id }}">{{ $t->table_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button class="btn btn-primary me-2" wire:click="createOrder">Create Order</button>
                                    <button class="btn btn-secondary" wire:click="$set('showOrderForm', false)">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($showOrderDetail && $selectedOrder)
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <span>Order #{{ $selectedOrder->id }} — {{ $selectedOrder->table->table_number ?? 'No table' }} — {{ $selectedOrder->order_status }}</span>
                                <div class="small text-muted">
                                    Waiter: {{ $selectedOrder->waiter->name ?? 'N/A' }}
                                    @if(!empty($selectedOrder->transfer_comment))
                                        <br><span title="Last transfer note">Transfer note: {{ $selectedOrder->transfer_comment }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                @if($selectedOrder->order_status !== 'PAID' && $selectedOrder->order_status !== 'CANCELLED')
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startTransfer">Transfer</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeOrderDetail">Close</button>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($canEditSelectedOrderItems)
                                <div class="mb-4">
                                    <label class="form-label small text-muted">Search items</label>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control" wire:model.live.debounce.200ms="add_item_search" placeholder="Type item name or code...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" wire:model.defer="add_menu_item_id">
                                                <option value="">Select item</option>
                                                @foreach($this->filteredMenuItems as $mi)
                                                    <option value="{{ $mi['menu_item_id'] }}">{{ $mi['name'] }} ({{ ucfirst($mi['sales_category'] ?? 'food') }}) — {{ \App\Helpers\CurrencyHelper::format($mi['sale_price']) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <input type="number" step="1" min="1" class="form-control" wire:model.defer="add_quantity" placeholder="Qty" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary w-100" wire:click="addItem">Add</button>
                                        </div>
                                    </div>
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-4">
                                            <label class="form-label small">Sales category</label>
                                            <select class="form-select form-select-sm" wire:model.live="add_sales_category_filter">
                                                <option value="">All</option>
                                                <option value="food">Food</option>
                                                <option value="beverage">Beverage</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label small">Comment for kitchen/bar</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.defer="add_item_notes" placeholder="e.g. no ice, extra lemon">
                                        </div>
                                    </div>
                                    @if(strlen(trim($add_item_search)) > 0)
                                        <div class="mt-2 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                            <small class="text-muted d-block px-2 pt-2 pb-1">Click to select, or click &quot;+1&quot; to add one</small>
                                            <div class="list-group list-group-flush">
                                            @forelse($this->filteredMenuItems as $mi)
                                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                                                    <a href="#" class="text-decoration-none text-dark flex-grow-1" wire:click.prevent="selectMenuItemForAdd({{ $mi['menu_item_id'] }})">
                                                        {{ $mi['name'] }} <span class="text-muted small">({{ ucfirst($mi['sales_category'] ?? 'food') }}) — {{ \App\Helpers\CurrencyHelper::format($mi['sale_price']) }}</span>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-success ms-2" wire:click="quickAddItem({{ $mi['menu_item_id'] }}, 1)" title="Add 1">+1</button>
                                                </div>
                                            @empty
                                                <div class="list-group-item"><span class="text-muted small">No items match &quot;{{ $add_item_search }}&quot;</span></div>
                                            @endforelse
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if($showTransferForm)
                                <div class="alert alert-info small">
                                    <h6 class="mb-2">Transfer Order</h6>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label small">Transfer to user</label>
                                            <select class="form-select form-select-sm" wire:model="transfer_to_user_id">
                                                <option value="">-- Select user --</option>
                                                @foreach($transferUsers as $u)
                                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role->name ?? 'No role' }})</option>
                                                @endforeach
                                            </select>
                                            @error('transfer_to_user_id') <small class="text-danger">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small">Comment (reason for transfer)</label>
                                            <textarea class="form-control form-control-sm" rows="2" wire:model="transfer_comment" placeholder="e.g. End of shift, handing over to colleague"></textarea>
                                            @error('transfer_comment') <small class="text-danger">{{ $message }}</small> @enderror
                                        </div>
                                        <div class="col-12 d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="transferOrder">Confirm Transfer</button>
                                            <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('showTransferForm', false)">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="table-responsive mb-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Qty</th>
                                            <th>Unit price</th>
                                            <th>Total</th>
                                        @if($selectedOrder->canEditItems() || $canEditSelectedOrderItems)
                                            <th class="text-end">Post / Print / Void or Remove</th>
                                        @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($orderItems as $oi)
                                            <tr class="{{ $oi['is_voided'] ?? false ? 'table-secondary' : '' }}">
                                                <td>
                                                    @if($oi['is_voided'] ?? false)
                                                        <s>{{ $oi['menu_item']['name'] ?? 'N/A' }}</s>
                                                        <br><small class="text-danger">Voided by {{ $oi['voided_by_name'] ?? '—' }}</small>
                                                    @else
                                                        {{ $oi['menu_item']['name'] ?? 'N/A' }}
                                                        @if($oi['is_posted'] ?? false)<span class="badge bg-success small ms-1">Posted</span>@endif
                                                        @if($oi['is_printed'] ?? false)<span class="badge bg-info small ms-1">Printed</span>@endif
                                                    @endif
                                                    @if(!empty($oi['notes']))
                                                        <br><small class="text-info">{{ $oi['notes'] }}</small>
                                                    @endif
                                                    @if($selectedOrder->canEditItems() && $canEditSelectedOrderItems && !($oi['is_voided'] ?? false))
                                                        <br>
                                                        <button type="button"
                                                                class="btn btn-xs btn-outline-secondary mt-1"
                                                                wire:click="openEditItemOptions({{ $oi['id'] }})">
                                                            <i class="fa fa-sliders-h me-1"></i>Options / ingredients
                                                        </button>
                                                    @endif
                                                </td>
                                                <td>{{ (int) $oi['quantity'] }}</td>
                                                <td>{{ \App\Helpers\CurrencyHelper::format($oi['unit_price']) }}</td>
                                                <td>{{ \App\Helpers\CurrencyHelper::format($oi['line_total']) }}</td>
                                                @if($selectedOrder->canEditItems() || $canEditSelectedOrderItems)
                                                    <td class="text-end">
                                                        @if($oi['is_voided'] ?? false)
                                                            —
                                                        @elseif($oi['pending_void_request'] ?? null)
                                                            <span class="badge bg-warning text-dark">Void pending</span>
                                                        @elseif($selectedOrder->canEditItems() && (int) ($selectedOrder->waiter_id ?? 0) === (int) Auth::id() && ($oi['can_remove'] ?? true))
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary" wire:click="openPostItemModal({{ $oi['id'] }})" title="Post to station"><i class="fa fa-paper-plane"></i></button>
                                                                <button type="button" class="btn btn-outline-secondary" wire:click="printItem({{ $oi['id'] }})" title="Print"><i class="fa fa-print"></i></button>
                                                                <button type="button" class="btn btn-outline-danger" wire:click="removeOrderItem({{ $oi['id'] }})" title="Remove"><i class="fa fa-trash"></i></button>
                                                            </div>
                                                        @else
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary" wire:click="openPostItemModal({{ $oi['id'] }})" title="Post to station" @if($oi['is_posted'] ?? false) disabled @endif><i class="fa fa-paper-plane"></i></button>
                                                                <button type="button" class="btn btn-outline-secondary" wire:click="printItem({{ $oi['id'] }})" title="Print" @if($oi['is_printed'] ?? false) disabled @endif><i class="fa fa-print"></i></button>
                                                                <button type="button" class="btn btn-outline-warning" wire:click="requestVoidItem({{ $oi['id'] }})" title="Request void (needs approval)"><i class="fa fa-ban"></i> Void</button>
                                                            </div>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Post single item: confirm station --}}
                            @if($postItemModalOrderItemId)
                                <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title">Post to station</h6>
                                                <button type="button" class="btn-close" wire:click="closePostItemModal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body py-2">
                                                <label class="form-label small">Station</label>
                                                <select class="form-select form-select-sm" wire:model="postItemModalStation">
                                                    @foreach($this->stations as $slug => $label)
                                                        <option value="{{ $slug }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="closePostItemModal">Cancel</button>
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="postItem({{ $postItemModalOrderItemId }})">Post</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Edit item options / ingredients --}}
                            @if($editOptionsOrderItemId)
                                <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title">Item options & ingredients</h6>
                                                <button type="button" class="btn-close" wire:click="closeEditItemOptions" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    @if(count($editOptionGroups) > 0)
                                                        @foreach($editOptionGroups as $gIndex => $grp)
                                                            <div class="col-md-12">
                                                                <label class="form-label small">{{ $grp['name'] ?? 'Options' }}</label>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    @foreach($grp['options'] as $oIndex => $opt)
                                                                        @if(($grp['type'] ?? 'single') === 'single')
                                                                            <div class="form-check form-check-inline">
                                                                                <input class="form-check-input"
                                                                                       type="radio"
                                                                                       id="opt_{{ $gIndex }}_{{ $oIndex }}"
                                                                                       wire:model="editOptionGroups.{{ $gIndex }}.options.{{ $oIndex }}.selected"
                                                                                       value="1"
                                                                                       @if($opt['selected']) checked @endif>
                                                                                <label class="form-check-label small" for="opt_{{ $gIndex }}_{{ $oIndex }}">
                                                                                    {{ $opt['label'] }}
                                                                                    @if(!empty($opt['price_delta']) && (float)$opt['price_delta'] !== 0.0)
                                                                                        <span class="text-muted">({{ (float)$opt['price_delta'] >= 0 ? '+' : '' }}{{ number_format((float)$opt['price_delta'], 2) }})</span>
                                                                                    @endif
                                                                                </label>
                                                                            </div>
                                                                        @else
                                                                            <div class="form-check form-check-inline">
                                                                                <input class="form-check-input"
                                                                                       type="checkbox"
                                                                                       id="opt_{{ $gIndex }}_{{ $oIndex }}"
                                                                                       wire:model="editOptionGroups.{{ $gIndex }}.options.{{ $oIndex }}.selected">
                                                                                <label class="form-check-label small" for="opt_{{ $gIndex }}_{{ $oIndex }}">
                                                                                    {{ $opt['label'] }}
                                                                                    @if(!empty($opt['price_delta']) && (float)$opt['price_delta'] !== 0.0)
                                                                                        <span class="text-muted">({{ (float)$opt['price_delta'] >= 0 ? '+' : '' }}{{ number_format((float)$opt['price_delta'], 2) }})</span>
                                                                                    @endif
                                                                                </label>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="col-md-4">
                                                            <label class="form-label small">Temperature (drinks)</label>
                                                            <select class="form-select form-select-sm" wire:model="editOptionsData.temperature">
                                                                <option value="default">Default</option>
                                                                <option value="cold">Cold</option>
                                                                <option value="warm">Warm</option>
                                                                <option value="hot">Hot</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small">Sugar</label>
                                                            <select class="form-select form-select-sm" wire:model="editOptionsData.sugar">
                                                                <option value="default">Default</option>
                                                                <option value="no">No sugar</option>
                                                                <option value="less">Less sugar</option>
                                                                <option value="extra">Extra sugar</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label small">Ice</label>
                                                            <select class="form-select form-select-sm" wire:model="editOptionsData.ice">
                                                                <option value="default">Default</option>
                                                                <option value="no">No ice</option>
                                                                <option value="extra">Extra ice</option>
                                                            </select>
                                                        </div>
                                                    @endif
                                                    <div class="col-md-12">
                                                        <label class="form-label small">Ingredients (kitchen)</label>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" id="noOnion" wire:model="editOptionsData.ingredients.no_onion">
                                                            <label class="form-check-label small" for="noOnion">No onion</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" id="noSalt" wire:model="editOptionsData.ingredients.no_salt">
                                                            <label class="form-check-label small" for="noSalt">No salt</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="checkbox" id="noSugar" wire:model="editOptionsData.ingredients.no_sugar">
                                                            <label class="form-check-label small" for="noSugar">No sugar</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label small">Special instructions (optional)</label>
                                                        <input type="text"
                                                               class="form-control form-control-sm"
                                                               wire:model.defer="editOptionsData.notes"
                                                               placeholder="e.g. very cold, separate plate, serve with mains">
                                                        @error('editOptionsData.notes')<small class="text-danger">{{ $message }}</small>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeEditItemOptions">Cancel</button>
                                                <button type="button" class="btn btn-primary btn-sm" wire:click="saveItemOptions">Save</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @php
                                $pendingVoidRequests = $selectedOrder->orderItems->flatMap(fn ($item) => $item->voidRequests)->where('status', \App\Models\OrderItemVoidRequest::STATUS_PENDING);
                            @endphp
                            @if($pendingVoidRequests->isNotEmpty() && Auth::user()?->hasPermission('pos_approve_void'))
                                <div class="alert alert-warning mb-3">
                                    <h6 class="mb-2">Pending void requests</h6>
                                    <ul class="mb-0 ps-3">
                                        @foreach($pendingVoidRequests as $req)
                                            @php $item = $req->orderItem; @endphp
                                            <li class="mb-1">
                                                {{ $item->menuItem->name ?? 'Item' }} — {{ $req->reason ?: 'No reason' }}
                                                <button type="button" class="btn btn-sm btn-success ms-2" wire:click="approveVoidRequest({{ $req->id }})">Approve</button>
                                                <button type="button" class="btn btn-sm btn-danger" wire:click="rejectVoidRequest({{ $req->id }})">Reject</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php
                                $orderTotal = (float) collect($orderItems)->where('is_voided', false)->sum('line_total');
                                $orderVat = \App\Helpers\VatHelper::fromInclusive($orderTotal);
                            @endphp
                            <p class="mb-1 small text-muted">Net (excl. VAT): {{ \App\Helpers\CurrencyHelper::format($orderVat['net']) }} · VAT ({{ (int)\App\Helpers\VatHelper::getVatRate() }}% included): {{ \App\Helpers\CurrencyHelper::format($orderVat['vat']) }}</p>
                            <p class="mb-2"><strong>Total (VAT included): {{ \App\Helpers\CurrencyHelper::format($orderTotal) }}</strong></p>

                                    @if($selectedOrder->order_status === 'OPEN')
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-success" wire:click="requestInvoice" @if(empty($orderItems)) disabled @endif>Request Invoice</button>
                                    <button class="btn btn-outline-secondary" wire:click="printOrderTicket" @if(empty($orderItems)) disabled @endif title="Mark order ticket as sent to kitchen/station"><i class="fa fa-print me-1"></i>Print order ticket (KOT)</button>
                                    <button class="btn btn-outline-warning" wire:click="voidOrder" wire:confirm="Void this order?">Void Order</button>
                                </div>
                            @elseif($selectedOrder->order_status === 'CONFIRMED' && $selectedOrder->invoice)
                                <div class="d-flex gap-2 flex-wrap">
                                    @if(Auth::user()?->hasPermission('pos_confirm_payment'))
                                        <a href="{{ route('pos.payment', ['invoice' => $selectedOrder->invoice->id]) }}" class="btn btn-primary">Receive Payment</a>
                                    @endif
                                    @if(Auth::user()?->hasPermission('pos_split_bill'))
                                        <a href="{{ route('pos.payment', ['invoice' => $selectedOrder->invoice->id]) }}" class="btn btn-outline-info"><i class="fa fa-divide me-1"></i>Split bill</a>
                                    @endif
                                    <a href="{{ route('pos.receipt', ['order' => $selectedOrder->id]) }}" class="btn btn-outline-primary" target="_blank"><i class="fa fa-print me-1"></i>Print Invoice</a>
                                    <button class="btn btn-outline-secondary btn-sm" wire:click="printOrderTicket" title="Mark order ticket as sent to kitchen/station"><i class="fa fa-print me-1"></i>Print order ticket (KOT)</button>
                                </div>
                            @elseif($selectedOrder->order_status === 'PAID')
                                <div class="d-flex gap-2 flex-wrap">
                                    @if(Auth::user()?->hasPermission('pos_split_bill') && $selectedOrder->invoice)
                                        <a href="{{ route('pos.payment', ['invoice' => $selectedOrder->invoice->id]) }}" class="btn btn-outline-info"><i class="fa fa-divide me-1"></i>Split bill</a>
                                    @endif
                                    <a href="{{ route('pos.receipt', ['order' => $selectedOrder->id]) }}" class="btn btn-outline-primary" target="_blank"><i class="fa fa-print me-1"></i>Print Receipt</a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="border rounded p-3 mb-3 bg-white">
                            <div class="row g-3 align-items-end">
                                <div class="col-12">
                                    <div class="fw-semibold small text-uppercase text-muted mb-1">Find orders</div>
                                    <p class="small text-muted mb-0">By default shows <strong>today’s</strong> orders (hotel date). Adjust date range, staff, menu item, and status below.</p>
                                </div>
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small mb-1">From date</label>
                                    <input type="date" class="form-control form-control-sm" wire:model.live="date_from">
                                </div>
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small mb-1">To date</label>
                                    <input type="date" class="form-control form-control-sm" wire:model.live="date_to">
                                </div>
                                <div class="col-md-4 col-lg-6">
                                    <label class="form-label small mb-1 d-block">Quick range</label>
                                    <div class="btn-group btn-group-sm flex-wrap" role="group">
                                        <button type="button" class="btn btn-outline-secondary" wire:click="setDatePreset('today')">Today</button>
                                        <button type="button" class="btn btn-outline-secondary" wire:click="setDatePreset('yesterday')">Yesterday</button>
                                        <button type="button" class="btn btn-outline-secondary" wire:click="setDatePreset('week')">This week</button>
                                        <button type="button" class="btn btn-outline-secondary" wire:click="setDatePreset('month')">This month</button>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label class="form-label small mb-1">Waiter <span class="text-muted fw-normal">(this hotel)</span></label>
                                    <select class="form-select form-select-sm" wire:model.live="filter_waiter_id" @if($view_filter === 'mine') disabled title="Clear &quot;My orders&quot; to filter by another user" @endif>
                                        <option value="">All staff</option>
                                        @foreach($orderFilterUsers as $fu)
                                            <option value="{{ $fu['id'] }}">{{ $fu['name'] }}</option>
                                        @endforeach
                                    </select>
                                    @if($view_filter === 'mine')
                                        <div class="form-text">Switch filter from &quot;My orders&quot; to use this.</div>
                                    @endif
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label class="form-label small mb-1">Contains menu item</label>
                                    <select class="form-select form-select-sm" wire:model.live="filter_menu_item_id">
                                        <option value="">Any item</option>
                                        @foreach($menuItems as $mi)
                                            <option value="{{ $mi['menu_item_id'] }}">{{ $mi['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-lg-4 d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="clearOrderFilters">
                                        Reset filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
                            <div class="btn-group flex-wrap" role="group" aria-label="Order filters">
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'all')">
                                    All
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'mine' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'mine')">
                                    My orders
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'open')">
                                    Open
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'confirmed' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'confirmed')">
                                    Confirmed
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'paid' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'paid')">
                                    Paid
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'unpaid' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'unpaid')">
                                    Not paid
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'transferred' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'transferred')">
                                    Transferred
                                </button>
                                <button type="button" class="btn fw-semibold {{ $view_filter === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        wire:click="$set('view_filter', 'cancelled')">
                                    Cancelled
                                </button>
                            </div>
                        </div>
                        @if($orders->count() > 0)
                            @php $currentUser = Auth::user(); @endphp
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Table</th>
                                            <th>Waiter</th>
                                            <th class="text-end">Amount</th>
                                            <th>Status</th>
                                            <th>Transfer</th>
                                            <th>Payment</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($orders as $order)
                                            @php
                                                $status = $order->order_status;
                                                $statusColor = $status === 'PAID'
                                                    ? 'success'
                                                    : ($status === 'OPEN'
                                                        ? 'warning'
                                                        : ($status === 'CONFIRMED' ? 'info' : ($status === 'CREDIT' ? 'secondary' : 'secondary')));
                                                $total = $order->invoice
                                                    ? (float) $order->invoice->total_amount
                                                    : (float) ($order->orderItems?->sum('line_total') ?? 0);
                                                $canReceivePayment = $order->invoice
                                                    && $order->invoice->invoice_status !== 'PAID'
                                                    && $currentUser
                                                    && $currentUser->hasPermission('pos_confirm_payment');
                                                $canAddItemsForOrder = $order->canEditItems()
                                                    && $currentUser
                                                    && (
                                                        $currentUser->isSuperAdmin()
                                                        || $currentUser->isManager()
                                                        || $currentUser->isRestaurantManager()
                                                        || $currentUser->isCashier()
                                                        || $order->waiter_id === $currentUser->id
                                                    );
                                                $inv = $order->invoice;
                                                $paymentStatus = null;
                                                if ($inv) {
                                                    if ($inv->invoice_status === 'PAID') {
                                                        $paymentStatus = ['label' => 'Paid', 'badge' => 'success'];
                                                    } elseif ($inv->charge_type === \App\Models\Invoice::CHARGE_TYPE_ROOM) {
                                                        $paymentStatus = ['label' => 'Assigned to room', 'badge' => 'info'];
                                                    } elseif ($inv->charge_type === \App\Models\Invoice::CHARGE_TYPE_HOTEL_COVERED) {
                                                        $paymentStatus = ['label' => 'Hotel covered', 'badge' => 'secondary'];
                                                    } elseif ($inv->invoice_status === 'CREDIT') {
                                                        $paymentStatus = ['label' => 'Credit', 'badge' => 'warning'];
                                                    } else {
                                                        $paymentStatus = ['label' => $inv->invoice_status ?? '—', 'badge' => 'secondary'];
                                                    }
                                                }
                                            @endphp
                                            <tr wire:click="selectOrder({{ $order->id }})" style="cursor: pointer;">
                                                <td>{{ \App\Helpers\HotelTimeHelper::format($order->created_at, 'Y-m-d H:i') }}</td>
                                                <td>{{ $order->table->table_number ?? 'Takeaway' }}</td>
                                                <td>{{ $order->waiter->name ?? '—' }}</td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($total) }}</td>
                                                <td>
                                                    <span class="badge bg-{{ $statusColor }}">{{ $status }}</span>
                                                </td>
                                                <td>
                                                    @if($order->transferred_from_id)
                                                        <span class="badge bg-info text-dark" title="Order was transferred to this waiter"><i class="fa fa-random me-1"></i>Yes</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($paymentStatus)
                                                        <span class="badge bg-{{ $paymentStatus['badge'] }}" title="{{ $inv && $inv->charge_type === \App\Models\Invoice::CHARGE_TYPE_ROOM && $inv->reservation ? $inv->reservation->guest_name . ' · ' . ($inv->room?->room_number ?? '') : ($inv && $inv->charge_type === \App\Models\Invoice::CHARGE_TYPE_HOTEL_COVERED ? $inv->hotel_covered_names . ' · ' . $inv->hotel_covered_reason : '') }}">{{ $paymentStatus['label'] }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        @if($order->invoice)
                                                            <a href="{{ route('pos.payment', ['invoice' => $order->invoice->id]) }}"
                                                               class="btn btn-outline-secondary"
                                                               title="View invoice / payment details"
                                                               onclick="event.stopPropagation();">
                                                                <i class="fa fa-file-invoice"></i>
                                                            </a>
                                                        @endif
                                                        <button type="button"
                                                                class="btn btn-outline-primary"
                                                                wire:click.stop="selectOrder({{ $order->id }})"
                                                                title="{{ $canAddItemsForOrder ? 'Details / Add items' : 'View details' }}">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        @if($canReceivePayment)
                                                            <a href="{{ route('pos.payment', ['invoice' => $order->invoice->id]) }}"
                                                               class="btn btn-outline-success"
                                                               title="Receive payment"
                                                               onclick="event.stopPropagation();">
                                                                <i class="fa fa-money-bill-wave"></i>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="mt-2">
                                    {{ $orders->links() }}
                                </div>
                            </div>
                        @else
                            <p class="text-muted mb-0">No orders.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
