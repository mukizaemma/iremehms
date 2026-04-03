<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\OperationalShiftActionGate;
use App\Services\StockRequestExecutionService;
use App\Support\ActivityLogModule;
use App\Services\TimeAndShiftResolver;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class GoodsReceipts extends Component
{
    use WithPagination, ChecksModuleStatus;

    public $receipts = [];
    public $showReceiptForm = false;
    public $editingReceiptId = null;
    public $selectedRequisitionId = null;
    
    // Form fields
    public $requisition_id = null;
    public $supplier_id = null;
    public $department_id = null;
    public $receipt_status = 'COMPLETE';
    public $notes = '';
    
    // Receipt items
    public $receiptItems = [];
    
    // Filters
    public $filter_status = '';
    public $filter_supplier = '';
    public $search = '';
    
    public $suppliers = [];
    public $departments = [];
    public $requisitions = [];
    public $stockLocations = [];
    public $stocks = [];

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');
        
        // Check access: Store Keeper, Manager, Super Admin
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->isManager() && !$this->isStoreKeeper($user)) {
            abort(403, 'Unauthorized access. Only Store Keeper, Manager, and Super Admin can access Goods Receipts.');
        }
        
        $this->loadData();
    }
    
    private function isStoreKeeper($user)
    {
        return $user->hasModuleAccess(\App\Models\Module::where('slug', 'store')->first()?->id ?? 0);
    }

    public function loadData()
    {
        $this->suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $deptQuery = Department::where('is_active', true);
        if (!empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->orderBy('name')->get();
        $this->stockLocations = StockLocation::where('is_active', true)->orderBy('name')->get();
        $this->stocks = Stock::with(['itemType', 'stockLocation'])->orderBy('name')->get();
        $this->requisitions = PurchaseRequisition::where('status', 'APPROVED')
            ->whereDoesntHave('goodsReceipts', function($query) {
                $query->where('receipt_status', 'COMPLETE');
            })
            ->with(['supplier', 'items.item'])
            ->orderBy('created_at', 'desc')
            ->get();
        $this->loadReceipts();
    }

    public function loadReceipts()
    {
        $query = GoodsReceipt::with(['supplier', 'receivedBy', 'department', 'requisition', 'items.item', 'items.location']);
        
        // Filter by status
        if ($this->filter_status) {
            $query->where('receipt_status', $this->filter_status);
        }
        
        // Filter by supplier
        if ($this->filter_supplier) {
            $query->where('supplier_id', $this->filter_supplier);
        }
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('supplier', function($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhere('notes', 'like', '%' . $this->search . '%');
            });
        }
        
        $this->receipts = $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    public function openReceiptForm($requisitionId = null)
    {
        $this->editingReceiptId = null;
        $this->selectedRequisitionId = $requisitionId;
        $this->receiptItems = [];
        
        if ($requisitionId) {
            $requisition = PurchaseRequisition::with('items.item')->find($requisitionId);
            
            if (!$requisition || $requisition->status !== 'APPROVED') {
                session()->flash('error', 'Selected requisition is not approved or does not exist.');
                return;
            }
            
            $this->requisition_id = $requisitionId;
            $this->supplier_id = $requisition->supplier_id;
            $this->department_id = $requisition->department_id;
            
            // Pre-populate items from requisition (amount = received qty × unit cost; update cost if changed)
            foreach ($requisition->items as $item) {
                $qty = (float) $item->quantity_requested;
                $cost = (float) ($item->estimated_unit_cost ?? $item->item->purchase_price ?? 0);
                $expiryDate = ($item->item && ($item->item->use_expiration ?? false)) ? $item->item->expiration_date : null;
                $this->receiptItems[] = [
                    'item_id' => $item->item_id,
                    'location_id' => $item->item->stock_location_id ?? null,
                    'quantity_requested' => $qty,
                    'quantity_received' => $qty, // Default to requested; user can change
                    'unit_id' => $item->unit_id ?? '',
                    'unit_cost' => $cost,
                    'total_cost' => round($qty * $cost, 2),
                    'expiry_date' => $expiryDate,
                    'notes' => $item->notes ?? '',
                    'purchase_requisition_item_id' => $item->stock_request_item_id ? $item->line_id : null,
                ];
            }
            $this->recalculateReceiptItemTotals();
        } else {
            $this->resetForm();
        }
        
        $this->showReceiptForm = true;
    }

    public function closeReceiptForm()
    {
        $this->showReceiptForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingReceiptId = null;
        $this->selectedRequisitionId = null;
        $this->requisition_id = null;
        $this->supplier_id = null;
        // Default to current user's department if it's enabled for the hotel; otherwise leave empty.
        $userDeptId = Auth::user()->department_id;
        $enabledDeptIds = $this->departments ? array_map(fn ($d) => (int) ($d->id ?? 0), $this->departments) : [];
        $this->department_id = in_array((int) $userDeptId, $enabledDeptIds, true) ? (int) $userDeptId : null;
        $this->receipt_status = 'COMPLETE';
        $this->notes = '';
        $this->receiptItems = [];
    }

    public function addReceiptItem()
    {
        $this->receiptItems[] = [
            'item_id' => null,
            'location_id' => null,
            'quantity_requested' => 0,
            'quantity_received' => 0,
            'unit_id' => '',
            'unit_cost' => 0,
            'total_cost' => 0,
            'expiry_date' => null,
            'notes' => '',
            'purchase_requisition_item_id' => null,
        ];
    }

    public function removeReceiptItem($index)
    {
        unset($this->receiptItems[$index]);
        $this->receiptItems = array_values($this->receiptItems);
    }

    /**
     * Recalculate total_cost for all receipt items (quantity_received * unit_cost).
     * Call when opening form, when qty/cost changes, and before save.
     */
    public function recalculateReceiptItemTotals(): void
    {
        foreach ($this->receiptItems as $index => $item) {
            $qty = (float) ($item['quantity_received'] ?? 0);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $this->receiptItems[$index]['total_cost'] = round($qty * $cost, 2);
        }
    }

    public function updatedReceiptItems($value, $key)
    {
        if (!is_string($key)) {
            return;
        }

        // When selecting an item, fill derived fields (expiry + default unit/location when possible).
        if (str_contains($key, 'item_id')) {
            // Key format: receiptItems.{index}.item_id
            $parts = explode('.', $key);
            $index = null;
            if (count($parts) >= 3 && is_numeric($parts[1])) {
                $index = (int) $parts[1];
            } elseif (count($parts) === 2 && is_numeric($parts[0])) {
                $index = (int) $parts[0];
            }

            if ($index !== null && isset($this->receiptItems[$index])) {
                $itemId = (int) ($this->receiptItems[$index]['item_id'] ?? 0);
                $stock = $itemId > 0 ? collect($this->stocks)->firstWhere('id', $itemId) : null;

                $this->receiptItems[$index]['location_id'] = $stock?->stock_location_id ?? $this->receiptItems[$index]['location_id'];
                $this->receiptItems[$index]['expiry_date'] = ($stock && ($stock->use_expiration ?? false)) ? $stock->expiration_date : null;

                if (empty($this->receiptItems[$index]['unit_id'])) {
                    $this->receiptItems[$index]['unit_id'] = $stock?->qty_unit ?? $stock?->unit ?? '';
                }
            }

            return;
        }

        // Recalculate line total when quantity_received or unit_cost changes.
        if (!str_contains($key, 'quantity_received') && !str_contains($key, 'unit_cost')) {
            return;
        }

        $parts = explode('.', $key);
        $index = null;
        if (count($parts) === 2 && is_numeric($parts[0])) {
            $index = (int) $parts[0];
        } elseif (count($parts) >= 3 && is_numeric($parts[1] ?? null)) {
            $index = (int) $parts[1];
        }
        if ($index !== null && isset($this->receiptItems[$index])) {
            $qty = (float) ($this->receiptItems[$index]['quantity_received'] ?? 0);
            $cost = (float) ($this->receiptItems[$index]['unit_cost'] ?? 0);
            $this->receiptItems[$index]['total_cost'] = round($qty * $cost, 2);
        } else {
            $this->recalculateReceiptItemTotals();
        }
    }

    public function saveReceipt()
    {
        // Ensure all line amounts are recalculated from received qty × unit cost before save
        $this->recalculateReceiptItemTotals();

        $this->validate([
            'supplier_id' => 'required|exists:suppliers,supplier_id',
            'department_id' => 'required|exists:departments,id',
            'receiptItems' => 'required|array|min:1',
            'receiptItems.*.item_id' => 'required|exists:stocks,id',
            'receiptItems.*.location_id' => 'required|exists:stock_locations,id',
            'receiptItems.*.quantity_received' => 'required|numeric|min:0.01',
            'receiptItems.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $hotel = Hotel::getHotel();
        try {
            OperationalShiftActionGate::assertStoreActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        // Resolve business date and shift
        $resolved = TimeAndShiftResolver::resolve();

        DB::beginTransaction();
        try {
            // Create goods receipt
            $receipt = GoodsReceipt::create([
                'requisition_id' => $this->requisition_id,
                'supplier_id' => $this->supplier_id,
                'received_by' => Auth::id(),
                'department_id' => $this->department_id,
                'business_date' => $resolved['business_date'],
                'shift_id' => $resolved['shift_id'],
                'receipt_status' => $this->receipt_status,
                'notes' => $this->notes,
            ]);

            // Create receipt items and update stock
            foreach ($this->receiptItems as $itemData) {
                $stock = Stock::find($itemData['item_id']);
                
                // Calculate total cost in purchase units
                $totalCost = $itemData['quantity_received'] * $itemData['unit_cost'];

                // Convert received quantity and unit cost to base (qty_unit) using package_size if defined
                $packageSize = $stock->package_size && $stock->package_size > 0 ? (float) $stock->package_size : 1.0;
                $receivedBaseQty = (float) $itemData['quantity_received'] * $packageSize;
                $baseUnitPrice = $packageSize > 0 ? ((float) $itemData['unit_cost'] / $packageSize) : (float) $itemData['unit_cost'];
                
                // Create receipt item (keeps original purchase-unit quantity and cost)
                $receiptItem = GoodsReceiptItem::create([
                    'receipt_id' => $receipt->receipt_id,
                    'item_id' => $itemData['item_id'],
                    'location_id' => $itemData['location_id'],
                    'quantity_received' => $itemData['quantity_received'],
                    'unit_id' => $itemData['unit_id'] ?? null,
                    'unit_cost' => $itemData['unit_cost'],
                    'total_cost' => $totalCost,
                    'notes' => $itemData['notes'] ?? null,
                    'purchase_requisition_item_id' => $itemData['purchase_requisition_item_id'] ?? null,
                ]);

                // Update stock quantity (always in base units)
                $stock->current_stock += $receivedBaseQty;
                $stock->quantity = $stock->current_stock; // Sync legacy field
                $stock->save();

                // Create PURCHASE stock movement in base units
                StockMovement::create([
                    'stock_id' => $stock->id,
                    'movement_type' => 'PURCHASE',
                    'quantity' => $receivedBaseQty,
                    'unit_price' => $baseUnitPrice,
                    'total_value' => $totalCost,
                    'from_department_id' => null,
                    'to_department_id' => $this->department_id,
                    'user_id' => Auth::id(),
                    'shift_id' => $resolved['shift_id'],
                    'business_date' => $resolved['business_date'],
                    'notes' => 'Goods receipt #' . $receipt->receipt_id . ($itemData['notes'] ? '. ' . $itemData['notes'] : ''),
                    'goods_receipt_item_id' => $receiptItem->line_id,
                ]);

                // If this receipt line came from a stock-request requisition, issue to requested location after stock is updated
                if (!empty($receiptItem->purchase_requisition_item_id)) {
                    $pri = PurchaseRequisitionItem::with('stockRequestItem.stockRequest')->find($receiptItem->purchase_requisition_item_id);
                    if ($pri && $pri->stockRequestItem && $pri->stockRequestItem->issue_status === 'on_requisition') {
                        try {
                            StockRequestExecutionService::issueSingleItem($pri->stockRequestItem);
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            session()->flash('error', 'Stock updated but issue to requested location failed: ' . $e->getMessage());
                            return;
                        }
                    }
                }
            }

            DB::commit();

            ActivityLogger::log(
                'stock.goods_receipt',
                sprintf(
                    'Goods receipt #%s recorded (%d line(s)).',
                    $receipt->receipt_id,
                    count($this->receiptItems)
                ),
                GoodsReceipt::class,
                (int) $receipt->receipt_id,
                null,
                [
                    'supplier_id' => $this->supplier_id,
                    'department_id' => $this->department_id,
                    'lines' => count($this->receiptItems),
                ],
                ActivityLogModule::STOCK
            );

            session()->flash('message', 'Goods receipt confirmed and stock updated successfully!');
            $this->closeReceiptForm();
            $this->loadReceipts();
            $this->loadData(); // Reload requisitions
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error processing goods receipt: ' . $e->getMessage());
        }
    }

    public function updatedFilterStatus()
    {
        $this->loadReceipts();
    }

    public function updatedFilterSupplier()
    {
        $this->loadReceipts();
    }

    public function updatedSearch()
    {
        $this->loadReceipts();
    }

    public function render()
    {
        return view('livewire.goods-receipts')
            ->layout('livewire.layouts.app-layout');
    }
}
