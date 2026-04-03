<?php

namespace App\Livewire;

use App\Models\BusinessDay;
use App\Models\DayShift;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\OperationalShift;
use App\Models\OperationalShiftOpenRequest;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\ShiftLog;
use App\Models\ShiftTemplate;
use App\Services\BusinessDayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\DayShiftService;
use App\Services\OperationalShiftActionGate;
use App\Services\OperationalShiftOpenRequestService;
use App\Services\OperationalShiftService;
use App\Services\PosSessionService;
use App\Services\TimeAndShiftResolver;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ShiftManagement extends Component
{
    use WithPagination;

    public $hotel;
    public $shifts = [];
    public $showShiftForm = false;
    public $editingShiftId = null;
    
    // Shift form fields
    public $name = '';
    public $code = '';
    public $start_time = '';
    public $end_time = '';
    public $description = '';
    public $order = 0;
    public $is_active = true;
    
    // Hotel configuration
    public $business_day_rollover_time = '03:00:00';
    public $shifts_enabled = true;
    public $shift_mode = 'STRICT_SHIFT';
    /** @var string Hotel timezone for business day and all POS times (e.g. Africa/Kigali) */
    public $timezone = 'Africa/Kigali';

    /** @var string per_module|global — one shift for all modules vs POS/FO separate */
    public string $operational_shift_scope = 'per_module';

    /** When operational_shifts table exists */
    public bool $useOperationalShifts = false;

    /** @var array<string, mixed|null> */
    public array $opShiftGlobal = [];

    /** @var array<string, mixed|null> */
    public array $opShiftPos = [];

    /** @var array<string, mixed|null> */
    public array $opShiftFrontOffice = [];

    /** @var array<string, mixed|null> */
    public array $opShiftStore = [];

    public bool $showPosScope = true;

    public bool $showFrontOfficeScope = true;

    public bool $showStoreScope = true;

    /** @var array<int, array<string, mixed>> */
    public array $operationalShiftHistory = [];

    /** Pending "open shift" requests from staff (when operational_shift_open_requests exists) */
    public array $pendingOpenRequests = [];

    public string $fulfill_open_note = '';

    public bool $showRejectRequestModal = false;

    public ?int $rejectRequestId = null;

    public string $reject_request_note = '';

    /** Super Admin / Director / GM / Manager — edit hotel shift config */
    public bool $canEditShiftHotelConfig = false;

    public bool $showCloseOpModal = false;

    public ?int $opShiftToCloseId = null;

    public string $close_op_comment = '';

    /** @var array<string, mixed> */
    public array $closeChecklist = [];

    public string $open_op_note = '';
    
    // Business day (new flow)
    public $openBusinessDay = null;
    public $dayShifts = [];
    /** @var array Past business days with sales summary for listing */
    public $pastBusinessDays = [];
    
    // Shift templates (new flow)
    public $shiftTemplates = [];
    public $showTemplateForm = false;
    public $editingTemplateId = null;
    public $template_name = '';
    public $template_start_time = '';
    public $template_end_time = '';
    public $template_display_order = 0;
    public $template_is_active = true;
    
    // Shift log management (legacy)
    public $showShiftLogs = false;
    public $selectedShiftLogs = [];
    
    // Confirmation dialogs
    public $showDeleteConfirmation = false;
    public $shiftToDelete = null;
    public $showCloseConfirmation = false;
    public $shiftLogToClose = null;
    public $closing_cash = '';
    public $close_notes = '';
    public $showCloseDayShiftConfirmation = false;
    public $dayShiftToClose = null;

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! OperationalShiftService::userCanAccessShiftManagementPage($user)) {
            abort(403, 'You do not have permission to access shift management.');
        }

        $this->canEditShiftHotelConfig = (bool) ($user->isSuperAdmin() || $user->canNavigateModules());

        $this->loadHotelConfig();
        $this->useOperationalShifts = OperationalShiftService::isEnabled();
        $this->loadShifts();
        if (! $this->useOperationalShifts) {
            $this->loadOpenBusinessDay();
            $this->loadShiftTemplates();
            $this->loadPastBusinessDays();
        }
        $this->loadOperationalShiftsUi();
    }

    public function loadOperationalShiftsUi(): void
    {
        if (! $this->useOperationalShifts) {
            return;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $user = Auth::user();
        $modules = $user ? $user->getAccessibleModules() : collect();
        $hasRestaurant = $modules->contains('slug', 'restaurant');
        $hasFrontOffice = $modules->contains('slug', 'front-office');
        $hasStore = $modules->contains('slug', 'store');

        $this->showPosScope = $hasRestaurant;
        $this->showFrontOfficeScope = $hasFrontOffice;
        $this->showStoreScope = $hasStore;

        $toArr = function (?OperationalShift $s): array {
            if (! $s) {
                return [];
            }
            $hours = round($s->durationHours(), 1);

            return [
                'id' => $s->id,
                'module_scope' => $s->module_scope,
                'reference_date' => $s->reference_date?->format('Y-m-d'),
                'opened_at' => $s->opened_at?->format('Y-m-d H:i'),
                'open_note' => $s->open_note,
                'hours_open' => $hours,
                'over_24h' => $hours >= 24.0,
            ];
        };

        $this->opShiftGlobal = $toArr(OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL));
        $this->opShiftPos = $toArr(OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_POS));
        $this->opShiftFrontOffice = $toArr(OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_FRONT_OFFICE));
        $this->opShiftStore = $toArr(OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_STORE));

        $this->operationalShiftHistory = OperationalShiftService::recentHistory($hotel, 21)
            ->map(function (OperationalShift $s) {
                $hours = round($s->durationHours(), 1);

                return [
                    'id' => $s->id,
                    'scope' => $s->module_scope,
                    'scope_label' => OperationalShiftActionGate::labelForScope($s->module_scope),
                    'reference_date' => $s->reference_date?->format('Y-m-d'),
                    'opened_at' => $s->opened_at?->format('Y-m-d H:i'),
                    'closed_at' => $s->closed_at?->format('Y-m-d H:i'),
                    'opened_by' => $s->opener?->name ?? '—',
                    'closed_by' => $s->closer?->name ?? '—',
                    'close_comment' => $s->close_comment,
                    'status' => $s->status,
                    'hours_open' => $hours,
                    'over_24h' => $s->status === OperationalShift::STATUS_OPEN && $hours >= 24.0,
                ];
            })
            ->values()
            ->all();

        $this->loadPendingOpenRequests();
    }

    public function loadPendingOpenRequests(): void
    {
        $this->pendingOpenRequests = [];
        if (! $this->useOperationalShifts || ! OperationalShiftOpenRequestService::isEnabled()) {
            return;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $user = Auth::user();
        $this->pendingOpenRequests = OperationalShiftOpenRequestService::pendingForHotel($hotel)
            ->limit(50)
            ->get()
            ->map(function (OperationalShiftOpenRequest $r) use ($user) {
                return [
                    'id' => $r->id,
                    'module_scope' => $r->module_scope,
                    'scope_label' => OperationalShiftActionGate::labelForScope($r->module_scope),
                    'requested_by' => $r->requester?->name ?? '—',
                    'note' => $r->note,
                    'created_at' => $r->created_at?->format('Y-m-d H:i'),
                    'can_fulfill' => $user && OperationalShiftOpenRequestService::userCanOpenOperationalScope($user, $r->module_scope),
                    'can_reject' => $user && OperationalShiftOpenRequestService::userCanResolveRequests($user),
                ];
            })
            ->values()
            ->all();
    }

    public function fulfillPendingOpenRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $req = OperationalShiftOpenRequest::query()->find($requestId);
        if (! $req || ! $req->isPending()) {
            session()->flash('error', 'Request not found or already handled.');
            $this->loadOperationalShiftsUi();

            return;
        }

        try {
            $note = trim($this->fulfill_open_note) !== '' ? trim($this->fulfill_open_note) : null;
            OperationalShiftOpenRequestService::fulfillWithOpenShift($req, $user, $note);
            $this->fulfill_open_note = '';
            session()->flash('message', 'Shift opened. Pending request(s) for this area are marked fulfilled.');
            $this->loadOperationalShiftsUi();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function promptRejectOpenRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! OperationalShiftOpenRequestService::userCanResolveRequests($user)) {
            session()->flash('error', 'You are not allowed to reject shift requests.');

            return;
        }
        $this->rejectRequestId = $requestId;
        $this->reject_request_note = '';
        $this->showRejectRequestModal = true;
    }

    public function cancelRejectOpenRequest(): void
    {
        $this->showRejectRequestModal = false;
        $this->rejectRequestId = null;
        $this->reject_request_note = '';
    }

    public function confirmRejectOpenRequest(): void
    {
        $user = Auth::user();
        if (! $user || ! $this->rejectRequestId) {
            $this->cancelRejectOpenRequest();

            return;
        }

        $this->validate([
            'reject_request_note' => 'nullable|string|max:2000',
        ]);

        $req = OperationalShiftOpenRequest::query()->find($this->rejectRequestId);
        if (! $req || ! $req->isPending()) {
            $this->cancelRejectOpenRequest();
            session()->flash('error', 'Request not found or already handled.');
            $this->loadOperationalShiftsUi();

            return;
        }

        try {
            OperationalShiftOpenRequestService::reject(
                $req,
                $user,
                $this->reject_request_note !== '' ? trim($this->reject_request_note) : null
            );
            session()->flash('message', 'Request rejected.');
            $this->cancelRejectOpenRequest();
            $this->loadOperationalShiftsUi();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function startOpenOperationalShift(string $scope): void
    {
        $user = Auth::user();
        if (! $user) {
            session()->flash('error', 'Not authenticated.');

            return;
        }

        if ($scope === OperationalShift::SCOPE_GLOBAL && ! OperationalShiftService::userCanOpenGlobal($user)) {
            session()->flash('error', 'You do not have permission to open a global shift.');

            return;
        }
        if ($scope === OperationalShift::SCOPE_POS && ! OperationalShiftService::userCanOpenPos($user)) {
            session()->flash('error', 'You do not have permission to open a POS shift.');

            return;
        }
        if ($scope === OperationalShift::SCOPE_FRONT_OFFICE && ! OperationalShiftService::userCanOpenFrontOffice($user)) {
            session()->flash('error', 'You do not have permission to open a Front office shift.');

            return;
        }
        if ($scope === OperationalShift::SCOPE_STORE && ! OperationalShiftService::userCanOpenStore($user)) {
            session()->flash('error', 'You do not have permission to open a Store shift.');

            return;
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            session()->flash('error', 'Hotel context required.');

            return;
        }

        try {
            $note = trim($this->open_op_note) !== '' ? trim($this->open_op_note) : null;
            OperationalShiftService::openShift($hotel, $scope, $user->id, $note);
            $this->open_op_note = '';
            session()->flash('message', 'Shift opened. Reference date: '.$hotel->getTodayYmd().' (hotel date).');
            $this->loadOperationalShiftsUi();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function promptCloseOperationalShift(int $shiftId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $shift = OperationalShift::find($shiftId);
        if (! $shift || $shift->status !== OperationalShift::STATUS_OPEN) {
            session()->flash('error', 'Shift not found or already closed.');

            return;
        }

        if ($shift->module_scope === OperationalShift::SCOPE_GLOBAL && ! OperationalShiftService::userCanCloseGlobal($user)) {
            session()->flash('error', 'You do not have permission to close a global shift.');

            return;
        }
        if ($shift->module_scope === OperationalShift::SCOPE_POS && ! OperationalShiftService::userCanClosePos($user)) {
            session()->flash('error', 'You do not have permission to close a POS shift.');

            return;
        }
        if ($shift->module_scope === OperationalShift::SCOPE_FRONT_OFFICE && ! OperationalShiftService::userCanCloseFrontOffice($user)) {
            session()->flash('error', 'You do not have permission to close a Front office shift.');

            return;
        }
        if ($shift->module_scope === OperationalShift::SCOPE_STORE && ! OperationalShiftService::userCanCloseStore($user)) {
            session()->flash('error', 'You do not have permission to close a Store shift.');

            return;
        }

        $this->opShiftToCloseId = $shiftId;
        $this->close_op_comment = '';
        $this->buildCloseChecklist($shift);
        $this->showCloseOpModal = true;
    }

    public function cancelCloseOperationalShift(): void
    {
        $this->showCloseOpModal = false;
        $this->opShiftToCloseId = null;
        $this->close_op_comment = '';
        $this->closeChecklist = [];
    }

    public function confirmCloseOperationalShift(): void
    {
        $user = Auth::user();
        if (! $user || ! $this->opShiftToCloseId) {
            return;
        }

        $shift = OperationalShift::find($this->opShiftToCloseId);
        if (! $shift) {
            $this->cancelCloseOperationalShift();

            return;
        }

        $this->validate([
            'close_op_comment' => 'nullable|string|max:5000',
        ], [], ['close_op_comment' => 'close comment']);

        try {
            OperationalShiftService::closeShift($shift, $user->id, $this->close_op_comment);
            session()->flash('message', 'Shift closed successfully.');
            $this->cancelCloseOperationalShift();
            $this->loadOperationalShiftsUi();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    protected function buildCloseChecklist(OperationalShift $shift): void
    {
        $hotelId = (int) $shift->hotel_id;
        $today = Hotel::getHotel()?->getTodayYmd() ?? Carbon::now()->format('Y-m-d');

        $unpaidOrdersCount = 0;
        $unpaidOrdersTotal = 0.0;
        if (\Schema::hasTable('orders') && \Schema::hasTable('invoices')) {
            $unpaidOrdersQuery = Invoice::whereNotIn('invoice_status', ['PAID', 'CREDIT'])
                ->whereHas('order.session', function ($q) use ($hotelId, $shift) {
                    $q->where('operational_shift_id', $shift->id)
                        ->whereHas('businessDay.hotel', function ($q2) use ($hotelId) {
                            $q2->where('id', $hotelId);
                        });
                })
                ->whereDate('created_at', $today);
            $unpaidOrdersCount = (int) $unpaidOrdersQuery->count();
            $unpaidOrdersTotal = (float) $unpaidOrdersQuery->sum('total_amount');
        }

        $pendingReservationsCount = 0;
        if (\Schema::hasTable('reservations')) {
            $pendingReservationsCount = Reservation::where('hotel_id', $hotelId)
                ->whereDate('check_in_date', $today)
                ->whereIn('status', [
                    Reservation::STATUS_CONFIRMED,
                    Reservation::STATUS_CHECKED_IN,
                ])
                ->count();
        }

        $openRequestsCount = 0;
        if (OperationalShiftOpenRequestService::isEnabled()) {
            $openRequestsCount = OperationalShiftOpenRequest::where('hotel_id', $hotelId)
                ->where('module_scope', $shift->module_scope)
                ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
                ->count();
        }

        $this->closeChecklist = [
            'unpaid_orders_count' => $unpaidOrdersCount,
            'unpaid_orders_total' => $unpaidOrdersTotal,
            'pending_reservations_count' => $pendingReservationsCount,
            'open_requests_count' => $openRequestsCount,
        ];
    }

    /**
     * Load recent business days with order count and total sales for each.
     */
    public function loadPastBusinessDays(): void
    {
        $hotel = Hotel::getHotel();
        $days = BusinessDay::when($hotel, fn ($q) => $q->where('hotel_id', $hotel->id))
            ->orderByDesc('business_date')
            ->limit(31)
            ->get();
        if ($days->isEmpty()) {
            $this->pastBusinessDays = [];
            return;
        }
        $ids = $days->pluck('id')->toArray();
        $stats = DB::table('orders')
            ->join('pos_sessions', 'orders.session_id', '=', 'pos_sessions.id')
            ->leftJoin('invoices', 'orders.id', '=', 'invoices.order_id')
            ->whereIn('pos_sessions.business_day_id', $ids)
            ->where('orders.order_status', 'PAID')
            ->groupBy('pos_sessions.business_day_id')
            ->selectRaw('pos_sessions.business_day_id, count(orders.id) as orders_count, coalesce(sum(invoices.total_amount), 0) as total_sales')
            ->get()
            ->keyBy('business_day_id');
        $this->pastBusinessDays = $days->map(function (BusinessDay $bd) use ($stats) {
            $s = $stats->get($bd->id);
            return [
                'id' => $bd->id,
                'business_date' => $bd->business_date,
                'status' => $bd->status,
                'opened_at' => $bd->opened_at,
                'closed_at' => $bd->closed_at,
                'orders_count' => $s ? (int) $s->orders_count : 0,
                'total_sales' => $s ? (float) $s->total_sales : 0.0,
            ];
        })->toArray();
    }

    public function loadHotelConfig()
    {
        $this->hotel = Hotel::getHotel();
        $this->business_day_rollover_time = $this->hotel->business_day_rollover_time?->format('H:i:s') ?? '03:00:00';
        $this->shifts_enabled = $this->hotel->shifts_enabled ?? true;
        $this->shift_mode = $this->hotel->shift_mode ?? 'STRICT_SHIFT';
        $this->timezone = $this->hotel->getTimezone();
        $this->operational_shift_scope = $this->hotel->operational_shift_scope ?? 'per_module';
    }

    /**
     * Timezone options for dropdown (grouped by region).
     * @return array<string, array<string, string>>
     */
    public static function getTimezoneOptions(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();
        $grouped = [];
        foreach ($identifiers as $tz) {
            $region = explode('/', $tz)[0] ?? 'Other';
            if (!isset($grouped[$region])) {
                $grouped[$region] = [];
            }
            $grouped[$region][$tz] = str_replace('_', ' ', $tz);
        }
        ksort($grouped);
        foreach ($grouped as $k => $v) {
            asort($grouped[$k]);
        }
        return $grouped;
    }

    public function loadOpenBusinessDay()
    {
        $this->openBusinessDay = BusinessDayService::getOpenBusinessDay();
        $this->dayShifts = [];
        if ($this->openBusinessDay) {
            $this->dayShifts = DayShift::where('business_day_id', $this->openBusinessDay->id)
                ->orderBy('start_at')
                ->get()
                ->toArray();

            // If we're using shifts but this business day has none, create them from templates so the user can open a shift
            $hotel = Hotel::getHotel();
            if ($hotel && !$hotel->isNoShiftMode() && empty($this->dayShifts)) {
                // If there are no shift templates but there are legacy shifts (Morning/Evening), create templates from them once
                if (ShiftTemplate::active()->count() === 0) {
                    $legacyShifts = Shift::where('is_system_generated', false)->where('is_active', true)->orderBy('order')->orderBy('start_time')->get();
                    foreach ($legacyShifts as $i => $s) {
                        $start = is_string($s->start_time) ? $s->start_time : $s->start_time->format('H:i:s');
                        $end = is_string($s->end_time) ? $s->end_time : $s->end_time->format('H:i:s');
                        ShiftTemplate::firstOrCreate(
                            ['name' => $s->name, 'start_time' => $start, 'end_time' => $end],
                            ['display_order' => $s->order ?? $i, 'is_active' => true]
                        );
                    }
                    $this->loadShiftTemplates();
                }
                $templatesCount = ShiftTemplate::active()->count();
                if ($templatesCount > 0) {
                    BusinessDayService::createDayShiftsForBusinessDay($this->openBusinessDay);
                    $this->dayShifts = DayShift::where('business_day_id', $this->openBusinessDay->id)
                        ->orderBy('start_at')
                        ->get()
                        ->toArray();
                    if (session()->isStarted()) {
                        session()->flash('message', 'Shifts for this business day were created from your templates. You can now open a shift.');
                    }
                }
            }
        }
    }

    /**
     * When in NO_SHIFT mode, allow manager to close any remaining open shifts from previous days.
     */
    public function closeStaleShifts()
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->canNavigateModules()) {
            session()->flash('error', 'Not authorized.');
            return;
        }
        $logicalDate = BusinessDayService::getLogicalDate();
        $closed = BusinessDayService::closeStaleShiftsBeforeNewDay($logicalDate);
        $n = $closed['shift_logs'] + $closed['day_shifts'];
        if ($n > 0) {
            session()->flash('message', $n . ' open shift(s) from previous days were closed automatically.');
        } else {
            session()->flash('message', 'No open shifts from previous days to close.');
        }
        $this->loadOpenBusinessDay();
    }

    public function loadShiftTemplates()
    {
        $this->shiftTemplates = \App\Models\ShiftTemplate::orderBy('display_order')->orderBy('start_time')->get()->toArray();
    }

    public function openBusinessDay()
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->canNavigateModules()) {
            session()->flash('error', 'Only Admin/Manager can open a business day.');
            return;
        }
        try {
            BusinessDayService::openBusinessDay(Auth::id());
            session()->flash('message', 'Business day opened.');
            $this->loadOpenBusinessDay();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeBusinessDay()
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->canNavigateModules()) {
            session()->flash('error', 'Only Admin/Manager can close a business day.');
            return;
        }
        try {
            BusinessDayService::closeBusinessDay(Auth::id());
            session()->flash('message', 'Business day closed.');
            $this->loadOpenBusinessDay();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openDayShift($dayShiftId)
    {
        $user = Auth::user();
        if (!$user->hasPermission('pos_open_shift') && !$user->isSuperAdmin() && !$user->canNavigateModules()) {
            session()->flash('error', 'You do not have permission to open a shift.');
            return;
        }
        try {
            DayShiftService::openShift($dayShiftId, Auth::id());
            session()->flash('message', 'Shift opened.');
            $this->loadOpenBusinessDay();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeDayShift($dayShiftId)
    {
        $user = Auth::user();
        if (!$user->hasPermission('pos_close_shift') && !$user->isSuperAdmin() && !$user->canNavigateModules()) {
            session()->flash('error', 'You do not have permission to close a shift.');
            return;
        }
        $this->dayShiftToClose = $dayShiftId;
        $this->showCloseDayShiftConfirmation = true;
    }

    public function confirmCloseDayShift()
    {
        if (!$this->dayShiftToClose) return;
        $user = Auth::user();
        if (!$user->hasPermission('pos_close_shift') && !$user->isSuperAdmin() && !$user->canNavigateModules()) {
            session()->flash('error', 'You do not have permission to close a shift.');
            return;
        }
        try {
            DayShiftService::closeShift($this->dayShiftToClose, Auth::id());
            session()->flash('message', 'Shift closed. POS sessions for this shift have been closed.');
            $this->showCloseDayShiftConfirmation = false;
            $this->dayShiftToClose = null;
            $this->loadOpenBusinessDay();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openTemplateForm($id = null)
    {
        $this->editingTemplateId = $id;
        if ($id) {
            $t = ShiftTemplate::find($id);
            $this->template_name = $t->name;
            $this->template_start_time = substr($t->start_time, 0, 5);
            $this->template_end_time = substr($t->end_time, 0, 5);
            $this->template_display_order = $t->display_order;
            $this->template_is_active = $t->is_active;
        } else {
            $this->template_name = '';
            $this->template_start_time = '08:00';
            $this->template_end_time = '16:00';
            $this->template_display_order = 0;
            $this->template_is_active = true;
        }
        $this->showTemplateForm = true;
    }

    public function closeTemplateForm()
    {
        $this->showTemplateForm = false;
        $this->editingTemplateId = null;
    }

    public function saveTemplate()
    {
        $this->validate([
            'template_name' => 'required|string|max:255',
            'template_start_time' => 'required|date_format:H:i',
            'template_end_time' => 'required|date_format:H:i',
            'template_display_order' => 'integer|min:0',
        ]);
        $data = [
            'name' => $this->template_name,
            'start_time' => $this->template_start_time . ':00',
            'end_time' => $this->template_end_time . ':00',
            'display_order' => $this->template_display_order,
            'is_active' => $this->template_is_active,
        ];
        if ($this->editingTemplateId) {
            ShiftTemplate::find($this->editingTemplateId)->update($data);
            session()->flash('message', 'Shift template updated.');
        } else {
            ShiftTemplate::create($data);
            session()->flash('message', 'Shift template added.');
        }
        $this->loadShiftTemplates();
        $this->closeTemplateForm();
    }

    public function loadShifts()
    {
        $this->shifts = Shift::where('is_system_generated', false)
            ->orderBy('order')
            ->orderBy('start_time')
            ->get()
            ->toArray();
    }

    public function saveHotelConfig()
    {
        if (! $this->canEditShiftHotelConfig) {
            session()->flash('error', 'Only Super Admin or hotel managers (Director, GM, Manager) can modify shift configuration.');
            return;
        }

        $rules = [
            'business_day_rollover_time' => 'required|date_format:H:i:s',
            'shifts_enabled' => 'boolean',
            'shift_mode' => 'in:NO_SHIFT,OPTIONAL_SHIFT,STRICT_SHIFT',
            'timezone' => 'required|string|timezone',
        ];
        if ($this->useOperationalShifts && Schema::hasColumn('hotels', 'operational_shift_scope')) {
            $rules['operational_shift_scope'] = 'in:per_module,global';
        }
        $this->validate($rules);

        $prevScope = $this->hotel->operational_shift_scope ?? 'per_module';
        if ($this->useOperationalShifts && Schema::hasColumn('hotels', 'operational_shift_scope') && $prevScope !== $this->operational_shift_scope) {
            $openAny = OperationalShift::where('hotel_id', $this->hotel->id)
                ->where('status', OperationalShift::STATUS_OPEN)
                ->exists();
            if ($openAny) {
                session()->flash('error', 'Close all open operational shifts before changing global vs per-module scope.');

                return;
            }
        }

        $update = [
            'business_day_rollover_time' => $this->business_day_rollover_time,
            'shifts_enabled' => $this->shifts_enabled,
            'shift_mode' => $this->shift_mode,
            'timezone' => $this->timezone,
        ];
        if ($this->useOperationalShifts && Schema::hasColumn('hotels', 'operational_shift_scope')) {
            $update['operational_shift_scope'] = $this->operational_shift_scope;
        }
        $this->hotel->update($update);

        if ($this->shift_mode === 'NO_SHIFT') {
            $closed = BusinessDayService::closeAllOpenShiftsWhenSwitchingToNoShift();
            $n = $closed['shift_logs'] + $closed['day_shifts'];
            if ($n > 0) {
                session()->flash('message', 'Configuration saved. ' . $n . ' open shift(s) were closed automatically (POS now uses business day only).');
            } else {
                session()->flash('message', 'Hotel shift configuration saved successfully!');
            }
        } else {
            session()->flash('message', 'Hotel shift configuration saved successfully!');
        }
        $this->loadHotelConfig();
        if (! $this->useOperationalShifts) {
            $this->loadOpenBusinessDay();
        }
    }

    public function openShiftForm($shiftId = null)
    {
        $this->editingShiftId = $shiftId;
        
        if ($shiftId) {
            $shift = Shift::find($shiftId);
            $this->name = $shift->name;
            $this->code = $shift->code;
            // Convert H:i:s to H:i for HTML5 time input
            $startTime = is_string($shift->start_time) ? $shift->start_time : $shift->start_time->format('H:i:s');
            $endTime = is_string($shift->end_time) ? $shift->end_time : $shift->end_time->format('H:i:s');
            $this->start_time = substr($startTime, 0, 5); // Extract HH:MM from HH:MM:SS
            $this->end_time = substr($endTime, 0, 5); // Extract HH:MM from HH:MM:SS
            $this->description = $shift->description;
            $this->order = $shift->order;
            $this->is_active = $shift->is_active;
        } else {
            $this->resetShiftForm();
        }
        
        $this->showShiftForm = true;
    }

    public function closeShiftForm()
    {
        $this->showShiftForm = false;
        $this->resetShiftForm();
    }

    public function resetShiftForm()
    {
        $this->editingShiftId = null;
        $this->name = '';
        $this->code = '';
        $this->start_time = '';
        $this->end_time = '';
        $this->description = '';
        $this->order = 0;
        $this->is_active = true;
    }

    public function saveShift()
    {
        // Super Admin and hotel managers (Director, GM, Manager, Hotel Admin) can create/edit shifts
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->canNavigateModules()) {
            session()->flash('error', 'Only Super Admin or hotel managers can create or edit shifts.');
            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:shifts,code,' . ($this->editingShiftId ?? ''),
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'description' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Convert H:i format to H:i:s format for database storage
        $this->start_time = $this->start_time . ':00';
        $this->end_time = $this->end_time . ':00';

        // Check if shift has transactions (if editing)
        if ($this->editingShiftId) {
            $shift = Shift::find($this->editingShiftId);
            $hasTransactions = ShiftLog::where('shift_id', $shift->id)
                ->where('is_locked', true)
                ->exists();
            
            if ($hasTransactions) {
                session()->flash('error', 'Cannot edit shift that has closed transactions.');
                return;
            }
        }

        if ($this->editingShiftId) {
            $shift = Shift::find($this->editingShiftId);
            $shift->update([
                'name' => $this->name,
                'code' => $this->code,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'description' => $this->description,
                'order' => $this->order,
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'Shift updated successfully!');
        } else {
            Shift::create([
                'name' => $this->name,
                'code' => $this->code,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'description' => $this->description,
                'order' => $this->order,
                'is_active' => $this->is_active,
                'is_system_generated' => false,
            ]);
            session()->flash('message', 'Shift created successfully!');
        }

        $this->closeShiftForm();
        $this->loadShifts();
    }

    public function confirmDelete($shiftId)
    {
        // Super Admin and hotel managers can delete shifts (if no transactions)
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->canNavigateModules()) {
            session()->flash('error', 'Only Super Admin or hotel managers can delete shifts.');
            return;
        }

        $shift = Shift::find($shiftId);
        
        // Check if shift has transactions
        $hasTransactions = ShiftLog::where('shift_id', $shift->id)->exists();
        
        if ($hasTransactions) {
            session()->flash('error', 'Cannot delete shift that has transactions. Please contact supervisor.');
            return;
        }

        $this->shiftToDelete = $shiftId;
        $this->showDeleteConfirmation = true;
    }

    public function deleteShift()
    {
        if ($this->shiftToDelete) {
            Shift::find($this->shiftToDelete)->delete();
            session()->flash('message', 'Shift deleted successfully!');
            $this->showDeleteConfirmation = false;
            $this->shiftToDelete = null;
            $this->loadShifts();
        }
    }

    public function loadShiftLogs()
    {
        $this->selectedShiftLogs = ShiftLog::with(['shift', 'opener', 'closer'])
            ->orderBy('business_date', 'desc')
            ->orderBy('opened_at', 'desc')
            ->limit(50)
            ->get()
            ->toArray();
        
        $this->showShiftLogs = true;
    }

    public function closeShiftLog($shiftLogId)
    {
        $this->shiftLogToClose = $shiftLogId;
        $this->showCloseConfirmation = true;
    }

    public function confirmCloseShiftLog()
    {
        $this->validate([
            'closing_cash' => 'nullable|numeric|min:0',
            'close_notes' => 'nullable|string',
        ]);

        $shiftLog = ShiftLog::find($this->shiftLogToClose);
        
        if ($shiftLog && !$shiftLog->is_locked) {
            $shiftLog->update([
                'closed_at' => now(),
                'close_type' => 'manual',
                'closing_cash' => $this->closing_cash ?: null,
                'notes' => $this->close_notes,
                'closed_by' => Auth::id(),
                'is_locked' => true,
            ]);

            PosSessionService::closeSessionsForShiftLog($shiftLog->id);

            session()->flash('message', 'Shift closed successfully! All POS sessions for this shift have been closed.');
            $this->showCloseConfirmation = false;
            $this->shiftLogToClose = null;
            $this->closing_cash = '';
            $this->close_notes = '';
            $this->loadShiftLogs();
        }
    }

    public function render()
    {
        return view('livewire.shift-management')->layout('livewire.layouts.app-layout');
    }
}
