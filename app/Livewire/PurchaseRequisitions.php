<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseRequisitionComment;
use App\Models\PurchaseApproval;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\TimeAndShiftResolver;
use App\Support\ActivityLogModule;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseRequisitions extends Component
{
    use WithPagination, ChecksModuleStatus;

    public $requisitions = [];
    public $showRequisitionForm = false;
    public $editingRequisitionId = null;
    public $showApprovalModal = false;
    public $selectedRequisitionId = null;

    // View approved requisition (read-only, print, share)
    public $showViewRequisitionId = null;
    public $viewRequisitionData = null;

    // Comments
    public $showCommentsRequisitionId = null;
    public $commentsForModal = [];
    public $commentBody = '';
    public $editingCommentId = null;
    public $editCommentBody = '';
    
    // Form fields
    public $supplier_id = null;
    public $department_id = null;
    public $notes = '';
    public $status = 'DRAFT';
    
    // Requisition items
    public $requisitionItems = [];
    
    // Approval fields
    public $approval_action = 'APPROVE'; // APPROVE, REQUEST_MODIFICATION, REQUEST_CLARIFICATION
    public $approval_notes = '';
    
    // Filters
    public $filter_status = '';
    public $filter_department = '';
    public $search = '';
    
    public $suppliers = [];
    public $departments = [];
    public $stocks = [];

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');
        
        // Check access: Store Keeper, Manager, Super Admin
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->isManager() && !$this->isStoreKeeper($user)) {
            abort(403, 'Unauthorized access. Only Store Keeper, Manager, and Super Admin can access Purchase Requisitions.');
        }
        
        $this->loadData();
    }
    
    private function isStoreKeeper($user)
    {
        // Check if user has store module access and is in a store-related role
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
        $this->stocks = Stock::with(['itemType', 'stockLocation'])->orderBy('name')->get();
        $this->loadRequisitions();
    }

    public function loadRequisitions()
    {
        $query = PurchaseRequisition::with(['supplier', 'requestedBy', 'department', 'items.item', 'approval.approvedBy']);
        
        // Filter by status
        if ($this->filter_status) {
            $query->where('status', $this->filter_status);
        }
        
        // Filter by department
        if ($this->filter_department) {
            $query->where('department_id', $this->filter_department);
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
        
        // Access control: Store Keeper can see requisitions with no department or their department
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->isManager() && $this->isStoreKeeper($user)) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('department_id')->orWhere('department_id', $user->department_id);
            });
        }
        
        $this->requisitions = $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    public function openRequisitionForm($requisitionId = null)
    {
        $this->editingRequisitionId = $requisitionId;
        
        if ($requisitionId) {
            $requisition = PurchaseRequisition::with(['items.item.stockLocation'])->find($requisitionId);
            
            if (!$requisition->canBeEdited()) {
                session()->flash('error', 'Cannot edit requisition: Status is ' . $requisition->status . '. Only DRAFT and SUBMITTED requisitions can be edited.');
                return;
            }
            
            $this->supplier_id = $requisition->supplier_id;
            $this->department_id = $requisition->department_id;
            $this->notes = $requisition->notes;
            $this->status = $requisition->status;
            
            $this->requisitionItems = $requisition->items->map(function($item) {
                $stock = $item->item;
                $expiryDate = ($stock && ($stock->use_expiration ?? false)) ? $stock->expiration_date : null;
                $locationName = $stock?->stockLocation?->name;
                return [
                    'item_id' => $item->item_id,
                    'quantity_requested' => $item->quantity_requested,
                    'unit_id' => $item->unit_id ?? '',
                    'estimated_unit_cost' => $item->estimated_unit_cost ?? 0,
                    'location_id' => $stock?->stock_location_id,
                    'location_name' => $locationName,
                    'expiry_date' => $expiryDate,
                    'notes' => $item->notes ?? '',
                ];
            })->toArray();
        } else {
            $this->resetForm();
        }
        
        $this->showRequisitionForm = true;
    }

    public function closeRequisitionForm()
    {
        $this->showRequisitionForm = false;
        $this->resetForm();
    }

    /**
     * Open read-only view for a requisition (anyone can view, print, or share). Used for approved requisitions.
     */
    public function openViewRequisition($requisitionId)
    {
        $requisition = PurchaseRequisition::with(['supplier', 'requestedBy', 'department', 'items.item.stockLocation'])->find($requisitionId);
        if (!$requisition) {
            session()->flash('error', 'Requisition not found.');
            return;
        }
        $this->showViewRequisitionId = $requisitionId;
        $this->viewRequisitionData = $requisition->toArray();
    }

    public function closeViewRequisition()
    {
        $this->showViewRequisitionId = null;
        $this->viewRequisitionData = null;
    }

    /**
     * Plain-text summary for WhatsApp share (requisition #, supplier, department, items).
     */
    public function getWhatsAppShareText(): string
    {
        if (!$this->viewRequisitionData) {
            return '';
        }
        $r = $this->viewRequisitionData;
        $lines = [
            'Purchase Requisition #' . ($r['requisition_id'] ?? ''),
            'Supplier: ' . ($r['supplier']['name'] ?? 'N/A'),
            'Department: ' . ($r['department']['name'] ?? 'N/A'),
            'Requested by: ' . ($r['requested_by']['name'] ?? 'N/A'),
            'Date: ' . (isset($r['created_at']) ? \Carbon\Carbon::parse($r['created_at'])->format('M d, Y') : ''),
            '',
            'Items:',
        ];
        foreach ($r['items'] ?? [] as $item) {
            $name = $item['item']['name'] ?? 'Item';
            $qty = $item['quantity_requested'] ?? 0;
            $unit = $item['unit_id'] ?? '';
            $cost = $item['estimated_unit_cost'] ?? 0;
            $lines[] = sprintf('- %s: %s %s @ %s', $name, $qty, $unit, \App\Helpers\CurrencyHelper::format($cost));
        }
        $lines[] = '';
        $lines[] = 'Total: ' . \App\Helpers\CurrencyHelper::format($r['total_estimated_cost'] ?? 0);
        return implode("\n", $lines);
    }

    public function resetForm()
    {
        $this->editingRequisitionId = null;
        $this->supplier_id = null;
        // Default to current user's department if it's enabled for this hotel.
        $userDeptId = (int) (Auth::user()->department_id ?? 0);
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        $this->department_id = in_array($userDeptId, $enabledDepartments, true) ? $userDeptId : null;
        $this->notes = '';
        $this->status = 'DRAFT';
        $this->requisitionItems = [];
    }

    public function addRequisitionItem()
    {
        $this->requisitionItems[] = [
            'item_id' => null,
            'quantity_requested' => 0,
            'unit_id' => '',
            'estimated_unit_cost' => 0,
            'location_id' => null,
            'location_name' => null,
            'expiry_date' => null,
            'notes' => '',
        ];
    }

    /**
     * Keep derived fields in sync (location + expiry) when selecting an item.
     */
    public function updatedRequisitionItems($value, $key): void
    {
        if (!is_string($key) || !str_contains($key, 'item_id')) {
            return;
        }

        $parts = explode('.', $key); // requisitionItems.{index}.item_id
        if (count($parts) < 3 || !is_numeric($parts[1])) {
            return;
        }

        $index = (int) $parts[1];
        if (!isset($this->requisitionItems[$index])) {
            return;
        }

        $itemId = (int) ($this->requisitionItems[$index]['item_id'] ?? 0);
        if ($itemId <= 0) {
            $this->requisitionItems[$index]['location_id'] = null;
            $this->requisitionItems[$index]['location_name'] = null;
            $this->requisitionItems[$index]['expiry_date'] = null;
            return;
        }

        $stock = collect($this->stocks)->firstWhere('id', $itemId);
        $this->requisitionItems[$index]['location_id'] = $stock?->stock_location_id;
        $this->requisitionItems[$index]['location_name'] = $stock?->stockLocation?->name;
        $this->requisitionItems[$index]['expiry_date'] = ($stock && ($stock->use_expiration ?? false)) ? $stock->expiration_date : null;

        // Default unit_id if empty (keeps the rest of the UI behavior intact).
        if (empty($this->requisitionItems[$index]['unit_id'])) {
            $this->requisitionItems[$index]['unit_id'] = $stock?->qty_unit ?? $stock?->unit ?? '';
        }
    }

    public function removeRequisitionItem($index)
    {
        unset($this->requisitionItems[$index]);
        $this->requisitionItems = array_values($this->requisitionItems);
    }

    public function saveRequisition()
    {
        $this->validate([
            'supplier_id' => 'nullable|exists:suppliers,supplier_id',
            'department_id' => 'nullable|exists:departments,id',
            'requisitionItems' => 'required|array|min:1',
            'requisitionItems.*.item_id' => 'required|exists:stocks,id',
            'requisitionItems.*.quantity_requested' => 'required|numeric|min:0.01',
            'requisitionItems.*.estimated_unit_cost' => 'nullable|numeric|min:0',
        ]);

        // Normalize optional fields to null when empty
        $supplierId = $this->supplier_id ? (int) $this->supplier_id : null;
        $departmentId = $this->department_id ? (int) $this->department_id : null;

        // Resolve business date and shift
        $resolved = TimeAndShiftResolver::resolve();
        
        // Calculate total estimated cost
        $totalCost = 0;
        foreach ($this->requisitionItems as $item) {
            $totalCost += ($item['quantity_requested'] * ($item['estimated_unit_cost'] ?? 0));
        }

        if ($this->editingRequisitionId) {
            $requisition = PurchaseRequisition::find($this->editingRequisitionId);
            
            if (!$requisition->canBeEdited()) {
                session()->flash('error', 'Cannot edit requisition: Status is ' . $requisition->status);
                return;
            }
            
            $requisition->update([
                'supplier_id' => $supplierId,
                'department_id' => $departmentId,
                'notes' => $this->notes,
                'total_estimated_cost' => $totalCost,
            ]);
            
            // Delete existing items
            $requisition->items()->delete();
        } else {
            $requisition = PurchaseRequisition::create([
                'supplier_id' => $supplierId,
                'requested_by' => Auth::id(),
                'department_id' => $departmentId,
                'status' => 'DRAFT',
                'business_date' => $resolved['business_date'],
                'shift_id' => $resolved['shift_id'],
                'notes' => $this->notes,
                'total_estimated_cost' => $totalCost,
            ]);
        }

        // Create items
        foreach ($this->requisitionItems as $item) {
            PurchaseRequisitionItem::create([
                'requisition_id' => $requisition->requisition_id,
                'item_id' => $item['item_id'],
                'quantity_requested' => $item['quantity_requested'],
                'unit_id' => $item['unit_id'] ?? null,
                'estimated_unit_cost' => $item['estimated_unit_cost'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        session()->flash('message', 'Purchase requisition saved successfully!');
        $this->closeRequisitionForm();
        $this->loadRequisitions();
    }

    public function submitRequisition($requisitionId)
    {
        $requisition = PurchaseRequisition::find($requisitionId);
        
        if ($requisition->status !== 'DRAFT') {
            session()->flash('error', 'Only DRAFT requisitions can be submitted.');
            return;
        }
        
        $requisition->update(['status' => 'SUBMITTED']);

        ActivityLogger::log(
            'stock.requisition_submitted',
            sprintf('Purchase requisition #%s submitted for approval', $requisition->requisition_id),
            PurchaseRequisition::class,
            (int) $requisition->requisition_id,
            ['status' => 'DRAFT'],
            ['status' => 'SUBMITTED'],
            ActivityLogModule::STOCK
        );

        session()->flash('message', 'Requisition submitted for approval!');
        $this->loadRequisitions();
    }

    public function openApprovalModal($requisitionId)
    {
        $this->selectedRequisitionId = $requisitionId;
        $this->approval_action = 'APPROVE';
        $this->approval_notes = '';
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
        $this->selectedRequisitionId = null;
        $this->approval_action = 'APPROVE';
        $this->approval_notes = '';
    }

    public function processApproval()
    {
        $this->validate([
            'approval_action' => 'required|in:APPROVE,REQUEST_MODIFICATION,REQUEST_CLARIFICATION',
            'approval_notes' => 'required_if:approval_action,REQUEST_MODIFICATION,REQUEST_CLARIFICATION',
        ]);

        $requisition = PurchaseRequisition::find($this->selectedRequisitionId);
        
        if ($requisition->status !== 'SUBMITTED') {
            session()->flash('error', 'Only SUBMITTED requisitions can be processed.');
            return;
        }

        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->isManager()) {
            session()->flash('error', 'Only Manager and Super Admin can approve requisitions.');
            return;
        }

        if ($this->approval_action === 'APPROVE') {
            $requisition->update(['status' => 'APPROVED']);
            
            PurchaseApproval::create([
                'requisition_id' => $requisition->requisition_id,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $this->approval_notes,
            ]);

            ActivityLogger::log(
                'stock.requisition_approved',
                sprintf('Purchase requisition #%s approved', $requisition->requisition_id),
                PurchaseRequisition::class,
                (int) $requisition->requisition_id,
                ['status' => 'SUBMITTED'],
                ['status' => 'APPROVED'],
                ActivityLogModule::STOCK
            );
            
            session()->flash('message', 'Requisition approved successfully!');
        } else {
            // Request modification or clarification - revert to DRAFT
            $requisition->update(['status' => 'DRAFT']);

            ActivityLogger::log(
                'stock.requisition_returned',
                sprintf(
                    'Purchase requisition #%s returned for %s',
                    $requisition->requisition_id,
                    strtolower(str_replace('_', ' ', $this->approval_action))
                ),
                PurchaseRequisition::class,
                (int) $requisition->requisition_id,
                ['status' => 'SUBMITTED'],
                ['status' => 'DRAFT', 'action' => $this->approval_action],
                ActivityLogModule::STOCK
            );

            session()->flash('message', 'Requisition returned to requester for ' . strtolower(str_replace('_', ' ', $this->approval_action)) . '.');
        }

        $this->closeApprovalModal();
        $this->loadRequisitions();
    }

    public function cancelRequisition($requisitionId)
    {
        $requisition = PurchaseRequisition::find($requisitionId);
        
        if (!in_array($requisition->status, ['DRAFT', 'SUBMITTED'])) {
            session()->flash('error', 'Cannot cancel requisition in ' . $requisition->status . ' status.');
            return;
        }
        
        $requisition->update(['status' => 'CANCELLED']);
        session()->flash('message', 'Requisition cancelled.');
        $this->loadRequisitions();
    }

    public function updatedFilterStatus()
    {
        $this->loadRequisitions();
    }

    public function updatedFilterDepartment()
    {
        $this->loadRequisitions();
    }

    public function updatedSearch()
    {
        $this->loadRequisitions();
    }

    // --- Comments (on whole requisition, any status) ---

    public function openComments($requisitionId)
    {
        $this->showCommentsRequisitionId = $requisitionId;
        $this->commentBody = '';
        $this->editingCommentId = null;
        $this->editCommentBody = '';
        $this->loadCommentsForModal();
    }

    public function closeComments()
    {
        $this->showCommentsRequisitionId = null;
        $this->commentsForModal = [];
        $this->commentBody = '';
        $this->editingCommentId = null;
        $this->editCommentBody = '';
    }

    protected function loadCommentsForModal()
    {
        if (!$this->showCommentsRequisitionId) {
            return;
        }
        $requisition = PurchaseRequisition::with(['comments.user'])->find($this->showCommentsRequisitionId);
        $this->commentsForModal = $requisition ? $requisition->comments->toArray() : [];
    }

    public function addComment()
    {
        $this->validate([
            'commentBody' => 'required|string|min:1|max:2000',
        ], [
            'commentBody.required' => 'Please enter a comment.',
        ]);

        PurchaseRequisitionComment::create([
            'requisition_id' => $this->showCommentsRequisitionId,
            'user_id' => Auth::id(),
            'body' => trim($this->commentBody),
        ]);

        $this->commentBody = '';
        $this->loadCommentsForModal();
        session()->flash('message', 'Comment added.');
    }

    public function startEditComment($commentId)
    {
        $comment = PurchaseRequisitionComment::find($commentId);
        if (!$comment || $comment->user_id !== Auth::id()) {
            return;
        }
        $this->editingCommentId = $commentId;
        $this->editCommentBody = $comment->body;
    }

    public function updateComment()
    {
        $comment = PurchaseRequisitionComment::find($this->editingCommentId);
        if (!$comment || $comment->user_id !== Auth::id()) {
            $this->cancelEditComment();
            return;
        }
        $this->validate([
            'editCommentBody' => 'required|string|min:1|max:2000',
        ], [
            'editCommentBody.required' => 'Comment cannot be empty.',
        ]);

        $comment->update(['body' => trim($this->editCommentBody)]);
        $this->editingCommentId = null;
        $this->editCommentBody = '';
        $this->loadCommentsForModal();
        session()->flash('message', 'Comment updated.');
    }

    public function cancelEditComment()
    {
        $this->editingCommentId = null;
        $this->editCommentBody = '';
    }

    public function deleteComment($commentId)
    {
        $comment = PurchaseRequisitionComment::find($commentId);
        if (!$comment || $comment->user_id !== Auth::id()) {
            session()->flash('error', 'You can only delete your own comments.');
            return;
        }
        $comment->delete();
        $this->loadCommentsForModal();
        if ($this->editingCommentId == $commentId) {
            $this->cancelEditComment();
        }
        session()->flash('message', 'Comment deleted.');
    }

    public function render()
    {
        return view('livewire.purchase-requisitions')
            ->layout('livewire.layouts.app-layout');
    }
}
