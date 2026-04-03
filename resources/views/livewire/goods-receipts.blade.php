<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Goods Receipt Notes (GRN)</h5>
        <div>
            <button class="btn btn-primary" wire:click="openReceiptForm">
                <i class="fa fa-plus me-2"></i>New Receipt (Manual)
            </button>
            @if(count($requisitions) > 0)
                <div class="btn-group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fa fa-file-invoice me-2"></i>From Requisition
                    </button>
                    <ul class="dropdown-menu">
                        @foreach($requisitions as $req)
                            <li><a class="dropdown-item" wire:click="openReceiptForm({{ $req->requisition_id }})">
                                Requisition #{{ $req->requisition_id }} - {{ $req->supplier->name }}
                            </a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="search" wire:model.live.debounce.300ms="search" placeholder="Search...">
                        <label for="search">Search Supplier or Notes</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="filter_status" wire:model.live="filter_status">
                            <option value="">All Statuses</option>
                            <option value="PARTIAL">Partial</option>
                            <option value="COMPLETE">Complete</option>
                        </select>
                        <label for="filter_status">Status</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="filter_supplier" wire:model.live="filter_supplier">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->supplier_id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <label for="filter_supplier">Supplier</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipts List -->
    <div class="card">
        <div class="card-body">
            @if(count($receipts) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Requisition #</th>
                                <th>Supplier</th>
                                <th>Received By</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipts as $receipt)
                                <tr>
                                    <td><strong>#{{ $receipt['receipt_id'] }}</strong></td>
                                    <td>
                                        @if($receipt['requisition'])
                                            #{{ $receipt['requisition']['requisition_id'] }}
                                        @else
                                            <span class="text-muted">Manual</span>
                                        @endif
                                    </td>
                                    <td>{{ $receipt['supplier']['name'] ?? 'N/A' }}</td>
                                    <td>{{ $receipt['received_by']['name'] ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $receipt['receipt_status'] === 'COMPLETE' ? 'success' : 'warning' }}">
                                            {{ $receipt['receipt_status'] }}
                                        </span>
                                    </td>
                                    <td>{{ \App\Helpers\CurrencyHelper::format(collect($receipt['items'])->sum('total_cost')) }}</td>
                                    <td>
                                        {{ isset($receipt['business_date']) && $receipt['business_date']
                                            ? \Carbon\Carbon::parse($receipt['business_date'])->format('M d, Y')
                                            : (\Carbon\Carbon::parse($receipt['created_at'])->format('M d, Y')) }}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" wire:click="openReceiptForm({{ $receipt['receipt_id'] }})" title="View">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">No goods receipts found.</div>
            @endif
        </div>
    </div>

    <!-- Receipt Form Modal -->
    @if($showReceiptForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Goods Receipt Note</h5>
                        <button type="button" class="btn-close" wire:click="closeReceiptForm"></button>
                    </div>
                    <form wire:submit.prevent>
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

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="supplier_id" wire:model.defer="supplier_id" required {{ $selectedRequisitionId ? 'disabled' : '' }}>
                                            <option value="">Select Supplier</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->supplier_id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="department_id" wire:model.defer="department_id" required {{ $selectedRequisitionId ? 'disabled' : '' }}>
                                            <option value="">Select Department</option>
                                            @foreach($departments as $department)
                                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="department_id">Department <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="receipt_status" wire:model.defer="receipt_status">
                                            <option value="COMPLETE">Complete</option>
                                            <option value="PARTIAL">Partial</option>
                                        </select>
                                        <label for="receipt_status">Receipt Status</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="notes" wire:model.defer="notes" style="height: 100px" placeholder="Notes"></textarea>
                                <label for="notes">Notes (Optional)</label>
                            </div>

                            <h6 class="mb-3">Received Items</h6>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>
                                <strong>Important:</strong>
                                Use <strong>Received Qty</strong> and <strong>Unit</strong> in the same purchase unit you used on the invoice (e.g. cases, boxes).
                                The system will convert this into the stock <strong>Qty Unit</strong> (e.g. bottles, kg) using the <strong>Units per Package</strong> defined on each stock item, and update stock in those base units when you confirm.
                            </div>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Location</th>
                                            <th>Requested Qty</th>
                                            <th>Received Qty</th>
                                            <th>Unit</th>
                                            <th>Expiry Date</th>
                                            <th>Unit Cost</th>
                                            <th>Total Cost</th>
                                            <th>Notes</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($receiptItems as $index => $item)
                                            @php
                                                $requestedQty = $item['quantity_requested'] ?? 0;
                                                $receivedQty = $item['quantity_received'] ?? 0;
                                                $difference = $receivedQty - $requestedQty;
                                                $rowClass = '';
                                                if ($difference < 0) {
                                                    $rowClass = 'table-warning'; // Shortage
                                                } elseif ($difference > 0) {
                                                    $rowClass = 'table-info'; // Excess
                                                }
                                            @endphp
                                            <tr class="{{ $rowClass }}">
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model.live="receiptItems.{{ $index }}.item_id" required>
                                                        <option value="">Select Item</option>
                                                        @foreach($stocks as $stock)
                                                            <option value="{{ $stock->id }}">{{ $stock->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model.defer="receiptItems.{{ $index }}.location_id" required>
                                                        <option value="">Select Location</option>
                                                        @foreach($stockLocations as $location)
                                                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" value="{{ $requestedQty }}" disabled>
                                                    @if($selectedRequisitionId && $difference != 0)
                                                        <small class="text-muted d-block">
                                                            @if($difference < 0)
                                                                <span class="text-warning">Short: {{ abs($difference) }}</span>
                                                            @else
                                                                <span class="text-info">Excess: +{{ $difference }}</span>
                                                            @endif
                                                        </small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" wire:model.live.debounce.300ms="receiptItems.{{ $index }}.quantity_received" required>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" wire:model.defer="receiptItems.{{ $index }}.unit_id">
                                                        <option value="">Select Unit</option>
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
                                                </td>
                                                <td>
                                                    @php
                                                        $expiry = $item['expiry_date'] ?? null;
                                                        $expiryFormatted = $expiry ? \Carbon\Carbon::parse($expiry)->format('Y-m-d') : '—';
                                                    @endphp
                                                    <span class="text-muted">{{ $expiryFormatted }}</span>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live.debounce.300ms="receiptItems.{{ $index }}.unit_cost" required>
                                                </td>
                                                <td>
                                                    <strong>{{ \App\Helpers\CurrencyHelper::format($item['total_cost'] ?? (($item['quantity_received'] ?? 0) * ($item['unit_cost'] ?? 0))) }}</strong>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" wire:model.defer="receiptItems.{{ $index }}.notes" placeholder="Notes">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="removeReceiptItem({{ $index }})" wire:confirm="Remove this item from the receipt?">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-primary">
                                            <th colspan="7" class="text-end">Total:</th>
                                            <th>{{ \App\Helpers\CurrencyHelper::format(collect($receiptItems)->sum('total_cost')) }}</th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <button type="button" class="btn btn-sm btn-success mb-3" wire:click="addReceiptItem">
                                <i class="fa fa-plus me-2"></i>Add Item
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeReceiptForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="button" class="btn btn-primary" wire:click="saveReceipt" wire:loading.attr="disabled" wire:target="saveReceipt" wire:confirm="Confirm this goods receipt and update stock? Quantities and costs will be saved and stock levels will be updated.">
                                <span wire:loading.remove wire:target="saveReceipt">Confirm Receipt & Update Stock</span>
                                <span wire:loading wire:target="saveReceipt">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Processing...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
