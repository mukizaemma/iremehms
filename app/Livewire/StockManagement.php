<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\ItemType;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use App\Services\OperationalShiftActionGate;
use App\Services\TimeAndShiftResolver;
use App\Support\ActivityLogModule;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StockManagement extends Component
{
    use WithPagination, ChecksModuleStatus;

    protected function assertStoreOperationalShiftAllowed(): bool
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return true;
        }
        try {
            OperationalShiftActionGate::assertStoreActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return false;
        }

        return true;
    }

    public $stocks = [];
    public $itemTypes = [];
    public $departments = [];
    public $stockLocations = [];
    public $showStockForm = false;
    public $editingStockId = null;
    public $showMovementForm = false;
    public $selectedStockId = null;
    public $showMainToSubstockTransferForm = false;
    public $selectedMainStockId = null;
    
    // Stock form fields - All required attributes
    public $name = '';
    public $use_barcode = false;
    public $barcode = '';
    public $item_type_id = null; // MANDATORY: Assets, Expenses, Finished Product, Raw Material, Service
    public $package_unit = '';
    public $package_size = null; // units per package (e.g. 24 bottles per case)
    public $qty_unit = '';
    public $purchase_price = 0;
    public $sale_price = 0;
    public $tax_type = '0%';
    public $beginning_stock_qty = 0;
    public $current_stock = 0;
    public $safety_stock = 0;
    public $use_expiration = false;
    public $expiration_date = null;
    public $description = '';
    public $stock_location_id = null; // MANDATORY: Main or sub-stock location
    
    // Legacy fields (kept for compatibility)
    public $code = '';
    public $quantity = 0;
    public $unit = '';
    public $unit_price = 0;
    public $department_id = null;
    public $is_sellable = null;
    public $is_consumable = null;
    public $tracking_method = 'quantity';
    public $expected_lifespan = null;
    public $reorder_level = null;
    public $reorder_quantity = null;
    public $location = '';
    
    // Transfer form fields
    public $transfer_quantity = 0;
    public $transfer_notes = '';
    public $selectedSubstockLocationId = null;
    
    // External transfer fields
    public $external_transfer_type = 'client';
    public $recipient_name = '';
    public $recipient_details = '';
    public $external_transfer_items = []; // Array of items with prices
    public $external_transfer_total = 0;
    
    // Movement form fields
    public $movement_type = 'PURCHASE';
    public $movement_quantity = 0;
    public $movement_unit_price = 0;
    public $from_department_id = null;
    public $to_department_id = null;
    public $movement_reason = '';
    public $movement_notes = '';
    
    // Filters
    public $filter_item_type = '';
    public $filter_stock_type = 'main'; // main, substock, all
    public $search = '';

    /** Substock totals per product name (base qty and package qty) for "In substocks" on main rows */
    public $substockTotalsByName = [];

    /** Whether the current user can authorize stock requests (and thus do direct transfer without request) */
    public $canAuthorizeStockRequests = false;

    /** Whether the current user can edit/delete stock items (Super Admin or Manager) */
    public $canEditStockItems = false;

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');
        $this->canAuthorizeStockRequests = Auth::user() && Auth::user()->hasPermission('stock_authorize_requests');
        $this->canEditStockItems = Auth::user() && (Auth::user()->isSuperAdmin() || Auth::user()->isManager());

        // Check if stock locations exist
        $locationsCount = StockLocation::where('is_active', true)->count();
        if ($locationsCount === 0) {
            session()->flash('error', 'No stock locations found. Please create at least one main stock location first. Only Manager and Super Admin can create stock locations.');
        }

        // Allow external links (e.g. from Stock Requisitions) to control initial stock view
        if (request()->has('filter_stock_type')) {
            $this->filter_stock_type = request()->get('filter_stock_type', 'main');
        }
        
        $this->loadData();
        
        // Check if action=add is in the request
        if (request()->has('action') && request()->get('action') === 'add') {
            if ($locationsCount > 0) {
                $this->openStockForm();
            }
        }
    }

    public function loadData()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $this->itemTypes = ItemType::active()->get();
        $deptQuery = Department::where('is_active', true);
        if (!empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->get();
        $this->stockLocations = StockLocation::where('is_active', true)->orderBy('is_main_location', 'desc')->orderBy('name')->get();
        $this->loadStocks();
    }

    public function loadStocks()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $query = Stock::with(['itemType', 'department', 'stockLocation']);
        
        if ($this->filter_item_type) {
            $query->where('item_type_id', $this->filter_item_type);
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($enabledDepartments)) {
            $query->whereIn('department_id', $enabledDepartments);
        }
        
        $this->stocks = $query->with('stockLocation')->orderBy('name')->get()->toArray();
        $this->computeSubstockTotals();
    }

    /**
     * Compute total quantity in substocks per product name (for display on main-stock rows).
     */
    protected function computeSubstockTotals(): void
    {
        $this->substockTotalsByName = [];
        foreach ($this->stocks as $s) {
            $loc = $s['stock_location'] ?? null;
            if (!$loc || !empty($loc['is_main_location'])) {
                continue;
            }
            $name = $s['name'];
            $qty = (float) ($s['current_stock'] ?? $s['quantity'] ?? 0);
            if (!isset($this->substockTotalsByName[$name])) {
                $pkgSize = isset($s['package_size']) && $s['package_size'] > 0 ? (float) $s['package_size'] : 0;
                $this->substockTotalsByName[$name] = [
                    'base_qty' => 0,
                    'pkg_qty' => 0,
                    'pkg_unit' => $s['package_unit'] ?? '',
                    'pkg_size' => $pkgSize,
                    'qty_unit' => $s['qty_unit'] ?? $s['unit'] ?? '',
                ];
            }
            $this->substockTotalsByName[$name]['base_qty'] += $qty;
            if ($this->substockTotalsByName[$name]['pkg_size'] > 0) {
                $this->substockTotalsByName[$name]['pkg_qty'] += $qty / $this->substockTotalsByName[$name]['pkg_size'];
            }
        }
    }

    public function openStockForm($stockId = null)
    {
        // Check if stock locations exist
        if (StockLocation::where('is_active', true)->count() === 0) {
            session()->flash('error', 'No stock locations found. Please create at least one main stock location first. Only Manager and Super Admin can create stock locations.');
            return;
        }
        // Only Super Admin or Manager can edit an existing stock item
        if ($stockId && !$this->canEditStockItems) {
            session()->flash('error', 'Only Super Admin or Manager can edit stock items.');
            return;
        }
        
        $this->editingStockId = $stockId;
        
        if ($stockId) {
            $stock = Stock::find($stockId);
            $this->name = $stock->name;
            $this->use_barcode = $stock->use_barcode ?? false;
            $this->barcode = $stock->barcode ?? '';
            $this->item_type_id = $stock->item_type_id;
            $this->package_unit = $stock->package_unit ?? '';
            $this->package_size = $stock->package_size ?? null;
            $this->qty_unit = $stock->qty_unit ?? '';
            $this->purchase_price = $stock->purchase_price ?? 0;
            $this->sale_price = $stock->sale_price ?? 0;
            $this->tax_type = $stock->tax_type ?? '0%';
            $this->beginning_stock_qty = $stock->beginning_stock_qty ?? 0;
            $this->current_stock = $stock->current_stock ?? $stock->quantity ?? 0;
            $this->safety_stock = $stock->safety_stock ?? 0;
            $this->use_expiration = $stock->use_expiration ?? false;
            $this->expiration_date = $stock->expiration_date;
            $this->description = $stock->description ?? '';
            $this->stock_location_id = $stock->stock_location_id;
            
            // Legacy fields
            $this->code = $stock->code ?? '';
            $this->quantity = $stock->quantity ?? 0;
            $this->unit = $stock->unit ?? '';
            $this->unit_price = $stock->unit_price ?? 0;
            $this->department_id = $stock->department_id;
        } else {
            $this->resetStockForm();
        }
        
        $this->showStockForm = true;
    }


    public function closeStockForm()
    {
        $this->showStockForm = false;
        $this->resetStockForm();
    }

    public function resetStockForm()
    {
        $this->editingStockId = null;
        $this->name = '';
        $this->use_barcode = false;
        $this->barcode = '';
        $this->item_type_id = null;
        $this->package_unit = '';
        $this->package_size = null;
        $this->qty_unit = '';
        $this->purchase_price = 0;
        $this->sale_price = 0;
        $this->tax_type = '0%';
        $this->beginning_stock_qty = 0;
        $this->current_stock = 0;
        $this->safety_stock = 0;
        $this->use_expiration = false;
        $this->expiration_date = null;
        $this->description = '';
        $this->stock_location_id = null;
        
        // Legacy fields
        $this->code = '';
        $this->quantity = 0;
        $this->unit = '';
        $this->unit_price = 0;
        // Default department for new stock items:
        // - use the authenticated user's department if it is enabled for the hotel
        // - otherwise keep it null (stocks without department will not show in filtered views)
        $userDeptId = (int) (Auth::user()?->department_id ?? 0);
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        $this->department_id = in_array($userDeptId, $enabledDepartments, true) ? $userDeptId : null;
    }

    public function saveStock()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'use_barcode' => 'boolean',
            'barcode' => 'required_if:use_barcode,true|nullable|string|max:255|unique:stocks,barcode,' . ($this->editingStockId ?? ''),
            'item_type_id' => 'required|exists:item_types,id', // MANDATORY: Assets, Expenses, Finished Product, Raw Material, Service
            'package_unit' => 'nullable|string|max:50',
            'package_size' => 'nullable|numeric|min:0',
            'qty_unit' => 'nullable|string|max:50',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'tax_type' => 'required|in:0%,18%',
            'beginning_stock_qty' => 'required|numeric|min:0',
            'current_stock' => 'required|numeric|min:0',
            'safety_stock' => 'nullable|numeric|min:0',
            'use_expiration' => 'boolean',
            'expiration_date' => 'required_if:use_expiration,true|nullable|date',
            'description' => 'nullable|string',
            'stock_location_id' => 'required|exists:stock_locations,id', // MANDATORY: Main or sub-stock location
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Only Super Admin or Manager can update an existing stock item
        if ($this->editingStockId && !$this->canEditStockItems) {
            session()->flash('error', 'Only Super Admin or Manager can edit stock items.');
            return;
        }

        // Sync current_stock with quantity for compatibility
        $quantity = $this->current_stock;

        if ($this->editingStockId) {
            $stock = Stock::find($this->editingStockId);
            $stock->update([
                'name' => $this->name,
                'use_barcode' => $this->use_barcode,
                'barcode' => $this->use_barcode ? $this->barcode : null,
                'item_type_id' => $this->item_type_id,
                'package_unit' => $this->package_unit,
                'package_size' => $this->package_size,
                'qty_unit' => $this->qty_unit,
                'purchase_price' => $this->purchase_price,
                'sale_price' => $this->sale_price,
                'tax_type' => $this->tax_type,
                'beginning_stock_qty' => $this->beginning_stock_qty,
                'current_stock' => $this->current_stock,
                'safety_stock' => $this->safety_stock,
                'use_expiration' => $this->use_expiration,
                'expiration_date' => $this->use_expiration ? $this->expiration_date : null,
                'description' => $this->description,
                'stock_location_id' => $this->stock_location_id,
                'department_id' => $this->department_id,
                // Legacy fields for compatibility
                'quantity' => $quantity,
                'unit' => $this->qty_unit,
                'unit_price' => $this->purchase_price,
            ]);
            session()->flash('message', 'Stock item updated successfully!');
        } else {
            Stock::create([
                'name' => $this->name,
                'use_barcode' => $this->use_barcode,
                'barcode' => $this->use_barcode ? $this->barcode : null,
                'item_type_id' => $this->item_type_id,
                'package_unit' => $this->package_unit,
                'package_size' => $this->package_size,
                'qty_unit' => $this->qty_unit,
                'purchase_price' => $this->purchase_price,
                'sale_price' => $this->sale_price,
                'tax_type' => $this->tax_type,
                'beginning_stock_qty' => $this->beginning_stock_qty,
                'current_stock' => $this->current_stock,
                'safety_stock' => $this->safety_stock,
                'use_expiration' => $this->use_expiration,
                'expiration_date' => $this->use_expiration ? $this->expiration_date : null,
                'description' => $this->description,
                'stock_location_id' => $this->stock_location_id,
                'department_id' => $this->department_id,
                // Legacy fields for compatibility
                'quantity' => $quantity,
                'unit' => $this->qty_unit,
                'unit_price' => $this->purchase_price,
            ]);
            session()->flash('message', 'Stock item created successfully!');
        }

        $this->closeStockForm();
        $this->loadStocks();
    }

    public function openMovementForm($stockId)
    {
        $this->selectedStockId = $stockId;
        $stock = Stock::find($stockId);
        
        if (!$stock || !$stock->itemType) {
            session()->flash('error', 'Stock must have an item type to record movements.');
            return;
        }
        
        $this->movement_type = 'PURCHASE';
        $this->movement_quantity = 0;
        $this->movement_unit_price = $stock->unit_price ?? 0;
        $this->from_department_id = null;
        $this->to_department_id = $stock->department_id;
        $this->movement_reason = '';
        $this->movement_notes = '';
        
        $this->showMovementForm = true;
    }

    public function closeMovementForm()
    {
        $this->showMovementForm = false;
        $this->selectedStockId = null;
        $this->movement_type = 'PURCHASE';
        $this->movement_quantity = 0;
        $this->movement_unit_price = 0;
        $this->from_department_id = null;
        $this->to_department_id = null;
        $this->movement_reason = '';
        $this->movement_notes = '';
    }

    public function saveMovement()
    {
        if (! $this->assertStoreOperationalShiftAllowed()) {
            return;
        }

        $this->validate([
            'movement_type' => 'required|in:OPENING,PURCHASE,TRANSFER,WASTE,ADJUST,SALE',
            'movement_quantity' => 'required|numeric',
            'movement_unit_price' => 'nullable|numeric|min:0',
            'from_department_id' => 'nullable|exists:departments,id',
            'to_department_id' => 'nullable|exists:departments,id',
            'movement_reason' => 'required_if:movement_type,WASTE,ADJUST|nullable|string',
        ]);

        $stock = Stock::find($this->selectedStockId);
        
        if (!$stock || !$stock->itemType) {
            session()->flash('error', 'Stock must have an item type.');
            return;
        }

        // Check if movement type is allowed for this item type
        if (!$stock->allowsMovementType($this->movement_type)) {
            session()->flash('error', "Movement type '{$this->movement_type}' is not allowed for item type '{$stock->itemType->name}'.");
            return;
        }

        // Validate negative stock (hard block)
        $newQuantity = $stock->quantity + $this->movement_quantity;
        if ($newQuantity < 0 && !$stock->canGoNegative()) {
            session()->flash('error', 'Cannot create negative stock. Current quantity: ' . $stock->quantity);
            return;
        }

        // Validate TRANSFER requires both departments
        if ($this->movement_type === 'TRANSFER') {
            if (!$this->from_department_id || !$this->to_department_id) {
                session()->flash('error', 'Transfer requires both source and destination departments.');
                return;
            }
        }

        // Resolve business date and shift
        $resolved = TimeAndShiftResolver::resolve();

        // Calculate total value
        $totalValue = $this->movement_quantity * ($this->movement_unit_price ?? $stock->unit_price ?? 0);

        // Create movement record
        $movement = StockMovement::create([
            'stock_id' => $stock->id,
            'movement_type' => $this->movement_type,
            'quantity' => $this->movement_quantity,
            'unit_price' => $this->movement_unit_price ?: $stock->unit_price,
            'total_value' => $totalValue,
            'from_department_id' => $this->from_department_id,
            'to_department_id' => $this->to_department_id,
            'reason' => $this->movement_reason,
            'user_id' => Auth::id(),
            'shift_id' => $resolved['shift_id'],
            'business_date' => $resolved['business_date'],
            'notes' => $this->movement_notes,
        ]);

        ActivityLogger::log(
            'stock.movement',
            sprintf(
                '%s: %s — qty %s (stock #%s)',
                $this->movement_type,
                $stock->name,
                $this->movement_quantity,
                $stock->id
            ),
            StockMovement::class,
            $movement->id,
            null,
            [
                'movement_type' => $this->movement_type,
                'stock_id' => $stock->id,
                'quantity' => $this->movement_quantity,
            ],
            ActivityLogModule::STOCK
        );

        // Update stock quantity
        $stock->quantity = $newQuantity;
        $stock->save();

        session()->flash('message', 'Stock movement recorded successfully!');
        $this->closeMovementForm();
        $this->loadStocks();
    }

    public function deleteStock($stockId)
    {
        if (!$this->canEditStockItems) {
            session()->flash('error', 'Only Super Admin or Manager can delete stock items.');
            return;
        }
        $stock = Stock::find($stockId);
        
        // Check if stock has movements
        $hasMovements = StockMovement::where('stock_id', $stockId)->exists();
        
        if ($hasMovements) {
            session()->flash('error', 'Cannot delete stock that has movement history.');
            return;
        }

        $stock->delete();
        session()->flash('message', 'Stock deleted successfully!');
        $this->loadStocks();
    }

    public function updatedFilterItemType()
    {
        $this->loadStocks();
    }

    public function updatedFilterDepartment()
    {
        $this->loadStocks();
    }

    public function updatedSearch()
    {
        $this->loadStocks();
    }

    public function openMainToSubstockTransfer($mainStockId)
    {
        $this->selectedMainStockId = $mainStockId;
        $mainStock = Stock::with('stockLocation')->find($mainStockId);
        
        if (!$mainStock || !$mainStock->stockLocation || !$mainStock->stockLocation->is_main_location) {
            session()->flash('error', 'Selected stock is not in a main location.');
            return;
        }
        
        $this->transfer_quantity = 0;
        $this->transfer_notes = '';
        $this->selectedSubstockLocationId = null;
        $this->showMainToSubstockTransferForm = true;
    }

    public function closeMainToSubstockTransferForm()
    {
        $this->showMainToSubstockTransferForm = false;
        $this->selectedMainStockId = null;
        $this->selectedSubstockLocationId = null;
        $this->transfer_quantity = 0;
        $this->transfer_notes = '';
    }

    public function saveMainToSubstockTransfer()
    {
        if (! $this->assertStoreOperationalShiftAllowed()) {
            return;
        }

        $this->validate([
            'selectedSubstockLocationId' => 'required|exists:stock_locations,id',
            'transfer_quantity' => 'required|numeric|min:0.01',
        ]);

        $mainStock = Stock::find($this->selectedMainStockId);
        $subLocation = StockLocation::find($this->selectedSubstockLocationId);

        if (!$mainStock) {
            session()->flash('error', 'Invalid main stock.');
            return;
        }

        if (!$subLocation || !$subLocation->isSubLocation() || $subLocation->parent_location_id != $mainStock->stockLocation->id) {
            session()->flash('error', 'Selected sub-location does not belong to this main location.');
            return;
        }

        // Check if main stock has enough quantity
        if ($mainStock->current_stock < $this->transfer_quantity) {
            session()->flash('error', 'Insufficient quantity in main stock. Available: ' . $mainStock->current_stock);
            return;
        }

        // Find or create stock item in the sub-location
        $subStock = Stock::where('name', $mainStock->name)
            ->where('stock_location_id', $subLocation->id)
            ->where('item_type_id', $mainStock->item_type_id)
            ->first();

        if (!$subStock) {
            // Create stock item in sub-location
            $subStock = Stock::create([
                'name' => $mainStock->name,
                'code' => $mainStock->code . '_' . $subLocation->code,
                'description' => $mainStock->description,
                'use_barcode' => $mainStock->use_barcode,
                'barcode' => null, // Different barcode for sub-location
                'item_type_id' => $mainStock->item_type_id,
                'package_unit' => $mainStock->package_unit,
                'qty_unit' => $mainStock->qty_unit,
                'purchase_price' => $mainStock->purchase_price,
                'sale_price' => $mainStock->sale_price,
                'tax_type' => $mainStock->tax_type,
                'beginning_stock_qty' => 0,
                'current_stock' => 0,
                'safety_stock' => $mainStock->safety_stock,
                'use_expiration' => $mainStock->use_expiration,
                'expiration_date' => $mainStock->expiration_date,
                'stock_location_id' => $subLocation->id,
                // Legacy fields
                'quantity' => 0,
                'unit' => $mainStock->qty_unit,
                'unit_price' => $mainStock->purchase_price,
            ]);
        }

        // Resolve business date and shift
        $resolved = TimeAndShiftResolver::resolve();

        // Create movement from main stock (OUT)
        StockMovement::create([
            'stock_id' => $mainStock->id,
            'movement_type' => 'TRANSFER',
            'quantity' => -$this->transfer_quantity,
            'unit_price' => $mainStock->purchase_price,
            'total_value' => -($this->transfer_quantity * $mainStock->purchase_price),
            'from_department_id' => $mainStock->department_id,
            'to_department_id' => $mainStock->department_id,
            'user_id' => Auth::id(),
            'shift_id' => $resolved['shift_id'],
            'business_date' => $resolved['business_date'],
            'notes' => 'Transfer to sub-location: ' . $subLocation->name . '. ' . $this->transfer_notes,
        ]);

        // Create movement to sub-stock (IN)
        StockMovement::create([
            'stock_id' => $subStock->id,
            'movement_type' => 'TRANSFER',
            'quantity' => $this->transfer_quantity,
            'unit_price' => $mainStock->purchase_price,
            'total_value' => $this->transfer_quantity * $mainStock->purchase_price,
            'from_department_id' => $mainStock->department_id,
            'to_department_id' => $mainStock->department_id,
            'user_id' => Auth::id(),
            'shift_id' => $resolved['shift_id'],
            'business_date' => $resolved['business_date'],
            'notes' => 'Transfer from main location: ' . $mainStock->stockLocation->name . '. ' . $this->transfer_notes,
        ]);

        // Update quantities
        $mainStock->current_stock -= $this->transfer_quantity;
        $mainStock->quantity = $mainStock->current_stock; // Sync legacy field
        $mainStock->save();

        $subStock->current_stock += $this->transfer_quantity;
        $subStock->quantity = $subStock->current_stock; // Sync legacy field
        $subStock->save();

        ActivityLogger::log(
            'stock.main_to_sub_transfer',
            sprintf(
                'Transferred %s %s from main to sub-location %s (main stock #%s → sub #%s)',
                $this->transfer_quantity,
                $mainStock->name,
                $subLocation->name,
                $mainStock->id,
                $subStock->id
            ),
            Stock::class,
            $mainStock->id,
            null,
            [
                'quantity' => $this->transfer_quantity,
                'sub_stock_id' => $subStock->id,
                'sub_location_id' => $subLocation->id,
            ],
            ActivityLogModule::STOCK
        );

        session()->flash('message', 'Stock transferred to sub-location successfully!');
        $this->closeMainToSubstockTransferForm();
        $this->loadStocks();
    }

    public function openExternalTransferForm($substockId)
    {
        $this->selectedSubstockId = $substockId;
        $substock = Stock::find($substockId);
        
        if (!$substock || !$substock->isSubstock()) {
            session()->flash('error', 'Selected stock is not a substock.');
            return;
        }
        
        $this->external_transfer_type = 'client';
        $this->recipient_name = '';
        $this->recipient_details = '';
        $this->external_transfer_items = [
            [
                'stock_id' => null,
                'quantity' => 0,
                'unit_price' => 0,
            ]
        ];
        $this->external_transfer_total = 0;
        
        $this->showExternalTransferForm = true;
    }

    public function closeExternalTransferForm()
    {
        $this->showExternalTransferForm = false;
        $this->selectedSubstockId = null;
        $this->external_transfer_type = 'client';
        $this->recipient_name = '';
        $this->recipient_details = '';
        $this->external_transfer_items = [];
        $this->external_transfer_total = 0;
    }

    public function addExternalTransferItem()
    {
        $this->external_transfer_items[] = [
            'stock_id' => null,
            'quantity' => 0,
            'unit_price' => 0,
        ];
    }

    public function removeExternalTransferItem($index)
    {
        unset($this->external_transfer_items[$index]);
        $this->external_transfer_items = array_values($this->external_transfer_items);
        $this->calculateExternalTransferTotal();
    }

    public function updatedExternalTransferItems()
    {
        $this->calculateExternalTransferTotal();
    }

    public function calculateExternalTransferTotal()
    {
        $this->external_transfer_total = 0;
        foreach ($this->external_transfer_items as $item) {
            if (isset($item['quantity']) && isset($item['unit_price'])) {
                $this->external_transfer_total += ($item['quantity'] * $item['unit_price']);
            }
        }
    }

    public function saveExternalTransfer()
    {
        if (! $this->assertStoreOperationalShiftAllowed()) {
            return;
        }

        $this->validate([
            'recipient_name' => 'required|string|max:255',
            'external_transfer_type' => 'required|in:client,event',
            'external_transfer_items' => 'required|array|min:1',
            'external_transfer_items.*.stock_id' => 'required|exists:stocks,id',
            'external_transfer_items.*.quantity' => 'required|numeric|min:0.01',
            'external_transfer_items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $substock = Stock::find($this->selectedSubstockId);
        
        if (!$substock || !$substock->isSubstock()) {
            session()->flash('error', 'Invalid substock.');
            return;
        }

        // Validate quantities and calculate total
        $items = [];
        $totalAmount = 0;
        $mainStock = Stock::find($substock->parent_stock_id);
        
        foreach ($this->external_transfer_items as $item) {
            $stock = Stock::find($item['stock_id']);
            
            // Check if stock belongs to the same parent stock (main stock)
            if (!$stock || $stock->parent_stock_id != $substock->parent_stock_id) {
                session()->flash('error', 'Item ' . ($stock->name ?? 'Unknown') . ' does not belong to this substock location.');
                return;
            }
            
            // Check if substock has enough quantity
            if ($stock->quantity < $item['quantity']) {
                session()->flash('error', 'Insufficient quantity for ' . $stock->name . '. Available: ' . $stock->quantity);
                return;
            }
            
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $totalAmount += $itemTotal;
            
            $items[] = [
                'stock_id' => $stock->id,
                'stock_name' => $stock->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $itemTotal,
            ];
        }

        // Resolve business date and shift
        $resolved = TimeAndShiftResolver::resolve();

        // Create external transfer record
        $externalTransfer = \App\Models\ExternalTransfer::create([
            'substock_id' => $substock->id,
            'transfer_type' => $this->external_transfer_type,
            'recipient_name' => $this->recipient_name,
            'recipient_details' => $this->recipient_details,
            'items' => $items,
            'total_amount' => $totalAmount,
            'user_id' => Auth::id(),
            'shift_id' => $resolved['shift_id'],
            'business_date' => $resolved['business_date'],
            'transfer_date' => now(),
            'notes' => $this->transfer_notes,
        ]);

        // Create stock movements and update quantities
        foreach ($items as $item) {
            $stock = Stock::find($item['stock_id']);
            
            // Create movement (OUT from substock)
            StockMovement::create([
                'stock_id' => $stock->id,
                'movement_type' => 'TRANSFER',
                'quantity' => -$item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_value' => -$item['total'],
                'from_department_id' => $stock->department_id,
                'user_id' => Auth::id(),
                'shift_id' => $resolved['shift_id'],
                'business_date' => $resolved['business_date'],
                'notes' => 'External transfer to ' . $this->external_transfer_type . ': ' . $this->recipient_name,
            ]);
            
            // Update stock quantity
            $stock->quantity -= $item['quantity'];
            $stock->save();
        }

        session()->flash('message', 'External transfer recorded successfully!');
        $this->closeExternalTransferForm();
        $this->loadStocks();
    }

    public function render()
    {
        return view('livewire.stock-management')->layout('livewire.layouts.app-layout');
    }
}
