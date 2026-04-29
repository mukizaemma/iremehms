<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Hotel;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\OperationalShiftActionGate;
use App\Services\StockRequestExecutionService;
use App\Services\TimeAndShiftResolver;
use App\Support\ActivityLogModule;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class GoodsReceipts extends Component
{
    use ChecksModuleStatus, WithPagination;

    public $receipts = [];

    public $showReceiptForm = false;

    public $editingReceiptId = null;

    /** True when viewing a posted receipt (read-only). */
    public bool $receiptReadOnly = false;

    public $selectedRequisitionId = null;

    // Form fields
    public $requisition_id = null;

    public $supplier_id = null;

    public $department_id = null;

    public $receipt_status = 'COMPLETE';

    public $notes = '';

    // Receipt items
    public $receiptItems = [];

    /** Parallel to receiptItems: search text to filter the item dropdown per row */
    public array $receiptItemSearch = [];

    // Filters
    public $filter_status = '';

    public $filter_supplier = '';

    public $search = '';

    public $suppliers = [];

    public $departments = [];

    public $requisitions = [];

    public $stockLocations = [];

    public $stocks = [];

    /** Count of GRNs in DRAFT (not filtered; for list prompts). */
    public int $draftReceiptCount = 0;

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');

        // Check access: Store Keeper, Manager, Super Admin
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isManager() && ! $this->isStoreKeeper($user)) {
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
        if (! empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->orderBy('name')->get();
        $this->stockLocations = StockLocation::where('is_active', true)->orderBy('name')->get();
        $this->stocks = Stock::with(['itemType', 'stockLocation'])->orderBy('name')->get(); // Collection (used for searchable lines)
        // Exclude requisitions that already have a completed or in-progress (draft) goods receipt
        $this->requisitions = PurchaseRequisition::where('status', 'APPROVED')
            ->whereDoesntHave('goodsReceipts', function ($query) {
                $query->whereIn('receipt_status', ['COMPLETE', 'DRAFT']);
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
            $query->where(function ($q) {
                $q->whereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%');
                })
                    ->orWhere('notes', 'like', '%'.$this->search.'%');
            });
        }

        $this->receipts = $query
            ->orderByRaw("CASE WHEN receipt_status = 'DRAFT' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        $this->draftReceiptCount = GoodsReceipt::where('receipt_status', 'DRAFT')->count();
    }

    public function openReceiptForm($requisitionId = null)
    {
        $this->editingReceiptId = null;
        $this->receiptReadOnly = false;
        $this->selectedRequisitionId = $requisitionId;
        $this->receiptItems = [];

        if ($requisitionId) {
            $requisition = PurchaseRequisition::with('items.item')->find($requisitionId);

            if (! $requisition || $requisition->status !== 'APPROVED') {
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
                $this->receiptItemSearch[] = $item->item ? (string) $item->item->name : '';
            }
            $this->recalculateReceiptItemTotals();
        } else {
            $this->resetForm();
        }

        $this->showReceiptForm = true;
    }

    /**
     * Open an existing receipt from the list (draft = editable, posted = read-only).
     */
    public function openReceiptForEdit(int $receiptId): void
    {
        $receipt = GoodsReceipt::with(['items.item', 'requisition.items'])->find($receiptId);
        if (! $receipt) {
            session()->flash('error', 'Receipt not found.');

            return;
        }

        $requestedByPrLine = [];
        if ($receipt->requisition) {
            foreach ($receipt->requisition->items as $ri) {
                $requestedByPrLine[(int) $ri->line_id] = (float) $ri->quantity_requested;
            }
        }

        $this->editingReceiptId = $receipt->receipt_id;
        $this->requisition_id = $receipt->requisition_id;
        $this->supplier_id = $receipt->supplier_id;
        $this->department_id = $receipt->department_id;
        $this->receipt_status = $receipt->receipt_status === 'DRAFT' ? 'COMPLETE' : $receipt->receipt_status;
        $this->notes = $receipt->notes ?? '';
        $this->selectedRequisitionId = $receipt->requisition_id;
        $this->receiptReadOnly = $receipt->receipt_status !== 'DRAFT';
        $this->receiptItems = [];
        $this->receiptItemSearch = [];

        foreach ($receipt->items as $line) {
            $prItemId = $line->purchase_requisition_item_id;
            $qtyRequested = ($prItemId && isset($requestedByPrLine[(int) $prItemId]))
                ? $requestedByPrLine[(int) $prItemId]
                : 0.0;

            $this->receiptItems[] = [
                'item_id' => $line->item_id,
                'location_id' => $line->location_id,
                'quantity_requested' => $qtyRequested,
                'quantity_received' => (float) $line->quantity_received,
                'unit_id' => $line->unit_id ?? '',
                'unit_cost' => (float) $line->unit_cost,
                'total_cost' => (float) $line->total_cost,
                'expiry_date' => null,
                'notes' => $line->notes ?? '',
                'purchase_requisition_item_id' => $line->purchase_requisition_item_id,
            ];
            $this->receiptItemSearch[] = $line->item ? (string) $line->item->name : '';
        }
        $this->recalculateReceiptItemTotals();
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
        $enabledDeptIds = collect($this->departments ?? [])->map(fn ($d) => (int) (is_array($d) ? ($d['id'] ?? 0) : ($d->id ?? 0)))->all();
        $this->department_id = in_array((int) $userDeptId, $enabledDeptIds, true) ? (int) $userDeptId : null;
        $this->receipt_status = 'COMPLETE';
        $this->notes = '';
        $this->receiptItems = [];
        $this->receiptItemSearch = [];
        $this->receiptReadOnly = false;
    }

    /**
     * Stocks shown in the item dropdown for one line (filtered by receiptItemSearch for that index).
     */
    public function stocksForReceiptRow(int $index): Collection
    {
        $stocks = collect($this->stocks);
        $q = mb_strtolower(trim((string) ($this->receiptItemSearch[$index] ?? '')));
        if ($q === '') {
            return $stocks->take(100)->values();
        }

        return $stocks->filter(function ($s) use ($q) {
            $name = mb_strtolower((string) (is_array($s) ? ($s['name'] ?? '') : ($s->name ?? '')));
            $code = mb_strtolower((string) (is_array($s) ? ($s['code'] ?? '') : ($s->code ?? '')));
            $barcode = mb_strtolower((string) (is_array($s) ? ($s['barcode'] ?? '') : ($s->barcode ?? '')));

            return str_contains($name, $q)
                || ($code !== '' && str_contains($code, $q))
                || ($barcode !== '' && str_contains($barcode, $q));
        })->take(150)->values();
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
        $this->receiptItemSearch[] = '';
    }

    public function removeReceiptItem($index)
    {
        unset($this->receiptItems[$index]);
        unset($this->receiptItemSearch[$index]);
        $this->receiptItems = array_values($this->receiptItems);
        $this->receiptItemSearch = array_values($this->receiptItemSearch);
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
        if (! is_string($key)) {
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
                $stock = $itemId > 0 ? Stock::find($itemId) : null;

                // Location comes from the stock master record (not chosen per line in the form).
                $this->receiptItems[$index]['location_id'] = $stock?->stock_location_id ?? ($this->receiptItems[$index]['location_id'] ?? null);
                $this->receiptItems[$index]['expiry_date'] = ($stock && ($stock->use_expiration ?? false)) ? $stock->expiration_date : null;

                if (empty($this->receiptItems[$index]['unit_id'])) {
                    $pkg = (string) ($stock->package_unit ?? '');
                    $this->receiptItems[$index]['unit_id'] = $pkg !== ''
                        ? $pkg
                        : (string) ($stock->qty_unit ?? $stock->unit ?? '');
                }

                if ($stock) {
                    $this->receiptItemSearch[$index] = (string) $stock->name;
                }
            }

            return;
        }

        // Recalculate line total when quantity_received or unit_cost changes.
        if (! str_contains($key, 'quantity_received') && ! str_contains($key, 'unit_cost')) {
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

    /**
     * Save as draft: persists header + lines only; does not change stock levels or create movements.
     */
    public function saveDraft(): void
    {
        if ($this->receiptReadOnly) {
            return;
        }

        $this->recalculateReceiptItemTotals();
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,supplier_id',
            'department_id' => 'required|exists:departments,id',
            'receiptItems' => 'required|array|min:1',
            'receiptItems.*.item_id' => 'required|exists:stocks,id',
            'receiptItems.*.quantity_received' => 'required|numeric|min:0',
            'receiptItems.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $hasLine = collect($this->receiptItems)->contains(fn (array $row) => (float) ($row['quantity_received'] ?? 0) > 0);
        if (! $hasLine) {
            $this->addError('receiptItems', 'Add at least one line with received quantity greater than zero.');

            return;
        }

        $hotel = Hotel::getHotel();
        try {
            OperationalShiftActionGate::assertStoreActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $resolved = TimeAndShiftResolver::resolve();

        DB::beginTransaction();
        try {
            if ($this->editingReceiptId) {
                $receipt = GoodsReceipt::lockForUpdate()->findOrFail($this->editingReceiptId);
                if ($receipt->receipt_status !== 'DRAFT') {
                    throw new \RuntimeException('Only draft receipts can be updated as a draft.');
                }
                $receipt->update([
                    'requisition_id' => $this->requisition_id,
                    'supplier_id' => $this->supplier_id,
                    'department_id' => $this->department_id,
                    'business_date' => $resolved['business_date'],
                    'shift_id' => $resolved['shift_id'],
                    'receipt_status' => 'DRAFT',
                    'notes' => $this->notes,
                ]);
                GoodsReceiptItem::where('receipt_id', $receipt->receipt_id)->delete();
            } else {
                $receipt = GoodsReceipt::create([
                    'requisition_id' => $this->requisition_id,
                    'supplier_id' => $this->supplier_id,
                    'received_by' => Auth::id(),
                    'department_id' => $this->department_id,
                    'business_date' => $resolved['business_date'],
                    'shift_id' => $resolved['shift_id'],
                    'receipt_status' => 'DRAFT',
                    'notes' => $this->notes,
                ]);
                $this->editingReceiptId = $receipt->receipt_id;
            }

            foreach ($this->receiptItems as $itemData) {
                if ((float) ($itemData['quantity_received'] ?? 0) <= 0) {
                    continue;
                }
                $this->appendGoodsReceiptLine($receipt, $itemData, $resolved, false);
            }

            DB::commit();

            ActivityLogger::log(
                'stock.goods_receipt_draft',
                sprintf('Goods receipt #%s saved as draft.', $receipt->receipt_id),
                GoodsReceipt::class,
                (int) $receipt->receipt_id,
                null,
                ['supplier_id' => $this->supplier_id, 'department_id' => $this->department_id],
                ActivityLogModule::STOCK
            );

            session()->flash('message', 'Draft saved. It appears in the list below—use “Finish receipt” to confirm and update stock.');
            $this->closeReceiptForm();
            $this->loadReceipts();
            $this->loadData();
        } catch (\Throwable $e) {
            DB::rollBack();
            session()->flash('error', 'Could not save draft: '.$e->getMessage());
        }
    }

    /**
     * Confirm receipt: posts lines to stock (PURCHASE movements). New receipts or finalizing a draft.
     */
    public function confirmReceipt(): void
    {
        if ($this->receiptReadOnly) {
            return;
        }

        $this->recalculateReceiptItemTotals();
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,supplier_id',
            'department_id' => 'required|exists:departments,id',
            'receipt_status' => 'required|in:PARTIAL,COMPLETE',
            'receiptItems' => 'required|array|min:1',
            'receiptItems.*.item_id' => 'required|exists:stocks,id',
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

        $resolved = TimeAndShiftResolver::resolve();

        DB::beginTransaction();
        try {
            if ($this->editingReceiptId) {
                $receipt = GoodsReceipt::lockForUpdate()->findOrFail($this->editingReceiptId);
                if ($receipt->receipt_status !== 'DRAFT') {
                    throw new \RuntimeException('Only a draft receipt can be confirmed. Posted receipts cannot be re-confirmed.');
                }
                $receipt->update([
                    'requisition_id' => $this->requisition_id,
                    'supplier_id' => $this->supplier_id,
                    'department_id' => $this->department_id,
                    'business_date' => $resolved['business_date'],
                    'shift_id' => $resolved['shift_id'],
                    'receipt_status' => $this->receipt_status,
                    'notes' => $this->notes,
                ]);
                GoodsReceiptItem::where('receipt_id', $receipt->receipt_id)->delete();
            } else {
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
            }

            foreach ($this->receiptItems as $itemData) {
                if ((float) ($itemData['quantity_received'] ?? 0) < 0.01) {
                    continue;
                }
                $this->appendGoodsReceiptLine($receipt, $itemData, $resolved, true);
            }

            DB::commit();

            ActivityLogger::log(
                'stock.goods_receipt',
                sprintf(
                    'Goods receipt #%s confirmed (%d line(s)); stock updated.',
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
            $this->loadData();
        } catch (\Throwable $e) {
            DB::rollBack();
            session()->flash('error', 'Error confirming goods receipt: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $resolved  from TimeAndShiftResolver::resolve()
     */
    protected function appendGoodsReceiptLine(GoodsReceipt $receipt, array $itemData, array $resolved, bool $postToStock): void
    {
        $stock = Stock::findOrFail($itemData['item_id']);

        $locationId = (int) ($itemData['location_id'] ?? 0);
        if ($locationId <= 0) {
            $locationId = (int) ($stock->stock_location_id ?? 0);
        }
        if ($locationId <= 0) {
            $locationId = (int) (StockLocation::where('is_active', true)->where('is_main_location', true)->orderBy('name')->value('id') ?? 0);
        }
        if ($locationId <= 0) {
            throw new \RuntimeException('No stock location is configured. Add a main stock location first.');
        }

        $totalCost = (float) $itemData['quantity_received'] * (float) $itemData['unit_cost'];

        $packageSize = $stock->package_size && $stock->package_size > 0 ? (float) $stock->package_size : 1.0;
        $receivedBaseQty = (float) $itemData['quantity_received'] * $packageSize;
        $baseUnitPrice = $packageSize > 0 ? ((float) $itemData['unit_cost'] / $packageSize) : (float) $itemData['unit_cost'];

        $receiptItem = GoodsReceiptItem::create([
            'receipt_id' => $receipt->receipt_id,
            'item_id' => $itemData['item_id'],
            'location_id' => $locationId,
            'quantity_received' => $itemData['quantity_received'],
            'unit_id' => $itemData['unit_id'] ?? null,
            'unit_cost' => $itemData['unit_cost'],
            'total_cost' => $totalCost,
            'notes' => $itemData['notes'] ?? null,
            'purchase_requisition_item_id' => $itemData['purchase_requisition_item_id'] ?? null,
        ]);

        if (! $postToStock) {
            return;
        }

        $stock->current_stock += $receivedBaseQty;
        $stock->quantity = $stock->current_stock;
        $stock->save();

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
            'notes' => 'Goods receipt #'.$receipt->receipt_id.($itemData['notes'] ? '. '.$itemData['notes'] : ''),
            'goods_receipt_item_id' => $receiptItem->line_id,
        ]);

        if (! empty($receiptItem->purchase_requisition_item_id)) {
            $pri = PurchaseRequisitionItem::with('stockRequestItem.stockRequest')->find($receiptItem->purchase_requisition_item_id);
            if ($pri && $pri->stockRequestItem && $pri->stockRequestItem->issue_status === 'on_requisition') {
                StockRequestExecutionService::issueSingleItem($pri->stockRequestItem);
            }
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
