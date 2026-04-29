<?php

namespace App\Livewire\Pos;

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemVoidRequest;
use App\Models\PosSession;
use App\Models\PreparationStation;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\VoidRequestNotification;
use App\Services\ActivityLogger;
use App\Services\InvoiceNumberService;
use App\Support\ActivityLogModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PosOrders extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $filter_status = 'OPEN'; // legacy, kept for compatibility with query param

    public string $view_filter = 'all'; // all, mine, open, confirmed, paid, unpaid, transferred, cancelled

    /** Hotel-local date range for the orders list (default: today). */
    public string $date_from = '';

    public string $date_to = '';

    /** Optional: filter by assigned waiter (user id). Empty = all staff. */
    public string $filter_waiter_id = '';

    /** Optional: only orders that include this menu item (non-voided lines). Empty = any items. */
    public string $filter_menu_item_id = '';

    /** Active hotel users for the waiter filter dropdown (id + name). */
    public array $orderFilterUsers = [];

    public $showOrderForm = false;

    public $showOrderDetail = false;

    public $selectedOrderId = null;

    public $selectedOrder = null;

    public $orderItems = [];

    public $table_id = '';

    public $menuItems = [];

    public $add_item_search = '';

    public $add_menu_item_id = '';

    public $add_quantity = 1;

    public $add_item_notes = '';

    public string $add_sales_category_filter = '';

    /** Station to post the new item to (default from menu item) */
    public $add_station = '';

    // Order transfer
    public $showTransferForm = false;

    public $transfer_to_user_id = '';

    public $transfer_comment = '';

    public $transferUsers = [];

    /** Modal: post single item — order_item id and selected station */
    public ?int $postItemModalOrderItemId = null;

    public string $postItemModalStation = '';

    /** Modal: per-item options/ingredients */
    public ?int $editOptionsOrderItemId = null;

    public array $editOptionsData = [
        'temperature' => '',
        'sugar' => '',
        'ice' => '',
        'ingredients' => [],
        'notes' => '',
    ];

    /** Dynamic option groups for the selected order item (driven by Menu Management). */
    public array $editOptionGroups = [];

    /** Whether current user can edit items on the currently selected order */
    public bool $canEditSelectedOrderItems = false;

    /**
     * Check if the current user can perform high-level operations on the order
     * (e.g. request voids, review details). This is intentionally broader than
     * item-level editing which is handled separately.
     */
    protected function canModifyOrder(Order $order): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Managers / controllers / cashiers / admins can review and request approvals.
        if ($user->isSuperAdmin() || $user->isManager() || $user->isRestaurantManager() || $user->isCashier() || ($user->role && $user->role->slug === 'controller')) {
            return true;
        }

        // Waiter who created the order always retains control over their own order.
        return $order->waiter_id === $user->id;
    }

    /**
     * Whether the current user can add items to this order.
     * - Waiter who owns the order can add items while it is OPEN.
     * - Cashier can add items to help guests adjust their bill.
     */
    protected function canUserAddItems(Order $order): bool
    {
        $user = Auth::user();
        if (! $user || $order->order_status === 'CANCELLED') {
            return false;
        }

        if ($order->waiter_id === $user->id) {
            // Waiter can only add items while the order is OPEN.
            return $order->canEditItems();
        }

        if ($user->isCashier()) {
            // Cashier can add items even after PAID as long as the order ticket has not yet been printed.
            return $order->order_ticket_printed_at === null;
        }

        return false;
    }

    /**
     * Whether the current user can remove an item from this order directly.
     * Only the waiter who created the order may remove items that are still
     * safe to remove (not yet posted/printed). Others must use the void /
     * modification-request flow.
     */
    protected function canUserRemoveItems(Order $order): bool
    {
        $user = Auth::user();
        if (! $user || ! $order->canEditItems()) {
            return false;
        }

        return $order->waiter_id === $user->id;
    }

    /**
     * Whether the current user can transfer an order to another waiter.
     * Only supervisors (manager, restaurant manager, cashier, controller, super admin)
     * may transfer; regular waiters cannot transfer orders.
     */
    protected function canTransferOrders(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isManager()
            || $user->isRestaurantManager()
            || $user->isCashier()
            || ($user->role && $user->role->slug === 'controller');
    }

    public function mount()
    {
        $session = PosSession::getOpenForUser(Auth::id());
        if (! $session) {
            session()->flash('error', 'Open a POS session first.');

            return $this->redirect(route('pos.home'), navigate: true);
        }

        $this->loadMenuItems();
        $hotel = Hotel::getHotel();
        $today = Hotel::getTodayForHotel() ?: now()->toDateString();
        $this->date_from = request('date_from', $today);
        $this->date_to = request('date_to', $today);
        $this->filter_waiter_id = (string) request('waiter_id', '');
        $this->filter_menu_item_id = (string) request('menu_item_id', '');

        // Staff filter + transfer targets: waiters for this hotel only (not other roles / hotels).
        if ($hotel) {
            $this->orderFilterUsers = User::activeInHotelWithRoleSlug($hotel->id, 'waiter')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->all();

            $this->transferUsers = User::activeInHotelWithRoleSlug($hotel->id, 'waiter')
                ->where('id', '!=', Auth::id())
                ->orderBy('name')
                ->get();
        } else {
            $this->orderFilterUsers = [];
            $this->transferUsers = collect();
        }
        $orderId = request()->query('order');
        if ($orderId && is_numeric($orderId)) {
            $this->filter_status = '';
            $this->selectOrder((int) $orderId);
        }
        if (request()->query('action') === 'new') {
            $this->showOrderForm = true;
        }
    }

    public function updatedViewFilter(): void
    {
        if ($this->view_filter === 'mine') {
            $this->filter_waiter_id = '';
        }
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->normalizeDateRange();
        $this->resetPage();
    }

    public function updatedFilterWaiterId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMenuItemId(): void
    {
        $this->resetPage();
    }

    /**
     * Quick presets for the date range (hotel timezone day boundaries handled via whereDate on created_at).
     */
    public function setDatePreset(string $preset): void
    {
        $hotel = Hotel::getHotel();
        $tz = $hotel?->getTimezone() ?? config('app.timezone');
        $now = now($tz);

        switch ($preset) {
            case 'today':
                $this->date_from = $now->toDateString();
                $this->date_to = $now->toDateString();
                break;
            case 'yesterday':
                $y = $now->copy()->subDay();
                $this->date_from = $y->toDateString();
                $this->date_to = $y->toDateString();
                break;
            case 'week':
                $wStart = $now->copy()->startOfWeek();
                $wEnd = $now->copy()->endOfWeek();
                $this->date_from = $wStart->toDateString();
                $this->date_to = $wEnd->toDateString();
                break;
            case 'month':
                $mStart = $now->copy()->startOfMonth();
                $mEnd = $now->copy()->endOfMonth();
                $this->date_from = $mStart->toDateString();
                $this->date_to = $mEnd->toDateString();
                break;
            default:
                break;
        }

        $this->normalizeDateRange();
        $this->resetPage();
    }

    protected function normalizeDateRange(): void
    {
        if ($this->date_from !== '' && $this->date_to !== '' && $this->date_from > $this->date_to) {
            [$this->date_from, $this->date_to] = [$this->date_to, $this->date_from];
        }
    }

    public function clearOrderFilters(): void
    {
        $today = Hotel::getTodayForHotel() ?: now()->toDateString();
        $this->date_from = $today;
        $this->date_to = $today;
        $this->filter_waiter_id = '';
        $this->filter_menu_item_id = '';
        $this->view_filter = 'all';
        $this->resetPage();
    }

    public function updatedAddSalesCategoryFilter(): void
    {
        $this->add_menu_item_id = '';
    }

    protected function requireSession()
    {
        if (! PosSession::getOpenForUser(Auth::id())) {
            session()->flash('error', 'Open a POS session first.');

            return $this->redirect(route('pos.home'), navigate: true);
        }
    }

    /**
     * Orders list for current filter — computed so Livewire does not try to serialize the Paginator.
     */
    public function getOrdersProperty()
    {
        $session = PosSession::getOpenForUser(Auth::id());
        if (! $session) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        $hotel = \App\Models\Hotel::getHotel();

        $query = Order::with([
            'table',
            'waiter',
            'invoice' => fn ($q) => $q->with(['reservation', 'room', 'postedBy']),
            'orderItems' => function ($q) {
                $q->whereNull('voided_at');
            },
        ])->orderByDesc('created_at');

        // Scope orders to the current hotel (via waiter -> hotel_id) so Super Admin
        // and managers only see POS orders for the active hotel.
        if ($hotel) {
            $query->whereHas('waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            });
        }

        // Date range (defaults to today in mount).
        $this->normalizeDateRange();
        if ($this->date_from !== '' && $this->date_to !== '') {
            $query->whereDate('created_at', '>=', $this->date_from)
                ->whereDate('created_at', '<=', $this->date_to);
        }

        if ($this->view_filter !== 'mine' && $this->filter_waiter_id !== '' && is_numeric($this->filter_waiter_id)) {
            $wid = (int) $this->filter_waiter_id;
            if ($wid > 0) {
                $query->where('waiter_id', $wid);
            }
        }

        if ($this->filter_menu_item_id !== '' && is_numeric($this->filter_menu_item_id)) {
            $mid = (int) $this->filter_menu_item_id;
            if ($mid > 0) {
                $query->whereHas('orderItems', function ($q) use ($mid) {
                    $q->where('menu_item_id', $mid)->whereNull('voided_at');
                });
            }
        }

        $userId = Auth::id();
        switch ($this->view_filter) {
            case 'mine':
                $query->where('waiter_id', $userId);
                break;
            case 'open':
                $query->where('order_status', 'OPEN');
                break;
            case 'confirmed':
                $query->where('order_status', 'CONFIRMED');
                break;
            case 'paid':
                $query->where('order_status', 'PAID');
                break;
            case 'unpaid':
                $query->whereIn('order_status', ['OPEN', 'CONFIRMED']);
                break;
            case 'transferred':
                $query->whereNotNull('transferred_from_id');
                break;
            case 'cancelled':
                $query->where('order_status', 'CANCELLED');
                break;
            case 'all':
            default:
                break;
        }

        return $query->paginate(20);
    }

    public function loadMenuItems()
    {
        $this->menuItems = MenuItem::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function createOrder()
    {
        $session = PosSession::getOpenForUser(Auth::id());
        $tableId = $this->table_id ?: null;
        $order = Order::create([
            'table_id' => $tableId,
            'waiter_id' => Auth::id(),
            'session_id' => $session->id,
            'order_status' => 'OPEN',
        ]);
        if ($tableId) {
            RestaurantTable::where('id', $tableId)->update(['is_active' => false]);
        }
        session()->flash('message', 'Order created.');
        $this->showOrderForm = false;
        $this->table_id = '';
        $this->selectOrder($order->id);
    }

    public function selectOrder($orderId)
    {
        $this->selectedOrderId = $orderId;
        $this->selectedOrder = Order::with([
            'table',
            'waiter',
            'invoice',
            'orderItems.menuItem',
            'orderItems.voidRequests',
            'orderItems.voidedBy',
        ])->find($orderId);
        if (! $this->selectedOrder) {
            $this->showOrderDetail = false;

            return;
        }
        // Item-level editing is restricted:
        // - Waiter who created the order can edit while OPEN.
        // - Cashier can add items but must use void / modification flows to remove.
        $this->canEditSelectedOrderItems = $this->canUserAddItems($this->selectedOrder);
        $this->orderItems = $this->selectedOrder->orderItems->map(function (OrderItem $item) {
            $pendingVoid = $item->voidRequests->where('status', OrderItemVoidRequest::STATUS_PENDING)->first();

            return [
                'id' => $item->id,
                'menu_item' => $item->menuItem
                    ? ['name' => $item->menuItem->name, 'preparation_station' => $item->menuItem->preparation_station]
                    : ['name' => 'N/A', 'preparation_station' => null],
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'notes' => $item->notes,
                'posted_to_station' => $item->posted_to_station,
                'effective_station' => $item->effective_station,
                'effective_station_label' => $this->stationLabel($item->effective_station),
                'sent_to_station_at' => $item->sent_to_station_at?->toIso8601String(),
                'printed_at' => $item->printed_at?->toIso8601String(),
                'voided_at' => $item->voided_at?->toIso8601String(),
                'voided_by_name' => $item->voidedBy?->name,
                'can_remove' => $item->canRemove(),
                'is_posted' => $item->isPosted(),
                'is_printed' => $item->isPrinted(),
                'is_voided' => $item->isVoided(),
                'pending_void_request' => $pendingVoid ? [
                    'id' => $pendingVoid->id,
                    'reason' => $pendingVoid->reason,
                ] : null,
            ];
        })->values()->toArray();
        $this->postItemModalOrderItemId = null;
        $this->postItemModalStation = '';
        $this->editOptionsOrderItemId = null;
        $this->editOptionGroups = [];
        $this->showOrderDetail = true;
    }

    private function stationLabel(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '—';
        }
        $stations = PreparationStation::getActiveForPos();

        return $stations[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
    }

    public function closeOrderDetail()
    {
        $this->showOrderDetail = false;
        $this->selectedOrderId = null;
        $this->selectedOrder = null;
        $this->orderItems = [];
        $this->showTransferForm = false;
        $this->transfer_to_user_id = '';
        $this->transfer_comment = '';
        $this->canEditSelectedOrderItems = false;
    }

    /**
     * Show transfer form for the currently selected order.
     */
    public function startTransfer()
    {
        if (! $this->selectedOrderId) {
            return;
        }
        if (! $this->canTransferOrders()) {
            session()->flash('error', 'Only a manager, controller, or cashier can transfer orders.');

            return;
        }
        $this->showTransferForm = true;
    }

    /**
     * Transfer the selected order to another user with an optional comment.
     */
    public function transferOrder()
    {
        if (! $this->selectedOrderId) {
            return;
        }

        if (! $this->canTransferOrders()) {
            session()->flash('error', 'Only a manager, controller, or cashier can transfer orders.');

            return;
        }

        $this->validate([
            'transfer_to_user_id' => 'required|exists:users,id',
            'transfer_comment' => 'nullable|string|max:500',
        ]);

        $order = Order::find($this->selectedOrderId);
        if (! $order) {
            return;
        }
        if ($order->order_status === 'PAID' || $order->order_status === 'CANCELLED') {
            session()->flash('error', 'Only OPEN or CONFIRMED orders can be transferred.');

            return;
        }

        $order->update([
            'transferred_from_id' => Auth::id(),
            'waiter_id' => (int) $this->transfer_to_user_id,
            'transfer_comment' => $this->transfer_comment,
        ]);

        $newWaiter = \App\Models\User::find((int) $this->transfer_to_user_id);
        if ($newWaiter && $order->relationLoaded('table') === false) {
            $order->load('table');
        }
        if ($newWaiter) {
            $newWaiter->notify(new OrderPlacedNotification($order, 'assigned'));
        }

        session()->flash('message', 'Order transferred to new user.');

        $this->showTransferForm = false;
        $this->transfer_to_user_id = '';
        $this->transfer_comment = '';

        $this->selectOrder($order->id);
    }

    public function addItem()
    {
        if (! $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if (! $order || $order->order_status === 'CANCELLED') {
            session()->flash('error', 'Cannot add items: order is not active.');

            return;
        }
        if (! $this->canUserAddItems($order)) {
            session()->flash('error', 'Only the waiter who created the order or the cashier can add items.');

            return;
        }

        $this->validate([
            'add_menu_item_id' => 'required|exists:menu_items,menu_item_id',
            'add_quantity' => 'required|integer|min:1',
            'add_item_notes' => 'nullable|string|max:500',
        ]);

        $menuItem = MenuItem::find($this->add_menu_item_id);
        $unitPrice = (float) $menuItem->sale_price;
        $qty = (int) $this->add_quantity;

        $existing = OrderItem::where('order_id', $order->id)
            ->where('menu_item_id', $menuItem->menu_item_id)
            ->first();

        if ($existing) {
            $newQty = $existing->quantity + $qty;
            $existing->update([
                'quantity' => $newQty,
                'line_total' => $unitPrice * $newQty,
            ]);
        } else {
            $station = trim($this->add_station ?? '') ?: $menuItem->preparation_station;
            $station = ($station && PreparationStation::isActiveStation($station)) ? $station : null;
            OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->menu_item_id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $qty,
                'notes' => trim($this->add_item_notes) ?: null,
                'posted_to_station' => $station,
            ]);
        }

        $this->add_menu_item_id = '';
        $this->add_quantity = 1;
        $this->add_item_notes = '';
        $this->add_item_search = '';
        $this->add_station = '';
        $this->selectOrder($order->id);
    }

    public function getStationsProperty(): array
    {
        return PreparationStation::getActiveForPos();
    }

    /**
     * Open modal to confirm station when posting a single item.
     */
    public function openPostItemModal(int $orderItemId): void
    {
        $item = OrderItem::with('menuItem')->find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        if ($item->isPosted()) {
            session()->flash('message', 'Item already posted.');

            return;
        }
        $this->postItemModalOrderItemId = $orderItemId;
        $stations = PreparationStation::getActiveForPos();
        $default = $item->posted_to_station ?: $item->menuItem?->preparation_station ?: '';
        $this->postItemModalStation = ($default !== '' && array_key_exists($default, $stations)) ? $default : (array_key_first($stations) ?: '');
    }

    public function closePostItemModal(): void
    {
        $this->postItemModalOrderItemId = null;
        $this->postItemModalStation = '';
    }

    /**
     * Open per-item options / ingredients modal.
     * Phase A: use a small hard-coded set of choices (temperature, sugar, ice, no-onion/no-salt/no-sugar).
     */
    public function openEditItemOptions(int $orderItemId): void
    {
        if (! $this->selectedOrderId) {
            return;
        }
        $item = OrderItem::with('menuItem')->find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if (! $order || ! $order->canEditItems()) {
            session()->flash('error', 'Order is not open.');

            return;
        }
        if (! $this->canUserAddItems($order)) {
            session()->flash('error', 'Only the waiter who created the order or the cashier can adjust item options.');

            return;
        }

        $options = $item->selected_options ?? [];
        $overrides = $item->ingredient_overrides ?? ['no' => [], 'extra' => []];
        $this->editOptionsOrderItemId = $orderItemId;
        $this->editOptionsData = [
            'temperature' => $options['temperature'] ?? 'default',
            'sugar' => $options['sugar'] ?? 'default',
            'ice' => $options['ice'] ?? 'default',
            'ingredients' => [
                'no_onion' => in_array('onion', $overrides['no'] ?? [], true),
                'no_salt' => in_array('salt', $overrides['no'] ?? [], true),
                'no_sugar' => in_array('sugar', $overrides['no'] ?? [], true),
            ],
            'notes' => $item->notes ?? '',
        ];

        // Load dynamic option groups configured for this menu item (if any),
        // and mark selected options based on the existing selected_options array.
        $this->editOptionGroups = [];
        $menuItem = $item->menuItem;
        if ($menuItem) {
            $groups = $menuItem->optionGroups()->with('options')->orderBy('display_order')->get();
            foreach ($groups as $group) {
                $groupKey = $group->code ?: \Illuminate\Support\Str::slug($group->name, '_');
                $current = $options[$groupKey] ?? null;
                $isMulti = $group->type === 'multi';
                $selectedValues = $isMulti && is_array($current) ? $current : (array) ($current ?? []);
                $this->editOptionGroups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'type' => $group->type,
                    'display_order' => $group->display_order,
                    'options' => $group->options->sortBy('display_order')->values()->map(function (\App\Models\MenuItemOption $opt) use ($selectedValues) {
                        $val = $opt->value ?: \Illuminate\Support\Str::slug($opt->label, '_');

                        return [
                            'id' => $opt->id,
                            'label' => $opt->label,
                            'value' => $val,
                            'price_delta' => (string) $opt->price_delta,
                            'is_default' => $opt->is_default,
                            'display_order' => $opt->display_order,
                            'selected' => in_array($val, $selectedValues, true),
                        ];
                    })->toArray(),
                ];
            }
        }
    }

    public function closeEditItemOptions(): void
    {
        $this->editOptionsOrderItemId = null;
        $this->editOptionsData = [
            'temperature' => '',
            'sugar' => '',
            'ice' => '',
            'ingredients' => [],
            'notes' => '',
        ];
        $this->editOptionGroups = [];
    }

    public function saveItemOptions(): void
    {
        if (! $this->selectedOrderId || ! $this->editOptionsOrderItemId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if (! $order || ! $order->canEditItems()) {
            session()->flash('error', 'Order is not open.');

            return;
        }
        if (! $this->canUserAddItems($order)) {
            session()->flash('error', 'Only the waiter who created the order or the cashier can adjust item options.');

            return;
        }

        $this->validate([
            'editOptionsData.temperature' => 'nullable|in:default,cold,warm,hot',
            'editOptionsData.sugar' => 'nullable|in:default,no,less,extra',
            'editOptionsData.ice' => 'nullable|in:default,no,extra',
            'editOptionsData.notes' => 'nullable|string|max:255',
        ], [], [
            'editOptionsData.notes' => 'Comment',
        ]);

        $item = OrderItem::find($this->editOptionsOrderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            $this->closeEditItemOptions();

            return;
        }

        // If there are dynamic option groups configured for this item, use them;
        // otherwise fall back to the simple temperature/sugar/ice structure.
        $selectedOptions = [];
        if (! empty($this->editOptionGroups)) {
            foreach ($this->editOptionGroups as $group) {
                $key = $group['code'] ?? null;
                if (! $key) {
                    $key = \Illuminate\Support\Str::slug((string) ($group['name'] ?? ''), '_');
                }
                if (! $key) {
                    continue;
                }
                $type = $group['type'] ?? 'single';
                $values = [];
                foreach ($group['options'] ?? [] as $opt) {
                    if (! empty($opt['selected'])) {
                        $values[] = $opt['value'] ?? \Illuminate\Support\Str::slug((string) ($opt['label'] ?? ''), '_');
                    }
                }
                if ($type === 'single') {
                    $selectedOptions[$key] = $values[0] ?? null;
                } else {
                    $selectedOptions[$key] = $values;
                }
            }
        } else {
            $selectedOptions = [
                'temperature' => $this->editOptionsData['temperature'] ?: 'default',
                'sugar' => $this->editOptionsData['sugar'] ?: 'default',
                'ice' => $this->editOptionsData['ice'] ?: 'default',
            ];
        }

        $no = [];
        if (! empty($this->editOptionsData['ingredients']['no_onion'])) {
            $no[] = 'onion';
        }
        if (! empty($this->editOptionsData['ingredients']['no_salt'])) {
            $no[] = 'salt';
        }
        if (! empty($this->editOptionsData['ingredients']['no_sugar'])) {
            $no[] = 'sugar';
        }

        $item->selected_options = $selectedOptions;
        $item->ingredient_overrides = [
            'no' => $no,
            'extra' => [],
        ];
        $item->notes = $this->editOptionsData['notes'] ?: null;
        $item->save();

        $this->closeEditItemOptions();
        $this->selectOrder($order->id);
        session()->flash('message', 'Item options updated.');
    }

    /**
     * Post a single item to the selected station (from modal). Only if not yet posted.
     */
    public function postItem(int $orderItemId, ?string $station = null): void
    {
        $item = OrderItem::with('menuItem')->find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        $user = Auth::user();
        if (! $order || $order->order_status === 'CANCELLED') {
            session()->flash('error', 'Order is not active.');

            return;
        }
        if (! $order->canEditItems()) {
            // Allow cashier to post additional items even if the invoice is already PAID,
            // as long as the order ticket has not been printed yet.
            if (! ($user?->isCashier() && $order->order_ticket_printed_at === null)) {
                session()->flash('error', 'Order is not open.');

                return;
            }
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'You cannot post items for this order.');

            return;
        }
        if ($item->isPosted()) {
            session()->flash('message', 'Item already posted to station.');
            $this->closePostItemModal();
            $this->selectOrder($order->id);

            return;
        }
        $station = $station ?? $this->postItemModalStation;
        $station = trim($station ?? '') ?: ($item->menuItem?->preparation_station ?? '');
        if (! $station) {
            session()->flash('error', 'Select a station for this item.');

            return;
        }
        if (! PreparationStation::isActiveStation($station)) {
            session()->flash('error', 'Cannot post to an inactive station. Please choose an active station.');

            return;
        }
        $item->update([
            'posted_to_station' => $station,
            'sent_to_station_at' => now(),
        ]);
        session()->flash('message', 'Item posted to '.$this->stationLabel($station).'.');
        $this->closePostItemModal();
        $this->selectOrder($order->id);
    }

    /**
     * Mark a single item as printed (KOT). Only if not yet printed.
     */
    public function printItem(int $orderItemId): void
    {
        $item = OrderItem::find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        $user = Auth::user();
        $canPrintPaid = $order
            && $order->order_status === 'PAID'
            && $order->order_ticket_printed_at === null
            && ($user?->isCashier() || $user?->isManager() || $user?->isRestaurantManager() || $user?->isSuperAdmin());

        if (! $order || ! in_array($order->order_status, ['OPEN', 'CONFIRMED'], true) && ! $canPrintPaid) {
            session()->flash('error', 'Order is not available for printing.');

            return;
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'You cannot print items for this order.');

            return;
        }
        if ($item->isPrinted()) {
            session()->flash('message', 'Item already printed.');
            $this->selectOrder($order->id);

            return;
        }
        $item->update(['printed_at' => now()]);
        session()->flash('message', 'Item marked as printed.');
        $this->selectOrder($order->id);
    }

    /**
     * Request void for an item (after it was posted/printed). Requires approval.
     */
    public function requestVoidItem(int $orderItemId, string $reason = ''): void
    {
        $item = OrderItem::find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if (! $order || $order->order_status === 'CANCELLED') {
            session()->flash('error', 'Order is not active.');

            return;
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'You cannot request void for this order.');

            return;
        }
        if ($item->isVoided()) {
            session()->flash('error', 'Item is already voided.');

            return;
        }
        if (! $item->isPosted() && ! $item->isPrinted()) {
            // Cashiers cannot remove items directly; request a void for manager approval instead.
            if (! Auth::user()?->isCashier()) {
                session()->flash('error', 'Use Remove for items not yet posted or printed.');

                return;
            }
        }
        if ($item->voidRequests()->where('status', OrderItemVoidRequest::STATUS_PENDING)->exists()) {
            session()->flash('error', 'A void request is already pending for this item.');

            return;
        }
        $item->voidRequests()->create([
            'requested_by_id' => Auth::id(),
            'reason' => trim($reason) ?: null,
            'status' => OrderItemVoidRequest::STATUS_PENDING,
        ]);
        $request = OrderItemVoidRequest::with(['orderItem.menuItem', 'orderItem.order', 'requestedBy'])->where('order_item_id', $item->id)->latest()->first();
        if ($request) {
            $approvers = \App\Models\User::where('is_active', true)->get()->filter(fn ($u) => $u->hasPermission('pos_approve_void'));
            foreach ($approvers as $approver) {
                $approver->notify(new VoidRequestNotification($request));
            }
        }
        session()->flash('message', 'Void request submitted. Waiting for approval.');
        $this->selectOrder($order->id);
    }

    /**
     * Approve a void request (authorized user). Sets voided_at and voided_by on the order item.
     */
    public function approveVoidRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_approve_void')) {
            session()->flash('error', 'You do not have permission to approve void requests.');

            return;
        }
        $request = OrderItemVoidRequest::with('orderItem.order')->find($requestId);
        if (! $request || $request->status !== OrderItemVoidRequest::STATUS_PENDING) {
            session()->flash('error', 'Invalid or already resolved request.');

            return;
        }
        if ($request->orderItem->order_id != $this->selectedOrderId) {
            session()->flash('error', 'Request does not belong to this order.');

            return;
        }
        $request->orderItem->update([
            'voided_at' => now(),
            'voided_by_id' => $user->id,
        ]);
        $request->update([
            'status' => OrderItemVoidRequest::STATUS_APPROVED,
            'approved_by_id' => $user->id,
            'resolved_at' => now(),
        ]);
        session()->flash('message', 'Void request approved. Kitchen/bar will see the item as voided.');
        $this->selectOrder($this->selectedOrderId);
    }

    /**
     * Reject a void request.
     */
    public function rejectVoidRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_approve_void')) {
            session()->flash('error', 'You do not have permission to reject void requests.');

            return;
        }
        $request = OrderItemVoidRequest::with('orderItem')->find($requestId);
        if (! $request || $request->status !== OrderItemVoidRequest::STATUS_PENDING) {
            session()->flash('error', 'Invalid or already resolved request.');

            return;
        }
        if ($request->orderItem->order_id != $this->selectedOrderId) {
            return;
        }
        $request->update([
            'status' => OrderItemVoidRequest::STATUS_REJECTED,
            'approved_by_id' => $user->id,
            'resolved_at' => now(),
        ]);
        session()->flash('message', 'Void request rejected.');
        $this->selectOrder($this->selectedOrderId);
    }

    /**
     * Select a menu item from search results to add (sets selection; user then clicks Add or can quick-add).
     */
    public function selectMenuItemForAdd(int $menuItemId): void
    {
        $this->add_menu_item_id = $menuItemId;
    }

    /**
     * Quick-add: add one of the selected item immediately.
     */
    public function quickAddItem(int $menuItemId, int $qty = 1): void
    {
        $this->add_menu_item_id = $menuItemId;
        $this->add_quantity = $qty;
        $this->addItem();
    }

    public function getFilteredMenuItemsProperty(): array
    {
        $search = trim($this->add_item_search);
        $rows = $this->menuItems;

        if ($this->add_sales_category_filter !== '') {
            $rows = array_values(array_filter($rows, function ($mi) {
                return (string) ($mi['sales_category'] ?? 'food') === $this->add_sales_category_filter;
            }));
        }

        if ($search === '') {
            return $rows;
        }
        $lower = mb_strtolower($search);

        return array_values(array_filter($rows, function ($mi) use ($lower) {
            $name = mb_strtolower($mi['name'] ?? '');
            $code = mb_strtolower($mi['code'] ?? '');

            return str_contains($name, $lower) || str_contains($code, $lower);
        }));
    }

    /** When user selects a menu item to add, set default station from that item */
    public function updatedAddMenuItemId($value): void
    {
        if (! $value) {
            $this->add_station = '';

            return;
        }
        $menuItem = collect($this->menuItems)->firstWhere('menu_item_id', (int) $value);
        $this->add_station = $menuItem['preparation_station'] ?? '';
    }

    public function removeOrderItem($orderItemId)
    {
        $item = OrderItem::find($orderItemId);
        if (! $item || $item->order_id != $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if (! $order->canEditItems()) {
            if (Auth::user()?->isCashier()) {
                session()->flash('error', 'Cashiers cannot remove items from a paid order. Request void for manager approval instead.');

                return;
            }
            session()->flash('error', 'Cannot remove items: order is not open.');

            return;
        }
        if (! $this->canUserRemoveItems($order)) {
            session()->flash('error', 'Only the waiter who created the order can remove items. Use void / modification request instead.');

            return;
        }
        if (! $item->canRemove()) {
            session()->flash('error', 'Cannot remove: item was already posted or printed. Request a void instead.');

            return;
        }
        $item->delete();
        session()->flash('message', 'Item removed.');
        $this->selectOrder($order->id);
    }

    public function requestInvoice()
    {
        if (! $this->selectedOrderId) {
            return;
        }
        $order = Order::with('orderItems')->find($this->selectedOrderId);
        if ($order->order_status !== 'OPEN') {
            session()->flash('error', 'Order is not open.');

            return;
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'Only the assigned waiter or manager can request an invoice for this order.');

            return;
        }
        $nonVoidedItems = $order->orderItems->whereNull('voided_at');
        if ($nonVoidedItems->isEmpty()) {
            session()->flash('error', 'Add at least one non-voided item before requesting invoice.');

            return;
        }

        $total = $nonVoidedItems->sum('line_total');

        $createdInvoice = null;
        DB::transaction(function () use ($order, $total, &$createdInvoice) {
            $order->update(['order_status' => 'CONFIRMED']);
            $createdInvoice = $order->invoice()->create([
                'invoice_number' => InvoiceNumberService::generate(),
                'total_amount' => $total,
                'invoice_status' => 'UNPAID',
            ]);
        });

        if ($createdInvoice) {
            ActivityLogger::log(
                'pos.invoice_created',
                sprintf('Invoice %s created for order #%s (total %s)', $createdInvoice->invoice_number ?? $createdInvoice->id, $order->id, number_format((float) $total, 2, '.', '')),
                Invoice::class,
                $createdInvoice->id,
                null,
                ['order_id' => $order->id, 'total_amount' => $total],
                ActivityLogModule::POS
            );
        }

        session()->flash('message', 'Invoice created. You can now receive payment.');
        $this->selectOrder($order->id);
    }

    public function voidOrder()
    {
        if (! $this->selectedOrderId) {
            return;
        }
        $order = Order::find($this->selectedOrderId);
        if ($order->order_status !== 'OPEN') {
            session()->flash('error', 'Only open orders can be voided.');

            return;
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'Only the assigned waiter or manager can void this order.');

            return;
        }
        $tableId = $order->table_id;
        $order->update(['order_status' => 'CANCELLED']);
        if ($tableId) {
            RestaurantTable::where('id', $tableId)->update(['is_active' => true]);
        }
        ActivityLogger::log(
            'pos.order_voided',
            sprintf('Open order #%s voided / cancelled', $order->id),
            Order::class,
            $order->id,
            null,
            ['order_status' => 'CANCELLED'],
            ActivityLogModule::POS
        );
        session()->flash('message', 'Order voided.');
        $this->closeOrderDetail();
    }

    /**
     * Mark order ticket as printed (KOT). Posts all items to their default station if not yet posted, then marks as printed.
     */
    public function printOrderTicket(): void
    {
        if (! $this->selectedOrderId) {
            return;
        }
        $order = Order::with(['orderItems.menuItem'])->find($this->selectedOrderId);
        if (! $order) {
            return;
        }
        if (! in_array($order->order_status, ['OPEN', 'CONFIRMED'], true)) {
            session()->flash('error', 'Only open or confirmed orders can have order ticket printed.');

            return;
        }
        if (! $this->canModifyOrder($order)) {
            session()->flash('error', 'Only the assigned waiter or manager can print order ticket.');

            return;
        }

        $now = now();
        foreach ($order->orderItems as $item) {
            if (! $item->sent_to_station_at) {
                $defaultStation = $item->menuItem?->preparation_station;
                $stationToPost = ($defaultStation && PreparationStation::isActiveStation($defaultStation))
                    ? $defaultStation
                    : ($item->posted_to_station ?: null);
                $item->update([
                    'posted_to_station' => $stationToPost,
                    'sent_to_station_at' => $now,
                ]);
            }
            $item->update(['printed_at' => $now]);
        }
        $order->update(['order_ticket_printed_at' => $now]);
        session()->flash('message', 'Order ticket printed. Items posted to default stations.');
        $this->selectOrder($order->id);
    }

    public function updatedFilterStatus()
    {
        $this->resetPage();
    }

    /**
     * Tables available for new orders: active and not currently having an OPEN order.
     * After payment is confirmed, the table is set active again (see PosPayment).
     */
    public function getTablesProperty()
    {
        return RestaurantTable::active()
            ->whereDoesntHave('orders', fn ($q) => $q->where('order_status', 'OPEN'))
            ->orderBy('table_number')
            ->get();
    }

    public function render()
    {
        return view('livewire.pos.pos-orders', [
            // Uses the computed paginator from getOrdersProperty()
            'orders' => $this->orders,
        ])->layout('livewire.layouts.app-layout');
    }
}
