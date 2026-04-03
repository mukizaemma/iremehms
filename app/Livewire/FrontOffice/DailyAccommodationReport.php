<?php

namespace App\Livewire\FrontOffice;

use App\Helpers\VatHelper;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Support\PaymentCatalog;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DailyAccommodationReport extends Component
{
    use ChecksModuleStatus;

    public string $date_from = '';

    public string $date_to = '';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public float $total_gross = 0.0;

    public float $total_tax = 0.0;

    public float $total_paid_today = 0.0;

    public float $total_credit_today = 0.0;

    /** Sum of current folio balance once per reservation (not multiplied by nights in range). */
    public float $total_balance_due = 0.0;

    /** Sum of all confirmed reservation payments in the report period (matches payment-type breakdown; staff-filtered). */
    public float $summary_paid_period = 0.0;

    public string $currency = '';

    public bool $reports_show_vat = false;

    /** @var array<string, float> */
    public array $payments_by_type = [];

    public bool $canPickStaff = false;

    /** self|all|user */
    public string $staffScope = 'self';

    public ?int $staffId = null;

    /** @var array<int, string> */
    public array $staffOptions = [];

    public ?string $staffScopeLabel = null;

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');

        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        $user = Auth::user();
        $allowed = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('fo_collect_payment')
            || $user->isReceptionist();

        if (! $allowed) {
            abort(403, 'You do not have permission to view the daily accommodation report.');
        }

        $hotel = Hotel::getHotel();
        $today = $hotel ? Hotel::getTodayForHotel() : now()->toDateString();
        $this->date_from = $today;
        $this->date_to = $today;
        $this->currency = $hotel?->currency ?? 'RWF';

        $this->canPickStaff = (bool) ($user->isSuperAdmin()
            || $user->isManager()
            || $user->isEffectiveGeneralManager()
            || $user->isReceptionist());

        if (! $this->canPickStaff) {
            $this->staffScope = 'self';
            $this->staffId = $user->id;
        } else {
            // Receptionists need hotel-wide visibility by default (assist guests handled by others).
            if ($user->isReceptionist() && ! $user->isSuperAdmin() && ! $user->isManager() && ! $user->isEffectiveGeneralManager()) {
                $this->staffScope = 'all';
                $this->staffId = null;
            } else {
                $this->staffScope = 'self';
                $this->staffId = $user->id;
            }
            if ($hotel) {
                // Front-office / finance roles only (not waiters, store keepers, etc.)
                $this->staffOptions = User::activeInHotelWithRoleSlugs($hotel->id, [
                    'receptionist',
                    'manager',
                    'cashier',
                    'hotel-admin',
                    'director',
                    'general-manager',
                    'accountant',
                ])
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->pluck('name', 'id')
                    ->toArray();
            }
        }

        $this->loadReport();
    }

    /**
     * Run after the user sets dates / staff scope and clicks "Apply filters".
     * (Inputs use deferred wire:model so the report is not a hidden auto-refresh.)
     */
    public function applyReportFilters(): void
    {
        if ($this->canPickStaff) {
            if ($this->staffScope === 'self') {
                $this->staffId = (int) Auth::id();
            } elseif ($this->staffScope === 'all') {
                $this->staffId = null;
            } elseif ($this->staffScope === 'user') {
                $this->staffId = $this->staffId ?? (int) Auth::id();
                if ($this->staffId <= 0) {
                    $this->staffId = (int) Auth::id();
                }
            }
        } else {
            $this->staffScope = 'self';
            $this->staffId = (int) Auth::id();
        }

        $this->normalizeDateRange();
        $this->loadReport();
    }

    /** Keep staff pickers in sync when scope changes (UI only; run Apply filters to reload data). */
    public function updatedStaffScope(): void
    {
        if (! $this->canPickStaff) {
            $this->staffScope = 'self';
            $this->staffId = (int) Auth::id();

            return;
        }

        if ($this->staffScope === 'self') {
            $this->staffId = (int) Auth::id();
        } elseif ($this->staffScope === 'all') {
            $this->staffId = null;
        } elseif ($this->staffScope === 'user') {
            $this->staffId = $this->staffId ?? (int) Auth::id();
            if ($this->staffId <= 0) {
                $this->staffId = (int) Auth::id();
            }
        }
    }

    protected function normalizeDateRange(): void
    {
        $from = $this->date_from ?: Hotel::getTodayForHotel();
        $to = $this->date_to ?: $from;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->date_from = $from;
            $this->date_to = $to;
        }
    }

    protected function paymentModeLabel(ReservationPayment $p): string
    {
        return PaymentCatalog::formatPaymentLineForReport($p->payment_method ?? $p->payment_type, $p->payment_status);
    }

    protected function resolveReceiverFilter(): array
    {
        $receiverPaymentScopeLabel = 'Self';
        $receiverUserIdFilter = (int) Auth::id();

        if ($this->canPickStaff && $this->staffScope === 'all') {
            $receiverPaymentScopeLabel = 'All staff';
            $receiverUserIdFilter = 0;
        } elseif ($this->canPickStaff && $this->staffScope === 'user') {
            $receiverPaymentScopeLabel = 'Specific user';
            $receiverUserIdFilter = (int) ($this->staffId ?? 0);
            if ($receiverUserIdFilter <= 0) {
                $receiverUserIdFilter = (int) Auth::id();
            }
        }

        return [$receiverPaymentScopeLabel, $receiverUserIdFilter];
    }

    protected function loadPaymentAggregatesForPeriod(
        Hotel $hotel,
        Carbon $from,
        Carbon $to,
        int $receiverUserIdFilter
    ): void {
        $buckets = array_fill_keys(PaymentCatalog::accommodationReportBucketKeys(), 0.0);

        $q = ReservationPayment::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereRaw(
                'COALESCE(DATE(revenue_attribution_date), DATE(received_at)) BETWEEN ? AND ?',
                [$from->format('Y-m-d'), $to->format('Y-m-d')]
            );

        if ($receiverUserIdFilter > 0) {
            $q->where('received_by', $receiverUserIdFilter);
        }

        foreach ($q->get() as $p) {
            $key = PaymentCatalog::accommodationPaymentReportBucket($p->payment_method ?? '', $p->payment_status ?? '');
            $amt = (float) ($p->amount ?? 0);
            if (! array_key_exists($key, $buckets)) {
                $buckets[$key] = 0.0;
            }
            $buckets[$key] += $amt;
        }

        $this->payments_by_type = $buckets;
    }

    protected function loadReport(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $this->rows = [];
            $this->currency = '';
            $this->reports_show_vat = false;
            $this->resetTotalsAndAggregates();

            return;
        }

        $this->normalizeDateRange();
        $from = Carbon::parse($this->date_from)->startOfDay();
        $to = Carbon::parse($this->date_to)->startOfDay();

        $this->currency = $hotel->currency ?? 'RWF';
        $this->reports_show_vat = $hotel->showsVatOnReports();

        [$receiverPaymentScopeLabel, $receiverUserIdFilter] = $this->resolveReceiverFilter();
        $this->staffScopeLabel = $receiverPaymentScopeLabel;

        $rows = [];
        $totalGross = 0.0;
        $totalTax = 0.0;
        $totalPaidRow = 0.0;
        $totalCreditRow = 0.0;
        $balanceOncePerReservation = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dateStr = $day->format('Y-m-d');

            $reservations = Reservation::with(['roomUnits.room'])
                ->where('hotel_id', $hotel->id)
                ->where('status', '!=', Reservation::STATUS_CANCELLED)
                ->where('status', '!=', Reservation::STATUS_NO_SHOW)
                ->where('check_in_date', '<=', $dateStr)
                ->where('check_out_date', '>', $dateStr)
                ->orderBy('guest_name')
                ->get();

            $reservationIds = $reservations->pluck('id')->all();

            $paymentsByReservation = [];
            if (! empty($reservationIds)) {
                $payments = ReservationPayment::where('hotel_id', $hotel->id)
                    ->whereIn('reservation_id', $reservationIds)
                    ->where('status', 'confirmed')
                    ->where(function ($q) use ($dateStr) {
                        $q->whereDate('received_at', $dateStr)
                            ->orWhereDate('revenue_attribution_date', $dateStr);
                    })
                    ->when($receiverUserIdFilter > 0, fn ($q) => $q->where('received_by', $receiverUserIdFilter))
                    ->orderBy('received_at')
                    ->get();

                foreach ($payments as $p) {
                    $rid = (int) $p->reservation_id;
                    $paymentsByReservation[$rid][] = $p;
                }
            }

            foreach ($reservations as $r) {
                $roomUnits = $r->roomUnits ?? collect();
                $roomNumbers = $roomUnits->pluck('label')->filter()->unique()->values()->join(', ');
                $roomsCount = max(1, (int) $roomUnits->count());

                $nights = max(1, (int) $r->check_in_date->diffInDays($r->check_out_date));

                $dailyGross = (float) ($r->total_amount ?? 0) / $nights;
                $dailyTax = VatHelper::vatFromInclusive((float) $dailyGross);
                $dailyRoomRatePerUnit = $dailyGross / $roomsCount;

                $todayPayments = $paymentsByReservation[(int) $r->id] ?? [];

                $paidToday = 0.0;
                $creditToday = 0.0;
                $modeAmounts = [];

                foreach ($todayPayments as $p) {
                    $modeLabel = $this->paymentModeLabel($p);
                    $amount = (float) ($p->amount ?? 0);
                    $st = PaymentCatalog::normalizeStatus($p->payment_status ?? PaymentCatalog::STATUS_PAID);

                    if (! isset($modeAmounts[$modeLabel])) {
                        $modeAmounts[$modeLabel] = 0.0;
                    }
                    $modeAmounts[$modeLabel] += $amount;

                    if ($st === PaymentCatalog::STATUS_DEBITS || $st === PaymentCatalog::STATUS_OFFER) {
                        $creditToday += $amount;
                    } else {
                        $paidToday += $amount;
                    }
                }

                $balanceDue = max(0.0, (float) ($r->total_amount ?? 0) - (float) ($r->paid_amount ?? 0));
                $balanceOncePerReservation[(int) $r->id] = $balanceDue;

                $modeString = '';
                if (! empty($modeAmounts)) {
                    $parts = [];
                    foreach ($modeAmounts as $mode => $amt) {
                        $parts[] = $mode . ' ' . number_format((float) $amt, 2, '.', '');
                    }
                    $modeString = implode(', ', $parts);
                }

                $rows[] = [
                    'date' => $dateStr,
                    'reservation_id' => (int) $r->id,
                    'guest_name' => $r->guest_name ?? '—',
                    'guest_address' => $r->guest_address ?? '—',
                    'guest_id_number' => $r->guest_id_number ?? '—',
                    'guest_phone' => $r->guest_phone ?? '—',
                    'room_number' => $roomNumbers ?: '—',
                    'nights' => $nights,
                    'room_rate' => number_format((float) $dailyRoomRatePerUnit, 2, '.', ''),
                    'currency' => $this->currency,
                    'payment_mode' => $modeString ?: '—',
                    'paid_today' => number_format($paidToday, 2, '.', ''),
                    'credit_today' => number_format($creditToday, 2, '.', ''),
                    'balance_due' => number_format($balanceDue, 2, '.', ''),
                    'tax_for_row' => number_format((float) $dailyTax, 2, '.', ''),
                ];

                $totalGross += (float) $dailyGross;
                $totalTax += (float) $dailyTax;
                $totalPaidRow += (float) $paidToday;
                $totalCreditRow += (float) $creditToday;
            }
        }

        $this->rows = $rows;
        $this->total_gross = $totalGross;
        $this->total_tax = $totalTax;
        $this->total_paid_today = $totalPaidRow;
        $this->total_credit_today = $totalCreditRow;
        $this->total_balance_due = (float) array_sum($balanceOncePerReservation);

        $this->loadPaymentAggregatesForPeriod($hotel, $from, $to, $receiverUserIdFilter);
        $this->summary_paid_period = (float) array_sum($this->payments_by_type);
    }

    protected function resetTotalsAndAggregates(): void
    {
        $this->total_gross = 0.0;
        $this->total_tax = 0.0;
        $this->total_paid_today = 0.0;
        $this->total_credit_today = 0.0;
        $this->total_balance_due = 0.0;
        $this->summary_paid_period = 0.0;
        $this->payments_by_type = array_fill_keys(PaymentCatalog::accommodationReportBucketKeys(), 0.0);
    }

    public function render()
    {
        return view('livewire.front-office.daily-accommodation-report')
            ->layout('livewire.layouts.app-layout');
    }
}
