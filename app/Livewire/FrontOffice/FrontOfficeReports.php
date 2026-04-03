<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\RoomType;
use App\Models\RoomUnit;
use App\Support\PaymentCatalog;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FrontOfficeReports extends Component
{
    use ChecksModuleStatus;

    public string $date_from;
    public string $date_to;
    public string $room_type_id = '';
    public string $room_unit_id = '';
    /** room_type | room */
    public string $group_by = 'room_type';

    /** Optional filters (same reservation set as main report). */
    public string $filter_reservation_type = '';
    public string $filter_rate_plan = '';
    public string $filter_business_source = '';

    public array $summary = [];
    public array $byRoomType = [];
    public array $byRoom = [];
    public array $reservations = [];

    /** Grouped metrics for management (same date / filter scope). */
    public array $byReservationType = [];
    public array $byRatePlan = [];
    public array $byBusinessSource = [];

    /** Top guests in period by revenue; includes lifetime stay count for “recurring” recognition. */
    public array $topGuests = [];

    /** Accommodation payments in the filtered period (single “payment type” dimension). */
    public array $paymentsByType = [];

    /** Debt settlements in scope: who confirmed, method, payment vs sales date. */
    public array $debtSettlements = [];

    public function mount()
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
            || $user->hasPermission('reports_view_all')
            || $user->isReceptionist();
        if (! $allowed) {
            abort(403, 'You do not have permission to view Front Office sales reports.');
        }

        $hotel = Hotel::getHotel();
        $today = $hotel ? Carbon::now($hotel->getTimezone()) : Carbon::now();
        $this->date_from = $today->format('Y-m-d');
        $this->date_to = $today->format('Y-m-d');

        $this->loadReports();
    }

    public function updated($property): void
    {
        // Only reload automatically when grouping changes; other filters use the explicit "Filter" button.
        if ($property === 'group_by') {
            $this->loadReports();
        }
    }

    public function applyFilters(): void
    {
        $this->loadReports();
    }

    public function clearFilters(): void
    {
        $hotel = Hotel::getHotel();
        $today = Carbon::now($hotel->getTimezone());
        $this->date_from = $today->format('Y-m-d');
        $this->date_to = $today->format('Y-m-d');
        $this->room_type_id = '';
        $this->room_unit_id = '';
        $this->filter_reservation_type = '';
        $this->filter_rate_plan = '';
        $this->filter_business_source = '';
        $this->group_by = 'room_type';

        $this->loadReports();
    }

    protected function loadReports(): void
    {
        $hotel = Hotel::getHotel();
        $todayStr = $hotel ? Hotel::getTodayForHotel() : Carbon::now()->format('Y-m-d');
        $from = $this->date_from ?: $todayStr;
        $to = $this->date_to ?: $from;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->date_from = $from;
            $this->date_to = $to;
        }

        // Include any reservation whose stay overlaps the selected period:
        // check_in_date <= to AND check_out_date > from.
        // Exclude cancelled reservations.
        $query = Reservation::with(['roomType', 'roomUnits.room'])
            ->where('hotel_id', $hotel->id)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>', $from);

        $this->applyReservationReportFilters($query);

        $reservations = $query->orderBy('check_in_date')->get();

        $totalRevenue = 0.0;
        $totalNights = 0;
        $resCount = $reservations->count();

        // Snapshot for the "reference day" (start of range) so management can see
        // in-house / arrivals / departures for that day (defaults to today).
        $refDay = $from;
        $inHouseCount = 0;
        $arrivalsCount = 0;
        $departuresCount = 0;
        $confirmedCount = 0;
        $checkedOutCount = 0;
        $noShowCount = 0;

        $byType = [];
        $byRoom = [];
        $byResType = [];
        $byRate = [];
        $byBiz = [];

        foreach ($reservations as $r) {
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            $amount = (float) ($r->total_amount ?? 0);
            $totalRevenue += $amount;
            $totalNights += $nights;

            $cin = $r->check_in_date?->format('Y-m-d');
            $cout = $r->check_out_date?->format('Y-m-d');
            $status = $r->status;

            // Counts by status across all reservations in range
            if ($status === Reservation::STATUS_CONFIRMED) {
                $confirmedCount++;
            } elseif ($status === Reservation::STATUS_CHECKED_OUT) {
                $checkedOutCount++;
            } elseif ($status === Reservation::STATUS_NO_SHOW) {
                $noShowCount++;
            }

            // Snapshot for reference day: in-house, arrivals, departures.
            if ($cin && $cout) {
                // In-house: checked in and refDay is a night between check-in and check-out.
                if ($status === Reservation::STATUS_CHECKED_IN && $cin <= $refDay && $cout > $refDay) {
                    $inHouseCount++;
                }
                // Arrivals: check-in on refDay (confirmed or already checked in).
                if ($cin === $refDay && in_array($status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN], true)) {
                    $arrivalsCount++;
                }
                // Departures: check-out on refDay (either still in-house or just checked out).
                if ($cout === $refDay && in_array($status, [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT], true)) {
                    $departuresCount++;
                }
            }

            $resTypeLabel = $r->reservation_type ? (string) $r->reservation_type : 'Not set';
            if (! isset($byResType[$resTypeLabel])) {
                $byResType[$resTypeLabel] = [
                    'label' => $resTypeLabel,
                    'reservations' => 0,
                    'nights' => 0,
                    'revenue' => 0.0,
                ];
            }
            $byResType[$resTypeLabel]['reservations']++;
            $byResType[$resTypeLabel]['nights'] += $nights;
            $byResType[$resTypeLabel]['revenue'] += $amount;

            $rateLabel = $r->rate_plan ? (string) $r->rate_plan : 'Not set';
            if (! isset($byRate[$rateLabel])) {
                $byRate[$rateLabel] = [
                    'label' => $rateLabel,
                    'reservations' => 0,
                    'nights' => 0,
                    'revenue' => 0.0,
                ];
            }
            $byRate[$rateLabel]['reservations']++;
            $byRate[$rateLabel]['nights'] += $nights;
            $byRate[$rateLabel]['revenue'] += $amount;

            $bizLabel = $r->business_source ? (string) $r->business_source : 'Not set';
            if (! isset($byBiz[$bizLabel])) {
                $byBiz[$bizLabel] = [
                    'label' => $bizLabel,
                    'reservations' => 0,
                    'nights' => 0,
                    'revenue' => 0.0,
                ];
            }
            $byBiz[$bizLabel]['reservations']++;
            $byBiz[$bizLabel]['nights'] += $nights;
            $byBiz[$bizLabel]['revenue'] += $amount;

            $typeName = $r->roomType->name ?? '—';
            if (! isset($byType[$typeName])) {
                $byType[$typeName] = [
                    'room_type' => $typeName,
                    'reservations' => 0,
                    'nights' => 0,
                    'revenue' => 0.0,
                ];
            }
            $byType[$typeName]['reservations']++;
            $byType[$typeName]['nights'] += $nights;
            $byType[$typeName]['revenue'] += $amount;

            $units = $r->roomUnits;
            $unitsCount = max(1, $units->count());
            $sharePerUnit = $amount / $unitsCount;
            $nightsPerUnit = $nights / $unitsCount;

            if ($unitsCount === 0) {
                $key = '—';
                if (! isset($byRoom[$key])) {
                    $byRoom[$key] = [
                        'room_label' => '—',
                        'room_type' => $typeName,
                        'reservations' => 0,
                        'nights' => 0,
                        'revenue' => 0.0,
                    ];
                }
                $byRoom[$key]['reservations']++;
                $byRoom[$key]['nights'] += $nights;
                $byRoom[$key]['revenue'] += $amount;
            } else {
                foreach ($units as $unit) {
                    $label = $unit->label;
                    $roomName = $unit->room->name ?? '';
                    $key = $label . '|' . $roomName;
                    if (! isset($byRoom[$key])) {
                        $byRoom[$key] = [
                            'room_label' => $label,
                            'room_name' => $roomName,
                            'room_type' => $typeName,
                            'reservations' => 0,
                            'nights' => 0,
                            'revenue' => 0.0,
                        ];
                    }
                    $byRoom[$key]['reservations']++;
                    $byRoom[$key]['nights'] += $nightsPerUnit;
                    $byRoom[$key]['revenue'] += $sharePerUnit;
                }
            }
        }

        $avgRatePerNight = $totalNights > 0 ? $totalRevenue / $totalNights : 0.0;

        $this->summary = [
            'reservations' => $resCount,
            'nights' => $totalNights,
            'revenue' => $totalRevenue,
            'avg_rate' => $avgRatePerNight,
            'in_house' => $inHouseCount,
            'arrivals' => $arrivalsCount,
            'departures' => $departuresCount,
            'confirmed' => $confirmedCount,
            'checked_out' => $checkedOutCount,
            'no_show' => $noShowCount,
        ];

        $this->byRoomType = array_values($byType);
        usort($this->byRoomType, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $this->byRoom = array_values($byRoom);
        usort($this->byRoom, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $this->byReservationType = array_values($byResType);
        usort($this->byReservationType, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $this->byRatePlan = array_values($byRate);
        usort($this->byRatePlan, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $this->byBusinessSource = array_values($byBiz);
        usort($this->byBusinessSource, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $lifetimeByFingerprint = $this->buildLifetimeFingerprintCounts((int) $hotel->id);
        $this->topGuests = $this->buildTopGuestsForManagement($reservations, $lifetimeByFingerprint);

        $payQuery = ReservationPayment::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereRaw(
                'COALESCE(DATE(revenue_attribution_date), DATE(received_at)) BETWEEN ? AND ?',
                [$from, $to]
            );

        if ($this->hasAnyReservationFiltersForPayments()) {
            $payQuery->whereHas('reservation', function ($q) {
                $this->applyReservationReportFilters($q);
            });
        }

        $buckets = [];
        foreach ($payQuery->get() as $p) {
            $key = PaymentCatalog::accommodationPaymentReportBucket($p->payment_method ?? '', $p->payment_status ?? '');
            $amt = (float) ($p->amount ?? 0);
            $buckets[$key] = ($buckets[$key] ?? 0) + $amt;
        }
        $labels = PaymentCatalog::accommodationReportBucketLabels();
        $this->paymentsByType = collect($buckets)
            ->map(fn (float $total, string $key) => ['label' => $labels[$key] ?? $key, 'total' => $total])
            ->sortByDesc('total')
            ->values()
            ->all();

        $debtQuery = ReservationPayment::query()
            ->with(['reservation', 'receivedBy'])
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->where('is_debt_settlement', true)
            ->whereRaw(
                'COALESCE(DATE(revenue_attribution_date), DATE(received_at)) BETWEEN ? AND ?',
                [$from, $to]
            );

        if ($this->hasAnyReservationFiltersForPayments()) {
            $debtQuery->whereHas('reservation', function ($q) {
                $this->applyReservationReportFilters($q);
            });
        }

        $this->debtSettlements = $debtQuery->orderByDesc('received_at')->get()->map(function (ReservationPayment $p) use ($hotel) {
            $r = $p->reservation;
            $currency = $p->currency ?? $hotel->currency ?? 'RWF';

            return [
                'receipt' => $p->receipt_number ?? ('#'.$p->id),
                'guest' => $r->guest_name ?? '—',
                'reservation' => $r->reservation_number ?? '—',
                'amount' => (float) ($p->amount ?? 0),
                'currency' => $currency,
                'payment_method' => PaymentCatalog::formatPaymentLineForReport($p->payment_method ?? '', $p->payment_status ?? ''),
                'received_at' => $p->received_at ? $p->received_at->format('Y-m-d H:i') : '',
                'confirmed_by' => $p->receivedBy?->name ?? '—',
                'sales_date' => $p->revenue_attribution_date
                    ? $p->revenue_attribution_date->format('Y-m-d')
                    : ($p->received_at ? $p->received_at->format('Y-m-d') : ''),
            ];
        })->values()->all();

        $this->reservations = $reservations->map(function (Reservation $r) {
            return [
                'number' => $r->reservation_number,
                'guest' => $r->guest_name,
                'room_type' => $r->roomType->name ?? '—',
                'reservation_type' => $r->reservation_type ?: '—',
                'rate_plan' => $r->rate_plan ?: '—',
                'business_source' => $r->business_source ?: '—',
                'check_in' => $r->check_in_date?->format('Y-m-d'),
                'check_out' => $r->check_out_date?->format('Y-m-d'),
                'nights' => max(0, $r->check_in_date->diffInDays($r->check_out_date)),
                'amount' => (float) ($r->total_amount ?? 0),
                'status' => $r->status,
            ];
        })->toArray();
    }

    protected function applyReservationReportFilters(\Illuminate\Database\Eloquent\Builder $query): void
    {
        if ($this->room_type_id !== '') {
            $query->where('room_type_id', $this->room_type_id);
        }
        if ($this->room_unit_id !== '') {
            $query->whereHas('roomUnits', function ($q) {
                $q->where('room_units.id', $this->room_unit_id);
            });
        }
        if ($this->filter_reservation_type !== '') {
            $query->where('reservation_type', $this->filter_reservation_type);
        }
        if ($this->filter_rate_plan !== '') {
            $query->where('rate_plan', $this->filter_rate_plan);
        }
        if ($this->filter_business_source !== '') {
            $query->where('business_source', $this->filter_business_source);
        }
    }

    protected function hasAnyReservationFiltersForPayments(): bool
    {
        return $this->room_type_id !== ''
            || $this->room_unit_id !== ''
            || $this->filter_reservation_type !== ''
            || $this->filter_rate_plan !== ''
            || $this->filter_business_source !== '';
    }

    /**
     * Stable guest key: email (preferred), else normalized phone, else lowercased name.
     *
     * @return array<string, int>
     */
    protected function buildLifetimeFingerprintCounts(int $hotelId): array
    {
        $counts = [];
        Reservation::query()
            ->where('hotel_id', $hotelId)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->select(['id', 'guest_email', 'guest_phone', 'guest_name'])
            ->orderBy('id')
            ->chunkById(2000, function ($chunk) use (&$counts) {
                foreach ($chunk as $r) {
                    $fp = $this->guestFingerprint($r);
                    $counts[$fp] = ($counts[$fp] ?? 0) + 1;
                }
            });

        return $counts;
    }

    protected function guestFingerprint(Reservation $r): string
    {
        return $this->guestFingerprintFromRaw($r->guest_email, $r->guest_phone, $r->guest_name);
    }

    protected function guestFingerprintFromRaw(?string $email, ?string $phone, ?string $name): string
    {
        $email = strtolower(trim((string) $email));
        if ($email !== '') {
            return 'email:' . $email;
        }
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits !== '') {
            return 'phone:' . $digits;
        }

        return 'name:' . mb_strtolower(trim((string) $name));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Reservation>  $reservations
     * @param  array<string, int>  $lifetimeByFingerprint
     * @return array<int, array<string, mixed>>
     */
    protected function buildTopGuestsForManagement($reservations, array $lifetimeByFingerprint): array
    {
        $agg = [];
        foreach ($reservations as $r) {
            $fp = $this->guestFingerprint($r);
            if (! isset($agg[$fp])) {
                $agg[$fp] = [
                    'guest' => $r->guest_name,
                    'contact' => $r->guest_email ?: $r->guest_phone ?: '—',
                    'stays_in_period' => 0,
                    'nights' => 0,
                    'revenue' => 0.0,
                ];
            }
            $agg[$fp]['stays_in_period']++;
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            $agg[$fp]['nights'] += $nights;
            $agg[$fp]['revenue'] += (float) ($r->total_amount ?? 0);
        }

        $rows = [];
        foreach ($agg as $fp => $row) {
            $lifetime = $lifetimeByFingerprint[$fp] ?? 0;
            $rows[] = array_merge($row, [
                'lifetime_stays' => $lifetime,
                'is_recurring' => $lifetime >= 2,
            ]);
        }

        return collect($rows)
            ->sortByDesc('revenue')
            ->values()
            ->take(40)
            ->all();
    }

    public function getRoomTypesProperty()
    {
        $hotel = Hotel::getHotel();
        return RoomType::where('hotel_id', $hotel->id)->where('is_active', true)->orderBy('name')->get();
    }

    public function getRoomsProperty()
    {
        $hotel = Hotel::getHotel();
        return RoomUnit::with('room')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotel->id)->where('is_active', true))
            ->orderBy('label')
            ->get();
    }

    public function render()
    {
        $businessSources = array_values(array_unique(array_merge(
            AddReservation::BUSINESS_SOURCE_OPTIONS,
            ['Group']
        )));
        sort($businessSources);

        return view('livewire.front-office.front-office-reports', [
            'roomTypes' => $this->roomTypes,
            'rooms' => $this->rooms,
            'reservationTypeOptions' => AddReservation::RESERVATION_TYPES,
            'ratePlanOptions' => AddReservation::RATE_TYPES,
            'businessSourceOptions' => $businessSources,
        ])->layout('livewire.layouts.app-layout');
    }
}
