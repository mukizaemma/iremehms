<?php

namespace App\Livewire\Pos;

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\OperationalShift;
use App\Models\Order;
use App\Models\PreparationStation;
use App\Models\PosSession;
use App\Services\DayShiftService;
use App\Services\OperationalShiftActionGate;
use App\Services\OperationalShiftService;
use App\Services\PosSessionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PosHome extends Component
{
    public $session = null;
    /** @var \App\Models\BusinessDay|null */
    public $openBusinessDay = null;
    /** @var \App\Models\DayShift|\App\Models\ShiftLog|null */
    public $openShiftOrLog = null;

    /** @var \Illuminate\Support\Collection|array Pending day shifts for current business day when no shift is open */
    public $pendingDayShifts = [];

    /** When operational_shifts are used for POS, business day is not required to sell. */
    public bool $usesOperationalShiftFlow = false;

    /** Open POS/global operational shift for display (null if none). */
    public ?OperationalShift $openOperationalShift = null;

    // Dashboard summary
    public float $todayTotalSales = 0.0;
    public int $todayActiveOrders = 0;
    public int $todayUnpaidOrders = 0;
    public float $todayMySales = 0.0;

    // Detailed report on dashboard
    public string $report_view = 'sales'; // sales, active, unpaid, my_sales
    public ?string $report_from = null;
    public ?string $report_to = null;
    public array $report_rows = [];

    /**
     * Human-readable rollover time for display (e.g. "03:00 AM").
     */
    public function getRolloverTimeFormatted(): string
    {
        $hotel = Hotel::getHotel();
        $rollover = $hotel->business_day_rollover_time;
        if ($rollover) {
            return $rollover->format('g:i A');
        }
        return '03:00 AM';
    }

    public function mount()
    {
        $this->refreshPosContext();
        // Then fetch current user's session (may be null if it was just closed by auto-close)
        $this->session = PosSession::getOpenForUser(Auth::id());
        if ($this->session) {
            $this->session->load(['businessDay', 'dayShift', 'shiftLog.shift', 'operationalShift']);
        }

        $today = now()->toDateString();
        $this->report_from = $today;
        $this->report_to = $today;
        $this->loadSummaryForDate($today);
        $this->loadReport();
    }

    /**
     * Business day + shift context for POS home (legacy day-shift vs operational-shift-only).
     */
    protected function refreshPosContext(): void
    {
        $hotel = Hotel::getHotel();
        $this->usesOperationalShiftFlow = $hotel && OperationalShiftActionGate::requiresOperationalShiftForPos($hotel);

        if ($this->usesOperationalShiftFlow) {
            $this->openOperationalShift = OperationalShiftService::getOpenShiftForPos($hotel);
            $this->openBusinessDay = PosSessionService::getOpenBusinessDayWithoutEnsure();
            $this->openShiftOrLog = null;
            $this->pendingDayShifts = collect([]);
        } else {
            $this->openOperationalShift = null;
            // Legacy: resolve open business day (may ensure one exists).
            $this->openBusinessDay = PosSessionService::getOpenBusinessDay();
            $this->openShiftOrLog = PosSessionService::getOpenDayShift() ?? PosSessionService::getOpenShiftLog();
            if (! $this->openShiftOrLog && $this->openBusinessDay) {
                $this->pendingDayShifts = $this->openBusinessDay->dayShifts()
                    ->where('status', 'PENDING')
                    ->orderBy('start_at')
                    ->get();
            } else {
                $this->pendingDayShifts = collect([]);
            }
        }
    }

    protected function loadSummaryForDate(string $date): void
    {
        $this->todayTotalSales = (float) Invoice::whereDate('created_at', $date)
            ->where('invoice_status', 'PAID')
            ->sum('total_amount');

        $this->todayActiveOrders = Order::whereDate('created_at', $date)
            ->whereIn('order_status', ['OPEN', 'CONFIRMED'])
            ->count();

        $this->todayUnpaidOrders = Order::whereDate('created_at', $date)
            ->whereIn('order_status', ['OPEN', 'CONFIRMED'])
            ->count();

        $userId = Auth::id();
        $this->todayMySales = (float) Invoice::whereDate('created_at', $date)
            ->where('invoice_status', 'PAID')
            ->whereHas('order', fn ($q) => $q->where('waiter_id', $userId))
            ->sum('total_amount');
    }

    public function updatedReportView(): void
    {
        $this->loadReport();
    }

    public function applyReportDates(): void
    {
        $this->loadReport();
    }

    protected function loadReport(): void
    {
        $from = $this->report_from ?: now()->toDateString();
        $to = $this->report_to ?: $from;
        $fromTs = $from . ' 00:00:00';
        $toTs = $to . ' 23:59:59';

        $userId = Auth::id();

        if ($this->report_view === 'sales') {
            $rows = Invoice::with(['order.table', 'order.waiter'])
                ->whereBetween('created_at', [$fromTs, $toTs])
                ->where('invoice_status', 'PAID')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $this->report_rows = $rows->map(function (Invoice $inv) {
                $order = $inv->order;
                return [
                    'date' => $inv->created_at,
                    'order_id' => $inv->order_id,
                    'table' => $order?->table?->table_number,
                    'waiter' => $order?->waiter?->name,
                    'total' => (float) $inv->total_amount,
                    'status' => $inv->invoice_status,
                ];
            })->toArray();
            return;
        }

        $query = Order::with(['table', 'waiter', 'invoice'])
            ->whereBetween('created_at', [$fromTs, $toTs]);

        switch ($this->report_view) {
            case 'active':
                $query->whereIn('order_status', ['OPEN', 'CONFIRMED']);
                break;
            case 'unpaid':
                $query->whereIn('order_status', ['OPEN', 'CONFIRMED']);
                break;
            case 'my_sales':
                $query->where('waiter_id', $userId)
                    ->whereHas('invoice', fn ($q) => $q->where('invoice_status', 'PAID'));
                break;
        }

        $rows = $query->orderByDesc('created_at')->limit(20)->get();

        $this->report_rows = $rows->map(function (Order $order) {
            $invoice = $order->invoice;
            return [
                'date' => $order->created_at,
                'order_id' => $order->id,
                'table' => $order->table?->table_number,
                'waiter' => $order->waiter?->name,
                'total' => $invoice ? (float) ($invoice->total_amount ?? 0) : (float) ($order->total ?? 0),
                'status' => $order->order_status,
            ];
        })->toArray();
    }

    public function openSession()
    {
        try {
            PosSessionService::openSession();
            session()->flash('message', 'POS session opened. You can now take orders.');
            $this->session = PosSession::getOpenForUser(Auth::id());
            if ($this->session) {
                $this->session->load(['businessDay', 'dayShift', 'shiftLog.shift', 'operationalShift']);
            }
            $this->refreshPosContext();
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Open a shift (requires pos_open_shift permission). Used when business day is open but no shift is open.
     * Optionally opens a POS session for the current user so they can start selling immediately.
     */
    public function openShift(int $dayShiftId, bool $andOpenSession = true)
    {
        $user = Auth::user();
        if (!$user || (!$user->hasPermission('pos_open_shift') && !$user->canNavigateModules() && !$user->isSuperAdmin())) {
            session()->flash('error', 'You do not have permission to open a shift.');
            return;
        }
        try {
            DayShiftService::openShift($dayShiftId, $user->id);
            $this->refreshPosContext();

            if ($andOpenSession && $this->openShiftOrLog) {
                PosSessionService::openSession();
                $this->session = PosSession::getOpenForUser($user->id);
                if ($this->session) {
                    $this->session->load(['businessDay', 'dayShift', 'shiftLog.shift', 'operationalShift']);
                }
                session()->flash('message', 'Shift opened and POS session started. You can now take orders.');
            } else {
                session()->flash('message', 'Shift opened. Open your POS session below to start selling.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeSession()
    {
        PosSessionService::closeSession();
        session()->flash('message', 'POS session closed.');
        $this->session = null;
        $this->refreshPosContext();
    }

    public function render()
    {
        if ($this->session) {
            $this->session->loadMissing(['businessDay', 'dayShift', 'shiftLog.shift', 'operationalShift']);
        }

        return view('livewire.pos.pos-home', [
            'rolloverTimeFormatted' => $this->getRolloverTimeFormatted(),
            'activePreparationStations' => PreparationStation::getActiveForPos(),
            'canOpenShift' => Auth::check() && (Auth::user()->hasPermission('pos_open_shift') || Auth::user()->canNavigateModules() || Auth::user()->isSuperAdmin()),
            'pendingDayShiftsNotEmpty' => collect($this->pendingDayShifts)->isNotEmpty(),
            'usesOperationalShiftFlow' => $this->usesOperationalShiftFlow,
            'openOperationalShift' => $this->openOperationalShift,
        ])->layout('livewire.layouts.app-layout');
    }
}
