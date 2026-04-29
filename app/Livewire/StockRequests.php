<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockRequest;
use App\Models\StockRequestComment;
use App\Models\StockRequestItem;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityLogModule;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockRequests extends Component
{
    use ChecksModuleStatus;

    public $tab = 'my_requests'; // create, my_requests, pending, all_requests

    /** Store keeper: show tailored help for issue/receive workflow */
    public bool $isStoreKeeper = false;

    public $canAuthorize = false;

    public $canAuthorizeBarRestaurant = false; // Super Admin or approve_bar_restaurant_requisitions

    public $canEditStockItems = false; // Managers, store keeper, or Manage stock items permission

    public $canSeeAllRequests = false; // Manager or Super Admin: see all requests with filters

    public $isRestaurantManagerOnly = false; // Restaurant Manager: only create Bar & Restaurant reqs, only see my_requests

    // Filters for "All requests" (Manager/Super Admin)
    public $filterRequestedBy = null;

    public $filterDepartmentId = null;

    public string $filter_inventory_category = '';

    // Create form
    public $showCreateForm = false;

    public $request_type = StockRequest::TYPE_TRANSFER_SUBSTOCK;

    public $to_stock_location_id = null;

    public $to_department_id = null;

    public $request_notes = '';

    public $request_items = [];

    public $mainStocks = [];

    public $stockLocations = [];

    public $departments = [];

    // Reject modal
    public $showRejectModal = false;

    public $rejectingRequestId = null;

    public $rejected_reason = '';

    // View detail modal / edit
    public $viewingRequestId = null;

    public $editingRequestId = null;

    // Comment in view modal (Manager/Super Admin or requester can add/reply)
    public $commentBody = '';

    public function mount()
    {
        $user = Auth::user();
        $isRestaurantManager = $user->getEffectiveRole() && $user->getEffectiveRole()->slug === 'restaurant-manager';
        if (! $isRestaurantManager) {
            $this->ensureModuleEnabled('store');
        }

        $this->canAuthorize = Auth::user()->hasPermission('stock_authorize_requests');
        $this->canAuthorizeBarRestaurant = Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('approve_bar_restaurant_requisitions');
        $this->canEditStockItems = Auth::user() && Auth::user()->canManageStockItems();
        $this->canSeeAllRequests = Auth::user() && (Auth::user()->isSuperAdmin() || Auth::user()->isManager());
        $this->isRestaurantManagerOnly = $isRestaurantManager;

        $this->loadMainStocks();
        $this->canAuthorizeBarRestaurant = Auth::user()->isSuperAdmin() || Auth::user()->hasPermission('approve_bar_restaurant_requisitions');
        $this->canEditStockItems = Auth::user() && Auth::user()->canManageStockItems();
        $this->canSeeAllRequests = Auth::user() && (Auth::user()->isSuperAdmin() || Auth::user()->isManager());
        $this->isRestaurantManagerOnly = Auth::user()->getEffectiveRole() && Auth::user()->getEffectiveRole()->slug === 'restaurant-manager';
        $this->isStoreKeeper = Auth::user()->isEffectiveStoreKeeper();

        $this->loadMainStocks();
        $this->stockLocations = StockLocation::where('is_active', true)->orderBy('is_main_location', 'desc')->orderBy('name')->get();
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        $deptQuery = Department::where('is_active', true);
        if (! empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->orderBy('name')->get();

        // Default tab: show recent activity (not an empty "New request" screen).
        if ($this->isRestaurantManagerOnly) {
            $this->tab = 'create';
        } elseif ($this->canSeeAllRequests) {
            $this->tab = 'all_requests';
        } else {
            $this->tab = 'my_requests';
        }

        if (request()->get('action') === 'create') {
            $this->tab = 'create';
            $this->openCreateForm(request()->get('type'));
            $stockId = request()->get('stock_id');
            if ($stockId) {
                $this->request_items = [
                    ['stock_id' => $stockId, 'quantity' => '', 'quantity_packages' => '', 'to_stock_location_id' => '', 'to_department_id' => '', 'edit_data' => [], 'notes' => ''],
                ];
            }
            $this->showCreateForm = true;
        }
    }

    protected function loadMainStocks(): void
    {
        $mainLocationIds = StockLocation::where('is_active', true)
            ->where('is_main_location', true)
            ->whereNull('parent_location_id')
            ->pluck('id');
        $query = Stock::with(['itemType', 'stockLocation'])
            ->whereIn('stock_location_id', $mainLocationIds)
            ->orderBy('name');

        if ($this->filter_inventory_category !== '') {
            $query->where('inventory_category', $this->filter_inventory_category);
        }

        $this->mainStocks = $query->get();
    }

    /** Compare as int — DB/UI sometimes differ on string vs int and break === checks. */
    protected function currentUserIsRequester(StockRequest $request): bool
    {
        return (int) $request->requested_by_id === (int) Auth::id();
    }

    /** Pending request: creator may always edit; managers with authorize permission may also edit (incl. Bar & Restaurant). */
    public function userCanEditPendingStockRequest(StockRequest $request): bool
    {
        if (! $request->isPending()) {
            return false;
        }
        if ($this->currentUserIsRequester($request)) {
            return true;
        }
        if ($this->canAuthorize && Auth::user()->isManager()) {
            return true;
        }
        if ($request->isBarRestaurantRequisition() && $this->canAuthorizeBarRestaurant && Auth::user()->isManager()) {
            return true;
        }

        return false;
    }

    public function userCanCommentOnStockRequest(StockRequest $request): bool
    {
        return $this->canAuthorize
            || $this->canAuthorizeBarRestaurant
            || $this->currentUserIsRequester($request);
    }

    public function openCreateForm($type = null)
    {
        // Restaurant Manager can only create Bar & Restaurant requisitions
        $this->request_type = $this->isRestaurantManagerOnly
            ? StockRequest::TYPE_ISSUE_BAR_RESTAURANT
            : ($type ?? StockRequest::TYPE_TRANSFER_SUBSTOCK);
        $this->to_stock_location_id = null;
        $this->to_department_id = null;
        $this->request_notes = '';
        $this->request_items = [
            ['stock_id' => '', 'quantity' => '', 'quantity_packages' => '', 'to_stock_location_id' => '', 'to_department_id' => '', 'edit_data' => [], 'notes' => ''],
        ];
        $this->showCreateForm = true;
    }

    public function updated($property): void
    {
        if (preg_match('/^request_items\.(\d+)\.stock_id$/', $property, $m)) {
            $i = (int) $m[1];
            if (isset($this->request_items[$i])) {
                $this->request_items[$i]['quantity'] = '';
                $this->request_items[$i]['quantity_packages'] = '';
            }
        }
    }

    /** True when stock is ordered/sold by package (cases, etc.) and quantities are stored in base units. */
    public function stockUsesPackages(?Stock $stock): bool
    {
        if (! $stock) {
            return false;
        }
        $pkg = (float) ($stock->package_size ?? 0);

        return $pkg > 0 && ($stock->package_unit ?? '') !== '';
    }

    /**
     * For display: main-stock on hand and optional substock line for the selected destination.
     *
     * @return array{uses_packages: bool, pkg_size: float, pkg_unit: string, qty_unit: string, main_base: float, main_pkg: ?float, sub_base: ?float, sub_pkg: ?float}|null
     */
    public function stockAvailabilityHint(?string $stockId, $toStockLocationId = null, $defaultToLocationId = null): ?array
    {
        if (! $stockId) {
            return null;
        }
        $stock = Stock::with('stockLocation')->find((int) $stockId);
        if (! $stock) {
            return null;
        }
        $pkgSize = (float) ($stock->package_size ?? 0);
        $pkgUnit = $stock->package_unit ?? '';
        $qtyUnit = $stock->qty_unit ?? $stock->unit ?? '';
        $usesPackages = $pkgSize > 0 && $pkgUnit !== '';
        $mainBase = (float) ($stock->current_stock ?? $stock->quantity ?? 0);
        $mainPkg = $usesPackages ? $mainBase / $pkgSize : null;
        $subBase = null;
        $subPkg = null;
        $locId = $toStockLocationId !== null && $toStockLocationId !== ''
            ? (int) $toStockLocationId
            : ($defaultToLocationId !== null && $defaultToLocationId !== '' ? (int) $defaultToLocationId : null);
        if ($locId) {
            $sub = Stock::where('name', $stock->name)
                ->where('item_type_id', $stock->item_type_id)
                ->where('stock_location_id', $locId)
                ->first();
            if ($sub) {
                $subBase = (float) ($sub->current_stock ?? $sub->quantity ?? 0);
                $subPkg = $usesPackages ? $subBase / $pkgSize : null;
            }
        }

        return [
            'uses_packages' => $usesPackages,
            'pkg_size' => $pkgSize,
            'pkg_unit' => $pkgUnit,
            'qty_unit' => $qtyUnit,
            'main_base' => $mainBase,
            'main_pkg' => $mainPkg,
            'sub_base' => $subBase,
            'sub_pkg' => $subPkg,
        ];
    }

    /** Resolved base quantity for a line (from package input or direct base quantity). */
    protected function resolvedQuantityForRow(array $row, ?Stock $stock): float
    {
        if ($stock && $this->stockUsesPackages($stock)) {
            $pkg = (float) ($stock->package_size ?? 0);
            $qp = isset($row['quantity_packages']) && $row['quantity_packages'] !== ''
                ? (float) $row['quantity_packages']
                : 0.0;
            if ($qp > 0 && $pkg > 0) {
                return round($qp * $pkg, 4);
            }
        }

        return isset($row['quantity']) ? (float) $row['quantity'] : 0.0;
    }

    public function closeCreateForm()
    {
        $this->showCreateForm = false;
        $this->request_items = [];
        $this->editingRequestId = null;
    }

    public function addRequestItem()
    {
        $this->request_items[] = [
            'stock_id' => '',
            'quantity' => '',
            'quantity_packages' => '',
            'to_stock_location_id' => '',
            'to_department_id' => '',
            'edit_data' => [],
            'notes' => '',
        ];
    }

    public function removeRequestItem($index)
    {
        array_splice($this->request_items, $index, 1);
        if (empty($this->request_items)) {
            $this->addRequestItem();
        }
    }

    public function submitRequest()
    {
        $rules = [
            'request_type' => 'required|in:transfer_substock,transfer_department,issue_department,issue_bar_restaurant',
            'request_items' => 'required|array|min:1',
        ];
        if ($this->request_type === StockRequest::TYPE_TRANSFER_SUBSTOCK) {
            $rules['to_stock_location_id'] = 'required|exists:stock_locations,id';
        }
        if (in_array($this->request_type, [StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT])) {
            $rules['to_department_id'] = 'required|exists:departments,id';
        }
        $this->validate($rules);

        // Restaurant Manager may only create Bar & Restaurant type
        if ($this->isRestaurantManagerOnly && $this->request_type !== StockRequest::TYPE_ISSUE_BAR_RESTAURANT) {
            session()->flash('error', 'Restaurant Manager can only create Bar & Restaurant requisitions (items from main stock).');

            return;
        }

        $validItems = [];
        foreach ($this->request_items as $i => $row) {
            $stockId = $row['stock_id'] ?? null;
            if (! $stockId) {
                continue;
            }
            $stock = Stock::find($stockId);
            $qty = $this->resolvedQuantityForRow($row, $stock);
            if ($qty <= 0) {
                continue;
            }
            $toLocation = $this->request_type === StockRequest::TYPE_TRANSFER_SUBSTOCK ? ($row['to_stock_location_id'] ?? $this->to_stock_location_id) : null;
            $toDept = in_array($this->request_type, [StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_DEPARTMENT]) ? ($row['to_department_id'] ?? $this->to_department_id) : null;
            $validItems[] = [
                'stock_id' => $stockId,
                'quantity' => $qty,
                'to_stock_location_id' => $toLocation !== '' && $toLocation !== null ? (int) $toLocation : null,
                'to_department_id' => in_array($this->request_type, [StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT]) && $toDept !== '' && $toDept !== null ? (int) $toDept : null,
                'notes' => $row['notes'] ?? '',
            ];
        }

        if (empty($validItems)) {
            session()->flash('error', 'Add at least one item with a valid quantity.');

            return;
        }

        if ($this->editingRequestId) {
            $request = StockRequest::find($this->editingRequestId);
            if (! $request || ! $request->isPending()) {
                session()->flash('error', 'Request not found or not pending.');

                return;
            }
            if (! $this->userCanEditPendingStockRequest($request)) {
                session()->flash('error', 'You cannot edit this request.');

                return;
            }
            $request->update([
                'type' => $this->request_type,
                'notes' => $this->request_notes,
                'to_stock_location_id' => $this->request_type === StockRequest::TYPE_TRANSFER_SUBSTOCK && $this->to_stock_location_id !== '' && $this->to_stock_location_id !== null ? (int) $this->to_stock_location_id : null,
                'to_department_id' => in_array($this->request_type, [StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT]) && $this->to_department_id !== '' && $this->to_department_id !== null ? (int) $this->to_department_id : null,
            ]);
            $request->items()->delete();
        } else {
            $request = StockRequest::create([
                'type' => $this->request_type,
                'status' => StockRequest::STATUS_PENDING,
                'requested_by_id' => Auth::id(),
                'notes' => $this->request_notes,
                'to_stock_location_id' => $this->request_type === StockRequest::TYPE_TRANSFER_SUBSTOCK && $this->to_stock_location_id !== '' && $this->to_stock_location_id !== null ? (int) $this->to_stock_location_id : null,
                'to_department_id' => in_array($this->request_type, [StockRequest::TYPE_TRANSFER_DEPARTMENT, StockRequest::TYPE_ISSUE_DEPARTMENT, StockRequest::TYPE_ISSUE_BAR_RESTAURANT]) && $this->to_department_id !== '' && $this->to_department_id !== null ? (int) $this->to_department_id : null,
            ]);
        }

        foreach ($validItems as $item) {
            StockRequestItem::create([
                'stock_request_id' => $request->id,
                'stock_id' => $item['stock_id'],
                'quantity' => $item['quantity'] ?? 0,
                'to_stock_location_id' => isset($item['to_stock_location_id']) && $item['to_stock_location_id'] !== '' ? (int) $item['to_stock_location_id'] : null,
                'to_department_id' => isset($item['to_department_id']) && $item['to_department_id'] !== '' ? (int) $item['to_department_id'] : null,
                'edit_data' => $item['edit_data'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $isUpdate = (bool) $this->editingRequestId;
        session()->flash('message', $isUpdate ? 'Request updated.' : 'Stock request submitted. An authorizer will review it.');
        ActivityLogger::log(
            $isUpdate ? 'stock_request_update' : 'stock_request_create',
            $isUpdate
                ? "Stock request #{$request->id} updated (pending approval)."
                : "Stock request #{$request->id} submitted (".(StockRequest::typeLabels()[$request->type] ?? $request->type).', '.count($validItems).' line(s)).',
            StockRequest::class,
            $request->id,
            null,
            ['type' => $request->type, 'items' => count($validItems)],
            ActivityLogModule::STOCK
        );
        $this->closeCreateForm();
        $this->tab = 'my_requests';
    }

    public function viewRequest($id)
    {
        $this->viewingRequestId = $id;
    }

    public function closeViewModal()
    {
        $this->viewingRequestId = null;
        $this->commentBody = '';
    }

    public function editRequest($id)
    {
        $request = StockRequest::with(['items.stock', 'toStockLocation', 'toDepartment'])->find($id);
        if (! $request || ! $request->isPending()) {
            session()->flash('error', 'Request not found or not pending.');

            return;
        }
        if (! $this->userCanEditPendingStockRequest($request)) {
            session()->flash('error', 'You cannot edit this request.');

            return;
        }
        $this->editingRequestId = $request->id;
        $this->request_type = $request->type;
        $this->to_stock_location_id = $request->to_stock_location_id;
        $this->to_department_id = $request->to_department_id;
        $this->request_notes = $request->notes ?? '';
        $this->request_items = [];
        foreach ($request->items as $it) {
            $st = $it->stock;
            $qp = '';
            if ($st && $this->stockUsesPackages($st)) {
                $pkg = (float) ($st->package_size ?? 0);
                if ($pkg > 0) {
                    $qp = (string) round(((float) $it->quantity) / $pkg, 6);
                }
            }
            $this->request_items[] = [
                'stock_id' => $it->stock_id,
                'quantity' => (string) $it->quantity,
                'quantity_packages' => $qp,
                'to_stock_location_id' => $it->to_stock_location_id ?? '',
                'to_department_id' => $it->to_department_id ?? '',
                'edit_data' => $it->edit_data ?? [],
                'notes' => $it->notes ?? '',
            ];
        }
        if (empty($this->request_items)) {
            $this->addRequestItem();
        }
        $this->viewingRequestId = null;
        $this->showCreateForm = true;
    }

    public function getViewingRequestProperty()
    {
        if (! $this->viewingRequestId) {
            return null;
        }

        return StockRequest::with(['requestedBy', 'approvedBy', 'deletionRequestedBy', 'items.stock', 'items.toStockLocation', 'items.toDepartment', 'toStockLocation', 'toDepartment', 'comments.user'])
            ->find($this->viewingRequestId);
    }

    /**
     * Add a comment on the currently viewed request. Manager/Super Admin or the requester can add/reply.
     */
    public function addComment()
    {
        if (! $this->viewingRequestId) {
            return;
        }
        $request = StockRequest::find($this->viewingRequestId);
        if (! $request) {
            return;
        }
        $canComment = $this->userCanCommentOnStockRequest($request);
        if (! $canComment) {
            session()->flash('error', 'You cannot add comments on this request.');

            return;
        }
        $this->validate(['commentBody' => 'required|string|min:1|max:2000'], ['commentBody.required' => 'Please enter a comment.']);
        StockRequestComment::create([
            'stock_request_id' => $this->viewingRequestId,
            'user_id' => Auth::id(),
            'body' => trim($this->commentBody),
        ]);
        $this->commentBody = '';
        session()->flash('message', 'Comment added.');
    }

    /**
     * Super Admin only: set an approved (or rejected) request back to pending so it can be modified.
     */
    public function setRequestToPending($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can set a request back to pending.');

            return;
        }
        $request = StockRequest::find($id);
        if (! $request) {
            session()->flash('error', 'Request not found.');

            return;
        }
        if ($request->isPending()) {
            session()->flash('message', 'Request is already pending.');

            return;
        }
        $request->update([
            'status' => StockRequest::STATUS_PENDING,
            'approved_by_id' => null,
            'approved_at' => null,
            'rejected_reason' => null,
        ]);
        ActivityLogger::log(
            'stock_request_set_pending',
            'Stock request #'.$request->id.' set back to pending (Super Admin).',
            StockRequest::class,
            $request->id,
            null,
            null,
            ActivityLogModule::STOCK
        );
        session()->flash('message', 'Request set to pending. The requester or Manager can now modify it.');
        $this->viewingRequestId = null;
    }

    /**
     * Request deletion of this stock request. Only Super Admin can approve; no direct delete.
     */
    public function requestDeletion($id)
    {
        if (Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Super Admin can approve deletion from the request view; do not use "Request deletion".');

            return;
        }
        $request = StockRequest::find($id);
        if (! $request) {
            session()->flash('error', 'Request not found.');

            return;
        }
        if ($request->isDeletionRequested()) {
            session()->flash('message', 'Deletion already requested. Waiting for Super Admin approval.');

            return;
        }
        $request->update([
            'deletion_requested_at' => now(),
            'deletion_requested_by_id' => Auth::id(),
        ]);
        ActivityLogger::log(
            'stock_request_deletion_requested',
            'Deletion requested for stock request #'.$request->id.'.',
            StockRequest::class,
            $request->id,
            null,
            null,
            ActivityLogModule::STOCK
        );
        session()->flash('message', 'Deletion requested. Super Admin must approve to delete this request.');
    }

    /**
     * Super Admin only: approve deletion and delete the request.
     */
    public function approveDeletion($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can approve deletion.');

            return;
        }
        $request = StockRequest::find($id);
        if (! $request || ! $request->isDeletionRequested()) {
            session()->flash('error', 'Request not found or deletion was not requested.');

            return;
        }
        $rid = $request->id;
        $request->delete();
        ActivityLogger::log(
            'stock_request_delete',
            'Stock request #'.$rid.' deleted (Super Admin approved deletion).',
            StockRequest::class,
            $rid,
            null,
            null,
            ActivityLogModule::STOCK
        );
        session()->flash('message', 'Request deleted.');
        $this->closeViewModal();
    }

    /**
     * Super Admin only: reject deletion request.
     */
    public function rejectDeletion($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can reject deletion.');

            return;
        }
        $request = StockRequest::find($id);
        if (! $request) {
            session()->flash('error', 'Request not found.');

            return;
        }
        $request->update([
            'deletion_requested_at' => null,
            'deletion_requested_by_id' => null,
        ]);
        session()->flash('message', 'Deletion request rejected.');
    }

    public function approveRequest($id)
    {
        $request = StockRequest::with('items.stock')->find($id);
        if (! $request || ! $request->isPending()) {
            session()->flash('error', 'Request not found or already processed.');

            return;
        }
        $canApprove = $request->isBarRestaurantRequisition()
            ? $this->canAuthorizeBarRestaurant
            : $this->canAuthorize;
        if (! $canApprove) {
            session()->flash('error', 'You are not allowed to approve this request.');

            return;
        }

        $request->update([
            'status' => StockRequest::STATUS_APPROVED,
            'approved_by_id' => Auth::id(),
            'approved_at' => now(),
            'rejected_reason' => null,
        ]);

        // Execution (transfer/issue) happens when store keeper issues from Stock Out page, not on approve.
        ActivityLogger::log(
            'stock_request_approve',
            'Stock request #'.$request->id.' approved ('.(StockRequest::typeLabels()[$request->type] ?? $request->type).').',
            StockRequest::class,
            $request->id,
            ['status' => StockRequest::STATUS_PENDING],
            ['status' => StockRequest::STATUS_APPROVED],
            ActivityLogModule::STOCK
        );
        session()->flash('message', 'Request approved. Store keeper can now issue items from the Stock Out page.');
    }

    public function openRejectModal($id)
    {
        $request = StockRequest::find($id);
        if ($request && $request->isBarRestaurantRequisition() && ! $this->canAuthorizeBarRestaurant) {
            session()->flash('error', 'You are not allowed to reject Bar & Restaurant requisitions.');

            return;
        }
        if ($request && ! $request->isBarRestaurantRequisition() && ! $this->canAuthorize) {
            session()->flash('error', 'You are not allowed to reject this request.');

            return;
        }
        $this->rejectingRequestId = $id;
        $this->rejected_reason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->rejectingRequestId = null;
        $this->rejected_reason = '';
    }

    public function rejectRequest()
    {
        $request = StockRequest::find($this->rejectingRequestId);
        $canReject = $request && ($request->isBarRestaurantRequisition() ? $this->canAuthorizeBarRestaurant : $this->canAuthorize);
        if (! $canReject) {
            session()->flash('error', 'You are not allowed to reject this request.');
            $this->closeRejectModal();

            return;
        }
        $this->validate(['rejected_reason' => 'required|string|min:3']);
        $request = StockRequest::find($this->rejectingRequestId);
        if (! $request || ! $request->isPending()) {
            session()->flash('error', 'Request not found or already processed.');
            $this->closeRejectModal();

            return;
        }
        $request->update([
            'status' => StockRequest::STATUS_REJECTED,
            'approved_by_id' => Auth::id(),
            'approved_at' => now(),
            'rejected_reason' => $this->rejected_reason,
        ]);
        ActivityLogger::log(
            'stock_request_reject',
            'Stock request #'.$request->id.' rejected.',
            StockRequest::class,
            $request->id,
            null,
            ['reason' => $this->rejected_reason],
            ActivityLogModule::STOCK
        );
        session()->flash('message', 'Request rejected.');
        $this->closeRejectModal();
    }

    public function getMyRequestsProperty()
    {
        return StockRequest::with(['requestedBy', 'items.stock', 'toStockLocation', 'toDepartment'])
            ->where('requested_by_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
    }

    public function getPendingRequestsProperty()
    {
        if (! $this->canAuthorize && ! $this->canAuthorizeBarRestaurant) {
            return collect();
        }
        $query = StockRequest::with(['requestedBy', 'items.stock', 'toStockLocation', 'toDepartment'])
            ->where('status', StockRequest::STATUS_PENDING)
            ->orderBy('created_at');
        if ($this->canAuthorize && $this->canAuthorizeBarRestaurant) {
            // Super Admin or both permissions: all pending
        } elseif ($this->canAuthorize) {
            $query->where('type', '!=', StockRequest::TYPE_ISSUE_BAR_RESTAURANT);
        } else {
            $query->where('type', StockRequest::TYPE_ISSUE_BAR_RESTAURANT);
        }

        return $query->get();
    }

    /**
     * All stock requests for Manager/Super Admin, with optional filters by user or department.
     */
    public function getAllRequestsProperty()
    {
        if (! $this->canSeeAllRequests) {
            return collect();
        }
        $query = StockRequest::with(['requestedBy', 'approvedBy', 'items.stock', 'toStockLocation', 'toDepartment'])
            ->orderByDesc('created_at');

        if ($this->filterRequestedBy !== null && $this->filterRequestedBy !== '') {
            $query->where('requested_by_id', (int) $this->filterRequestedBy);
        }
        if ($this->filterDepartmentId !== null && $this->filterDepartmentId !== '') {
            if ($this->filterDepartmentId === 'none') {
                $query->whereNull('to_department_id');
            } else {
                $query->where('to_department_id', (int) $this->filterDepartmentId);
            }
        }

        return $query->get();
    }

    /**
     * Departments that appear in stock requests (to_department_id). Used for filter dropdown so only relevant departments are shown. Falls back to all active departments if none used yet.
     */
    public function getFilterDepartmentsProperty()
    {
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $usedIds = StockRequest::whereNotNull('to_department_id')->distinct()->pluck('to_department_id');
        if ($usedIds->isEmpty()) {
            $deptQuery = Department::where('is_active', true);
            if (! empty($enabledDepartments)) {
                $deptQuery->whereIn('id', $enabledDepartments);
            }

            return $deptQuery->orderBy('name')->get();
        }

        $deptQuery = Department::where('is_active', true)->whereIn('id', $usedIds);
        if (! empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }

        return $deptQuery->orderBy('name')->get();
    }

    /**
     * Users list for "All requests" filter (Manager/Super Admin).
     */
    public function getRequestersProperty()
    {
        if (! $this->canSeeAllRequests) {
            return collect();
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return collect();
        }

        return User::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getAuthorizersProperty()
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return collect();
        }

        return User::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('role.permissions', fn ($q2) => $q2->where('permissions.slug', 'stock_authorize_requests'))
                    ->orWhereHas('permissions', fn ($q2) => $q2->where('permissions.slug', 'stock_authorize_requests'));
            })
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.stock-requests')->layout('livewire.layouts.app-layout');
    }

    public function updatedFilterInventoryCategory(): void
    {
        $this->loadMainStocks();
    }
}
