<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Supplier;
use App\Services\StockRequestExecutionService;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockOut extends Component
{
    use ChecksModuleStatus;

    public $showAddToRequisitionModal = false;
    public $selectedItemIds = [];
    public $requisition_supplier_id = null;
    public $requisition_department_id = null;
    public $requisition_notes = '';

    public function mount()
    {
        $this->ensureModuleEnabled('store');
    }

    public function getPendingRequestsProperty()
    {
        return StockRequest::with(['requestedBy', 'items.stock.stockLocation', 'toStockLocation', 'toDepartment'])
            ->where('status', StockRequest::STATUS_APPROVED)
            ->whereIn('type', [
                StockRequest::TYPE_TRANSFER_SUBSTOCK,
                StockRequest::TYPE_ISSUE_DEPARTMENT,
                StockRequest::TYPE_TRANSFER_DEPARTMENT,
            ])
            ->orderBy('approved_at')
            ->get()
            ->filter(function ($req) {
                return $req->items->contains(fn ($i) => $i->isPendingIssue() && !$i->isOnRequisition());
            });
    }

    public function getSuppliersProperty()
    {
        return Supplier::where('is_active', true)->orderBy('name')->get();
    }

    public function getDepartmentsProperty()
    {
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $deptQuery = Department::where('is_active', true);
        if (!empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }

        return $deptQuery->orderBy('name')->get();
    }

    public function issueItem($itemId)
    {
        $item = StockRequestItem::with('stockRequest')->find($itemId);
        if (!$item || !$item->isPendingIssue() || $item->isOnRequisition()) {
            session()->flash('error', 'Item not found or not available to issue.');
            return;
        }
        try {
            $ok = StockRequestExecutionService::issueSingleItem($item);
            if ($ok) {
                session()->flash('message', 'Item issued successfully.');
            } else {
                session()->flash('error', 'Could not issue: check stock availability.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    public function issueAllForRequest($requestId)
    {
        $request = StockRequest::with('items')->find($requestId);
        if (!$request || !$request->isApproved()) {
            session()->flash('error', 'Request not found.');
            return;
        }
        $issued = 0;
        foreach ($request->items as $item) {
            if (!$item->isPendingIssue() || $item->isOnRequisition()) {
                continue;
            }
            try {
                if (StockRequestExecutionService::issueSingleItem($item)) {
                    $issued++;
                }
            } catch (\Throwable $e) {
                session()->flash('error', 'Stopped: ' . $e->getMessage());
                break;
            }
        }
        if ($issued > 0) {
            session()->flash('message', "Issued {$issued} item(s).");
        }
    }

    public function openAddToRequisitionModal($itemIds = [])
    {
        $this->selectedItemIds = is_array($itemIds) ? $itemIds : [$itemIds];
        $this->requisition_supplier_id = null;
        $userDeptId = (int) (Auth::user()->department_id ?? 0);
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        $this->requisition_department_id = in_array($userDeptId, $enabledDepartments, true) ? $userDeptId : null;
        $this->requisition_notes = '';
        $this->showAddToRequisitionModal = true;
    }

    public function closeAddToRequisitionModal()
    {
        $this->showAddToRequisitionModal = false;
        $this->selectedItemIds = [];
    }

    public function addToRequisition()
    {
        $this->validate([
            'requisition_department_id' => 'required|exists:departments,id',
            'selectedItemIds' => 'required|array|min:1',
            'selectedItemIds.*' => 'exists:stock_request_items,id',
        ]);
        $items = StockRequestItem::with('stockRequest', 'stock')->findMany($this->selectedItemIds);
        $items = $items->filter(fn ($i) => $i->isPendingIssue() && !$i->isOnRequisition());
        if ($items->isEmpty()) {
            session()->flash('error', 'No valid items to add to requisition.');
            return;
        }
        $resolved = \App\Services\TimeAndShiftResolver::resolve();
        $pr = PurchaseRequisition::create([
            'supplier_id' => $this->requisition_supplier_id,
            'requested_by' => Auth::id(),
            'department_id' => $this->requisition_department_id,
            'status' => 'SUBMITTED',
            'business_date' => $resolved['business_date'],
            'shift_id' => $resolved['shift_id'],
            'notes' => 'From Stock Out (requested items not in stock). ' . $this->requisition_notes,
        ]);
        foreach ($items as $sri) {
            $remaining = (float) $sri->quantity - (float) ($sri->quantity_issued ?? 0);
            if ($remaining <= 0) {
                continue;
            }
            $pri = PurchaseRequisitionItem::create([
                'requisition_id' => $pr->requisition_id,
                'item_id' => $sri->stock_id,
                'quantity_requested' => $remaining,
                'unit_id' => $sri->stock->qty_unit ?? $sri->stock->unit,
                'estimated_unit_cost' => $sri->stock->purchase_price,
                'notes' => 'Stock request #' . $sri->stock_request_id,
                'stock_request_item_id' => $sri->id,
            ]);
            $sri->update([
                'issue_status' => 'on_requisition',
                'purchase_requisition_item_id' => $pri->line_id,
            ]);
        }
        session()->flash('message', 'Items added to purchase requisition. Awaiting approval.');
        $this->closeAddToRequisitionModal();
    }

    public function getAvailableQuantity(StockRequestItem $item): float
    {
        $stock = Stock::find($item->stock_id);
        return $stock ? (float) ($stock->current_stock ?? $stock->quantity ?? 0) : 0;
    }

    public function render()
    {
        return view('livewire.stock-out')->layout('livewire.layouts.app-layout');
    }
}
