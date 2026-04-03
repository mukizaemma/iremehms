<?php

namespace App\Livewire\FrontOffice;

use App\Models\ActivityLog;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomUnit;
use App\Models\AdditionalCharge;
use App\Models\Invoice;
use App\Models\SupportRequest;
use App\Services\ActivityLogger;
use App\Services\OperationalShiftActionGate;
use App\Support\ActivityLogModule;
use App\Support\PaymentCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Front Office Admin – occupancy calendar (eZee-style).
 * Room types, beds, 15-day grid with bookings; status filters and summary.
 * Dynamic: loads rooms/room types from DB and reservations for CRUD.
 */
class FrontOfficeAdmin extends Component
{
    /** @var string Start date for room view (Y-m-d) */
    public $view_start_date = '';

    /** @var int Room view duration: 7, 15, or 30 days */
    public $room_view_days = 15;

    /** @var string all|vacant|occupied|reserved|blocked|due_out|dirty|no_show|due_in|recent_bookings */
    public $statusFilter = 'all';

    /** @var string Room type filter (empty = all) */
    public $roomTypeFilter = '';

    /** @var array N dates as Y-m-d (from view_start_date for room_view_days) */
    public $dates = [];

    /** @var array Room type slug => [ name, beds[] ] */
    public $roomTypes = [];

    /** @var array [ [ bed_key, guest_name, from, to, paid ] ] */
    public $bookings = [];

    /** @var array Status tab counts */
    public $counts = [
        'all' => 0,
        'vacant' => 0,
        'occupied' => 0,
        'reserved' => 0,
        'blocked' => 0,
        'due_out' => 0,
        'due_in' => 0,
        'dirty' => 0,
        'no_show' => 0,
        'recent_bookings' => 0,
    ];

    /** @var array Per-date capacity (for header) */
    public $capacityByDate = [];

    /** @var array Per-date rate/revenue (for header) */
    public $rateByDate = [];

    /** @var array Per-date occupancy % (for footer) */
    public $occupancyByDate = [];

    /** @var array Per-date [ 'booked' => int, 'total' => int ] for footer tooltip (e.g. "22% (2/9)") */
    public $occupancyDetailByDate = [];

    /** @var string 'grid' | 'cards' */
    public $viewMode = 'grid';

    /** @var array Set of bed_key that have a booking in current date range (for status filter) */
    public $unitIdsInRange = [];
    /** @var array Set of bed_key that have a stay covering today (check_in <= today <= check_out) */
    public $unitIdsOccupiedToday = [];
    /** @var array Set of bed_key that have a booking due out today (checkout date = today) */
    public $unitIdsDueOut = [];
    /** @var array Set of bed_key that have a no_show reservation in the date range */
    public $unitIdsNoShow = [];
    /** @var array Set of bed_key that have a reservation arriving today (check_in date = today) */
    public $unitIdsDueIn = [];
    /** @var array Set of bed_key that have a reservation created in the last 7 days (overlapping view) */
    public $unitIdsRecentBookings = [];

    /** @var array|null Selected reservation for sidebar (full detail) */
    public $selectedBooking = null;

    /**
     * Summary for group reservations in sidebar (expected vs registered guests).
     * Computed on the fly via getGroupSummaryForSelectedBooking().
     */
    public function getGroupSummaryForSelectedBooking(): ?array
    {
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return null;
        }
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)
            ->with('preRegistrations')
            ->find((int) $this->selectedBooking['reservation_id']);
        if (! $reservation) {
            return null;
        }
        if (! $reservation->group_name && ! $reservation->expected_guest_count) {
            return null; // not a group reservation
        }

        $expected = (int) ($reservation->expected_guest_count ?? ($reservation->adult_count + $reservation->child_count));
        $totalPreRegs = $reservation->preRegistrations->count();
        $assigned = $reservation->preRegistrations->whereNotNull('room_unit_id')->count();
        $checkedIn = $reservation->preRegistrations->where('status', \App\Models\PreRegistration::STATUS_CHECKED_IN)->count();
        $remaining = max(0, $expected - $totalPreRegs);

        return [
            'group_name' => $reservation->group_name ?: $reservation->guest_name,
            'expected' => $expected,
            'registered' => $totalPreRegs,
            'assigned' => $assigned,
            'checked_in' => $checkedIn,
            'remaining' => $remaining,
            'reservation_id' => $reservation->id,
        ];
    }

    /** @var string|null Selected room unit ID for "room detail" panel (upcoming guests, add reservation) */
    public $selectedRoomUnitId = null;
    /** @var string Label of selected room (e.g. "403" or "Double Room 1") */
    public $selectedRoomLabel = '';

    /** @var array|null Reservation being edited (Folio Operations view) */
    public $editingReservation = null;

    /** @var string Active tab on Edit Stay: folio_operations|booking_details|guest_details|room_charges|credit_card|audit_trail */
    public $editStayTab = 'folio_operations';

    /** @var array<int, array{at: string, user: string, action: string, description: string, ip: string}> */
    public array $auditTrailRows = [];

    /** Add Payment modal (also used for Edit Payment) */
    public $showAddPaymentModal = false;
    public $editingPaymentId = null;
    public $payment_date = '';
    public $payment_folio_display = '';
    public $payment_rec_vou_no = 'New';
    /** Single dropdown: Cash, MoMo, POS/Card, Bank, Pending, Debits, Offer */
    public $payment_unified = 'Cash';
    public bool $payment_cash_submit_later = false;
    public $payment_client_reference = '';
    public $payment_amount = '';
    public $payment_currency = 'INR';
    public $payment_comment = '';

    public bool $payment_is_debt_settlement = false;

    /** Y-m-d — rooms sales date when settling outstanding debt */
    public string $payment_revenue_attribution_date = '';

    /** Void modal */
    public $showVoidModal = false;
    public $voidPaymentId = null;
    public $voidReason = '';
    public const VOID_REASON_SUGGESTIONS = ['Booking Cancelled', 'Payment Cancelled', 'wrong entry', 'Amount Refund'];

    /** In-memory payment entries for current folio (so we can edit/void; keyed by id) */
    public $folioPayments = [];

    /** In-memory extra charge entries (Add Charge form) */
    public $folioCharges = [];

    /** @var array|null Last confirmed reservation payment id (for receipts). */
    public $lastRecordedPaymentId = null;

    /** Summary used in Add Payment modal (folio totals + linked POS invoices). */
    public array $paymentCheckoutSummary = [];

    /** Add Charge modal */
    public $showAddChargeModal = false;
    public $charge_date = '';
    public $charge_folio_display = '';
    public $charge_rec_vou_no = 'New';
    /** When to apply the charge: check_in_and_check_out|everyday|everyday_except_check_in|everyday_except_check_in_and_check_out|everyday_except_check_out|on_custom_date|only_on_check_in */
    public $charge_apply_when = 'check_in_and_check_out';
    /** Charge type (what): Late Check-out, Extra bed, etc. */
    public $charge_type = 'Late Check-out';
    /** Selected extra charge from AdditionalCharge (id); drives default amount and name */
    public $charge_additional_charge_id = null;
    /** Charge rule (how applied): Per Adult, Per Booking, etc. */
    public $charge_rule = '';
    public $charge_tax_inclusive = true;
    public $charge_add_as_inclusion = false;
    public $charge_qty = 1;
    public $charge_amount = '';
    public $charge_comment = '';

    public const CHARGE_TYPE_OPTIONS = ['Late Check-out', 'Extra bed', 'Mini Bar', 'Laundry', 'Room Service', 'Telephone', 'Other'];

    /** When user selects an extra charge, pre-fill amount (and optional rule/tax) but amount remains editable. */
    public function updatedChargeAdditionalChargeId($value): void
    {
        if ($value && is_numeric($value)) {
            $charge = AdditionalCharge::find((int) $value);
            if ($charge) {
                $this->charge_amount = $charge->default_amount !== null ? (string) $charge->default_amount : '';
                $this->charge_rule = $charge->charge_rule ?? $this->charge_rule;
                $this->charge_tax_inclusive = $charge->is_tax_inclusive ?? true;
                $this->charge_type = $charge->name;
            }
        }
    }

    /** Charge rule options (how the charge is applied) */
    public const CHARGE_RULE_OPTIONS = ['Per Adult', 'Per Booking', 'Per Child', 'Per Instance', 'Per Person', 'Per Quantity'];

    /** When to charge the additional charge (display labels) */
    public const CHARGE_APPLY_WHEN_OPTIONS = [
        'check_in_and_check_out' => 'Check in and check out',
        'everyday' => 'Everyday',
        'everyday_except_check_in' => 'Everyday except check in',
        'everyday_except_check_in_and_check_out' => 'Everyday except check in and check out',
        'everyday_except_check_out' => 'Everyday except check out',
        'on_custom_date' => 'On Custom Date',
        'only_on_check_in' => 'Only on check in',
    ];

    public function mount()
    {
        $this->view_start_date = Hotel::getTodayForHotel();
        $this->room_view_days = 15;
        $this->loadRoomTypesFromDb();
        $this->buildDates();
        $this->loadBookingsFromDb();
        $this->computeGrid();
        $this->handleReservationActionFromQuery();
    }

    /**
     * Open Add Payment / Add Charges modals directly when coming from
     * Reservations list buttons.
     *
     * Query params:
     * - reservation: reservation_number or reservation id
     * - action: payment|charges|checkout
     */
    protected function handleReservationActionFromQuery(): void
    {
        $reservationParam = request()->query('reservation');
        $action = (string) request()->query('action', '');
        if ($reservationParam === null || $reservationParam === '' || $action === '') {
            return;
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $reservation = null;
        if (is_numeric((string) $reservationParam)) {
            $reservation = Reservation::where('hotel_id', $hotel->id)->where('id', (int) $reservationParam)->first();
        } else {
            $reservation = Reservation::where('hotel_id', $hotel->id)->where('reservation_number', (string) $reservationParam)->first();
        }

        if (! $reservation) {
            return;
        }

        $this->openEditStayForReservation((int) $reservation->id);

        if ($action === 'payment') {
            $this->openAddPaymentModal();
        } elseif ($action === 'charges') {
            $this->openAddChargeModal();
        } elseif ($action === 'checkout') {
            $this->openCheckoutModalForReservation((int) $reservation->id);
        }
    }

    protected function loadRoomTypesFromDb(): void
    {
        $hotel = Hotel::getHotel();
        $roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->with(['rooms' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();

        $this->roomTypes = [];
        foreach ($roomTypes as $rt) {
            $beds = [];
            foreach ($rt->rooms as $room) {
                $units = RoomUnit::where('room_id', $room->id)->where('is_active', true)->orderBy('sort_order')->get();
                foreach ($units as $u) {
                    $beds[] = [
                        'id' => (string) $u->id,
                        'label' => $u->label,
                        'dirty' => false,
                    ];
                }
            }
            if (count($beds) > 0) {
                $slug = $rt->slug ?: \Illuminate\Support\Str::slug($rt->name);
                $this->roomTypes[$slug] = [
                    'name' => $rt->name,
                    'beds' => $beds,
                ];
            }
        }
    }

    protected function loadBookingsFromDb(): void
    {
        if (count($this->dates) === 0) {
            $this->bookings = [];
            return;
        }
        $start = $this->dates[0];
        $end = $this->dates[count($this->dates) - 1];
        $hotel = Hotel::getHotel();
        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>=', $start)
            ->where('check_in_date', '<=', $end)
            ->with(['roomUnits.room', 'roomType'])
            ->get();

        $this->bookings = [];
        foreach ($reservations as $r) {
            $from = $r->check_in_date->format('Y-m-d');
            $to = $r->check_out_date->format('Y-m-d');
            $arrivalDt = $r->check_in_date->format('d/m/Y') . ($r->check_in_time ? ' ' . Carbon::parse($r->check_in_time)->format('h:i A') : ' 12:00 PM');
            $departureDt = $r->check_out_date->format('d/m/Y') . ($r->check_out_time ? ' ' . Carbon::parse($r->check_out_time)->format('h:i A') : ' 11:00 AM');
            $nights = $r->check_in_date->diffInDays($r->check_out_date);
            $rate = $nights > 0 && $r->total_amount ? (float) $r->total_amount / $nights : 0;
            $base = [
                'reservation_id' => $r->id,
                'from' => $from,
                'to' => $to,
                'is_paid' => $r->isPaid(),
                'reservation_number' => $r->reservation_number,
                'status' => ucfirst(str_replace('_', ' ', $r->status)),
                'status_raw' => $r->status,
                'status_badge' => match ($r->status) {
                    Reservation::STATUS_CANCELLED => 'secondary',
                    Reservation::STATUS_NO_SHOW => 'danger',
                    Reservation::STATUS_CHECKED_IN => 'primary',
                    Reservation::STATUS_CHECKED_OUT => 'secondary',
                    default => 'success',
                },
                'country' => $r->guest_country ?? '',
                'phone' => $r->guest_phone ?? '',
                'email' => $r->guest_email ?? '',
                'arrival_datetime' => $arrivalDt,
                'departure_datetime' => $departureDt,
                'booking_datetime' => $r->created_at->format('d/m/Y h:i A'),
                'room_type' => $r->roomType->name ?? '—',
                'rate_plan' => $r->rate_plan ?? '—',
                'pax_adults' => $r->adult_count,
                'pax_infants' => $r->child_count,
                'avg_daily_rate' => number_format($rate, 2, '.', ''),
                'currency' => $r->currency ?? 'RWF',
                'total' => $r->total_amount ? number_format((float) $r->total_amount, 2, '.', '') : '0.00',
                'paid' => number_format((float) $r->paid_amount, 2, '.', ''),
                'balance' => number_format(max(0, (float) ($r->total_amount ?? 0) - (float) $r->paid_amount), 2, '.', ''),
                'guest_name' => $r->guest_name,
                'created_at' => $r->created_at?->format('Y-m-d H:i:s'),
            ];
            foreach ($r->roomUnits as $unit) {
                $this->bookings[] = array_merge($base, [
                    'bed_key' => (string) $unit->id,
                    'room_number' => $unit->label,
                ]);
            }
            if ($r->roomUnits->isEmpty()) {
                $this->bookings[] = array_merge($base, [
                    'bed_key' => 'r' . $r->id,
                    'room_number' => '—',
                ]);
            }
        }
    }

    protected function buildDates(): void
    {
        $days = in_array((int) $this->room_view_days, [7, 15, 30], true) ? (int) $this->room_view_days : 15;
        $start = $this->view_start_date ? Carbon::parse($this->view_start_date) : Carbon::parse(Hotel::getTodayForHotel());
        $this->dates = [];
        for ($i = 0; $i < $days; $i++) {
            $this->dates[] = $start->copy()->addDays($i)->format('Y-m-d');
        }
    }

    public function previousPeriod(): void
    {
        $days = in_array((int) $this->room_view_days, [7, 15, 30], true) ? (int) $this->room_view_days : 15;
        $this->view_start_date = Carbon::parse($this->view_start_date)->subDays($days)->format('Y-m-d');
        $this->buildDates();
        $this->loadBookingsFromDb();
        $this->computeGrid();
    }

    public function nextPeriod(): void
    {
        $days = in_array((int) $this->room_view_days, [7, 15, 30], true) ? (int) $this->room_view_days : 15;
        $this->view_start_date = Carbon::parse($this->view_start_date)->addDays($days)->format('Y-m-d');
        $this->buildDates();
        $this->loadBookingsFromDb();
        $this->computeGrid();
    }

    public function setViewDays(int $days): void
    {
        if (in_array($days, [7, 15, 30], true)) {
            $this->room_view_days = $days;
            $this->buildDates();
            $this->loadBookingsFromDb();
            $this->computeGrid();
        }
    }

    public function updatedViewStartDate(): void
    {
        $this->buildDates();
        $this->loadBookingsFromDb();
        $this->computeGrid();
    }

    public function updatedRoomViewDays(): void
    {
        if (! in_array((int) $this->room_view_days, [7, 15, 30], true)) {
            $this->room_view_days = 15;
        }
        $this->buildDates();
        $this->loadBookingsFromDb();
        $this->computeGrid();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    /** Ensure the view reloads when status or room type filter changes so tabs show the right list. */
    public function updatedStatusFilter(): void
    {
        // Re-render will use new statusFilter in bedMatchesStatusFilter / getRoomsForCurrentFilter
    }

    public function updatedRoomTypeFilter(): void
    {
        // Re-render will filter by room type in the view
    }

    /** Whether this bed should be shown for the current status filter. */
    public function bedMatchesStatusFilter(string $bedId): bool
    {
        $hasBookingInRange = isset($this->unitIdsInRange[$bedId]);
        $occupiedToday = isset($this->unitIdsOccupiedToday[$bedId]);
        $dueOut = isset($this->unitIdsDueOut[$bedId]);
        $noShow = isset($this->unitIdsNoShow[$bedId]);
        $dueIn = isset($this->unitIdsDueIn[$bedId]);
        $recentBookings = isset($this->unitIdsRecentBookings[$bedId]);
        switch ($this->statusFilter) {
            case 'all':
                return true;
            case 'vacant':
                return ! $hasBookingInRange;
            case 'reserved':
                return $hasBookingInRange;
            case 'occupied':
                return $occupiedToday;
            case 'due_out':
                return $dueOut;
            case 'due_in':
                return $dueIn;
            case 'blocked':
                return false;
            case 'dirty':
                return true;
            case 'no_show':
                return $noShow;
            case 'recent_bookings':
                return $recentBookings;
            default:
                return true;
        }
    }

    /** Get list of beds (id + label) that match the current status filter (for cards / room list). */
    public function getBedsForCurrentStatus(): array
    {
        $list = [];
        foreach ($this->roomTypes as $rt) {
            foreach ($rt['beds'] ?? [] as $bed) {
                $id = $bed['id'] ?? '';
                if ($id !== '' && $this->bedMatchesStatusFilter($id)) {
                    $list[] = ['id' => $id, 'label' => $bed['label'] ?? $id];
                }
            }
        }
        return $list;
    }

    /** Open room detail panel (upcoming guests, add reservation/guest). */
    public function selectRoom(string $bedId): void
    {
        $this->selectedRoomUnitId = $bedId;
        $this->selectedRoomLabel = $this->getRoomLabelForBed($bedId);
        $this->selectedBooking = null;
    }

    private function getRoomLabelForBed(string $bedId): string
    {
        foreach ($this->roomTypes as $rt) {
            foreach ($rt['beds'] ?? [] as $bed) {
                if (($bed['id'] ?? '') === $bedId) {
                    return $bed['label'] ?? $bedId;
                }
            }
        }
        return $bedId;
    }

    public function closeRoomPanel(): void
    {
        $this->selectedRoomUnitId = null;
        $this->selectedRoomLabel = '';
    }

    /** Upcoming reservations for the selected room unit (check-out >= today). */
    public function getUpcomingReservationsForSelectedRoom(): array
    {
        if (! $this->selectedRoomUnitId) {
            return [];
        }
        $hotel = Hotel::getHotel();

        // When viewing "Recent bookings", show stays for this room from the last 7 days,
        // otherwise show upcoming/future stays (check-out >= today).
        $query = Reservation::where('hotel_id', $hotel->id)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->whereHas('roomUnits', fn ($q) => $q->where('room_units.id', $this->selectedRoomUnitId))
            ->with('roomType');

        if ($this->statusFilter === 'recent_bookings') {
            $recentCutoff = Carbon::now($hotel->getTimezone())->subDays(7)->format('Y-m-d');
            $query->where('check_in_date', '>=', $recentCutoff);
        } else {
            $today = Hotel::getTodayForHotel();
            $query->where('check_out_date', '>=', $today);
        }

        $reservations = $query->orderBy('check_in_date')->get();
        $list = [];
        foreach ($reservations as $r) {
            $list[] = [
                'reservation_id' => $r->id,
                'guest_name' => $r->guest_name,
                'check_in' => $r->check_in_date->format('d M Y'),
                'check_out' => $r->check_out_date->format('d M Y'),
                'pax_adults' => $r->adult_count,
                'pax_children' => $r->child_count,
                'reservation_number' => $r->reservation_number,
                'room_type' => $r->roomType->name ?? '—',
                'currency' => $r->currency ?? 'RWF',
                'total' => (float) ($r->total_amount ?? 0),
                'paid' => (float) ($r->paid_amount ?? 0),
            ];
        }
        return $list;
    }

    /**
     * Open sidebar with reservation details (call when user clicks a booking bar).
     */
    public function selectBooking(string $bedKey, string $from): void
    {
        $this->selectedRoomUnitId = null;
        $this->selectedRoomLabel = '';
        foreach ($this->bookings as $b) {
            if (($b['bed_key'] ?? '') === $bedKey && ($b['from'] ?? '') === $from) {
                $this->selectedBooking = $b;
                return;
            }
        }
        $this->selectedBooking = null;
    }

    public function closeSidebar(): void
    {
        $this->selectedBooking = null;
        $this->showSidebarRecordPaymentModal = false;
    }

    /**
     * Printable receipt URL for the reservation sidebar: latest confirmed payment receipt,
     * or folio / balance preview when there is no payment yet (paid amount may be zero).
     */
    public function getSidebarPrintReceiptUrl(): ?string
    {
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return null;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return null;
        }
        $reservation = Reservation::where('hotel_id', $hotel->id)
            ->find((int) $this->selectedBooking['reservation_id']);
        if (! $reservation) {
            return null;
        }

        $latestPayment = ReservationPayment::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', 'confirmed')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        if ($latestPayment) {
            return route('front-office.reservation-payment-receipt', ['payment' => $latestPayment->id]);
        }

        return route('front-office.reservation-payment-receipt.preview', ['reservation_id' => $reservation->id])
            . '?payment_amount='.urlencode('0');
    }

    /**
     * mailto: link for sending the guest a link to the same printable receipt / folio page.
     */
    public function getSidebarReceiptMailtoUrl(): ?string
    {
        $printUrl = $this->getSidebarPrintReceiptUrl();
        $email = trim((string) ($this->selectedBooking['email'] ?? ''));
        if (! $printUrl || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $guest = trim((string) ($this->selectedBooking['guest_name'] ?? ''));
        $resNo = trim((string) ($this->selectedBooking['reservation_number'] ?? ''));
        $subject = 'Reservation receipt'.($resNo !== '' ? ' — '.$resNo : '');
        $body = 'Hello'.($guest !== '' ? ' '.$guest : '').",\n\n";
        $body .= "Here is your hotel receipt / folio statement:\n".$printUrl."\n\n";
        $body .= 'Thank you.';

        return 'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($body);
    }

    /** Check-in the reservation from the sidebar (no need to open Edit). */
    public function checkInReservation(int $reservationId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            session()->flash('error', 'You do not have permission to check in guests.');
            return;
        }
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->where('id', $reservationId)->firstOrFail();
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            session()->flash('error', 'Only confirmed reservations can be checked in.');
            return;
        }
        $today = Hotel::getTodayForHotel();
        if ($reservation->check_in_date->format('Y-m-d') > $today) {
            session()->flash('error', 'Cannot check in before arrival date.');
            return;
        }
        try {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        $previousStatus = $reservation->status;
        $reservation->status = Reservation::STATUS_CHECKED_IN;
        $reservation->save();
        ActivityLogger::log(
            'reservation.check_in',
            sprintf('Checked in guest %s — reservation %s', $reservation->guest_name ?? '—', $reservation->reservation_number ?? $reservation->id),
            Reservation::class,
            $reservation->id,
            ['status' => $previousStatus],
            ['status' => $reservation->status],
            ActivityLogModule::FRONT_OFFICE
        );
        $this->refreshSelectedBookingAfterAction($reservationId);
        $this->maybeRefreshAuditTrail();
        session()->flash('message', 'Guest checked in successfully.');
    }

    /** Open reservation details (Edit Stay) for check-in so receptionist can add missing details, then confirm check-in from there. */
    public function openEditStayForCheckIn(): void
    {
        $this->openEditStay();
    }

    /** Confirm check-in from Edit Stay view, then return to calendar. */
    public function confirmCheckInAndClose(): void
    {
        if (! $this->editingReservation || empty($this->editingReservation['reservation_id'])) {
            return;
        }
        $this->checkInReservation((int) $this->editingReservation['reservation_id']);
        $this->closeEditStay();
    }

    /** Check-out the reservation from the sidebar. */
    public function checkOutReservation(int $reservationId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            session()->flash('error', 'You do not have permission to check out guests.');
            return;
        }
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->where('id', $reservationId)->firstOrFail();
        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            session()->flash('error', 'Only in-house guests can be checked out.');
            return;
        }
        $previousStatus = $reservation->status;
        $reservation->status = Reservation::STATUS_CHECKED_OUT;
        $reservation->save();
        ActivityLogger::log(
            'reservation.check_out',
            sprintf('Checked out guest %s — reservation %s', $reservation->guest_name ?? '—', $reservation->reservation_number ?? $reservation->id),
            Reservation::class,
            $reservation->id,
            ['status' => $previousStatus],
            ['status' => $reservation->status],
            ActivityLogModule::FRONT_OFFICE
        );
        $this->refreshSelectedBookingAfterAction($reservationId);
        $this->maybeRefreshAuditTrail();
        session()->flash('message', 'Guest checked out successfully.');
    }

    /** Check-out confirmation: show receipt + linked invoices; confirm only when all settled. */
    public $showCheckoutModal = false;
    public $checkoutReservationId = null;

    public function openCheckoutModal(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            session()->flash('error', 'You do not have permission to check out guests.');
            return;
        }
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return;
        }
        $reservationId = (int) $this->selectedBooking['reservation_id'];
        if (($this->selectedBooking['status_raw'] ?? '') !== Reservation::STATUS_CHECKED_IN) {
            session()->flash('error', 'Only in-house guests can be checked out.');
            return;
        }
        $this->checkoutReservationId = $reservationId;
        $this->showCheckoutModal = true;
    }

    /**
     * Open the checkout / payment review modal directly for a reservation ID.
     * Used from the room sidebar so receptionist can see folio + POS invoices
     * and confirm payments without leaving the calendar.
     */
    public function openCheckoutModalForReservation(int $reservationId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            session()->flash('error', 'You do not have permission to review or confirm payments.');
            return;
        }

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            session()->flash('error', 'Reservation not found.');
            return;
        }

        $this->checkoutReservationId = $reservationId;
        $this->showCheckoutModal = true;
    }

    public function closeCheckoutModal(): void
    {
        $this->showCheckoutModal = false;
        $this->checkoutReservationId = null;
    }

    /** Data for checkout modal: reservation, folio summary, linked POS/restaurant invoices, can_confirm. */
    public function getCheckoutSummary(): array
    {
        if (! $this->checkoutReservationId) {
            return ['reservation' => null, 'folio' => null, 'invoices' => [], 'payments' => [], 'can_confirm' => false];
        }
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->with('invoices')->find($this->checkoutReservationId);
        if (! $reservation) {
            return ['reservation' => null, 'folio' => null, 'invoices' => [], 'payments' => [], 'can_confirm' => false];
        }
        $total = (float) ($reservation->total_amount ?? 0);
        $paid = (float) ($reservation->paid_amount ?? 0);
        $balance = max(0, $total - $paid);
        $folio = [
            'total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'currency' => $reservation->currency ?? 'RWF',
        ];
        $invoices = [];
        foreach ($reservation->invoices as $inv) {
            $paymentLabel = 'Unpaid';
            if ($inv->invoice_status === 'PAID') {
                $paymentLabel = 'Paid';
            } elseif ($inv->charge_type === Invoice::CHARGE_TYPE_ROOM) {
                $paymentLabel = 'Assigned to room';
            } elseif ($inv->charge_type === Invoice::CHARGE_TYPE_HOTEL_COVERED) {
                $paymentLabel = 'Hotel covered';
            } elseif ($inv->invoice_status === 'CREDIT') {
                $paymentLabel = 'Credit';
            }
            $invoices[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number ?? '—',
                'total_amount' => (float) $inv->total_amount,
                'status' => $inv->invoice_status,
                'payment_label' => $paymentLabel,
                'is_settled' => $inv->invoice_status === 'PAID' || $inv->invoice_status === 'CREDIT',
            ];
        }
        $reservationSettled = $balance <= 0;
        $allInvoicesSettled = true;
        foreach ($invoices as $inv) {
            if (! ($inv['is_settled'] ?? false)) {
                $allInvoicesSettled = false;
                break;
            }
        }
        $can_confirm = $reservationSettled && $allInvoicesSettled;

        // Hotel-side payments (who received what) for shift transparency + receipts.
        $payments = [];
        $hotelPayments = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservation->id)
            ->where('status', 'confirmed')
            ->with('receivedBy')
            ->orderBy('received_at')
            ->get();
        foreach ($hotelPayments as $p) {
            $payments[] = [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number ?? '—',
                'amount' => (float) ($p->amount ?? 0),
                'currency' => $p->currency ?? ($reservation->currency ?? 'RWF'),
                'payment_type' => $p->payment_type ?? '',
                'payment_method' => $p->payment_method ?? '',
                'payment_status' => $p->payment_status ?? PaymentCatalog::STATUS_PAID,
                'received_by' => $p->receivedBy?->name ?? '—',
                'received_at' => $p->received_at ? Carbon::parse($p->received_at)->format('d/m/Y H:i') : '',
                'total_paid_after' => (float) ($p->total_paid_after ?? 0),
                'balance_after' => (float) ($p->balance_after ?? 0),
            ];
        }

        return [
            'reservation' => [
                'guest_name' => $reservation->guest_name,
                'reservation_number' => $reservation->reservation_number,
                'room_number' => $reservation->roomUnits->first()?->label ?? '—',
            ],
            'folio' => $folio,
            'invoices' => $invoices,
            'payments' => $payments,
            'can_confirm' => $can_confirm,
        ];
    }

    public function doConfirmCheckout(): void
    {
        if (! $this->checkoutReservationId) {
            return;
        }
        $summary = $this->getCheckoutSummary();
        if (! ($summary['can_confirm'] ?? false)) {
            session()->flash('error', 'Cannot checkout until reservation balance is paid and all restaurant/invoice charges are paid or assigned (room/hotel/credit).');
            return;
        }
        $this->checkOutReservation($this->checkoutReservationId);
        $this->closeCheckoutModal();
    }

    /** Refresh sidebar booking data after check-in/check-out/payment so totals and status stay in sync. */
    protected function refreshSelectedBookingAfterAction(int $reservationId): void
    {
        $this->loadBookingsFromDb();
        $this->computeGrid();
        foreach ($this->bookings as $b) {
            if ((int) ($b['reservation_id'] ?? 0) === $reservationId) {
                $this->selectedBooking = $b;
                return;
            }
        }
        $this->selectedBooking = null;
    }

    /** Sidebar "Record payment" modal (persists to reservation.paid_amount). */
    public $showSidebarRecordPaymentModal = false;
    public $sidebarPaymentAmount = '';
    public $sidebarPaymentUnified = 'Cash';
    public $sidebarPaymentClientReference = '';
    public bool $sidebarCashSubmitLater = false;

    /** When true, this payment settles outstanding folio debt; revenue can be attributed to a past sales date. */
    public bool $sidebarDebtSettlement = false;

    /** Y-m-d — counted in rooms sales on this date when settling debt (defaults to checkout). */
    public string $sidebarRevenueAttributionDate = '';

    public function openSidebarRecordPayment(): void
    {
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return;
        }
        $balance = (float) ($this->selectedBooking['balance'] ?? 0);
        $this->sidebarPaymentAmount = $balance > 0 ? number_format($balance, 2, '.', '') : '';
        $this->sidebarPaymentUnified = PaymentCatalog::METHOD_CASH;
        $this->sidebarPaymentClientReference = '';
        $this->sidebarCashSubmitLater = false;
        $this->sidebarDebtSettlement = false;
        $this->sidebarRevenueAttributionDate = (string) ($this->selectedBooking['to'] ?? '');
        $this->showSidebarRecordPaymentModal = true;
    }

    public function updatedSidebarDebtSettlement(bool $value): void
    {
        if ($value && $this->sidebarRevenueAttributionDate === '' && $this->selectedBooking) {
            $this->sidebarRevenueAttributionDate = (string) ($this->selectedBooking['to'] ?? '');
        }
    }

    public function closeSidebarRecordPayment(): void
    {
        $this->showSidebarRecordPaymentModal = false;
        $this->sidebarPaymentAmount = '';
        $this->sidebarPaymentClientReference = '';
        $this->sidebarCashSubmitLater = false;
        $this->sidebarDebtSettlement = false;
        $this->sidebarRevenueAttributionDate = '';
    }

    public function submitSidebarRecordPayment(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_collect_payment')) {
            session()->flash('error', 'You do not have permission to collect payment.');
            return;
        }
        $rules = [
            'sidebarPaymentAmount' => 'required|numeric|min:0.01',
            'sidebarPaymentUnified' => ['required', Rule::in(PaymentCatalog::unifiedAccommodationValues())],
        ];
        if (PaymentCatalog::unifiedChoiceRequiresClientDetails($this->sidebarPaymentUnified)) {
            $rules['sidebarPaymentClientReference'] = 'required|string|min:2|max:500';
        }
        if ($this->sidebarDebtSettlement) {
            $rules['sidebarRevenueAttributionDate'] = 'required|date';
        }
        $this->validate($rules, [], [
            'sidebarPaymentAmount' => 'Amount',
            'sidebarPaymentUnified' => 'Payment type',
            'sidebarPaymentClientReference' => 'Client / account details',
            'sidebarRevenueAttributionDate' => 'Sales / revenue date',
        ]);
        $reservationId = (int) ($this->selectedBooking['reservation_id'] ?? 0);
        if (! $reservationId) {
            session()->flash('error', 'Reservation not found.');
            $this->closeSidebarRecordPayment();
            return;
        }

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->where('id', $reservationId)->firstOrFail();
        $amount = (float) $this->sidebarPaymentAmount;

        $cashLater = $this->sidebarPaymentUnified === PaymentCatalog::METHOD_CASH && $this->sidebarCashSubmitLater;
        $stored = PaymentCatalog::expandUnifiedToStorage($this->sidebarPaymentUnified, $cashLater);
        $method = PaymentCatalog::normalizeReservationMethod($stored['payment_method']);
        $pStatus = PaymentCatalog::normalizeStatus($stored['payment_status']);
        $comment = PaymentCatalog::mergeClientReferenceIntoComment('', $this->sidebarPaymentClientReference ?? '');

        $revenueAttr = null;
        if ($this->sidebarDebtSettlement) {
            $revenueAttr = $this->sidebarRevenueAttributionDate ?: ($reservation->check_out_date?->format('Y-m-d'));
        }

        $payment = ReservationPayment::create([
            'hotel_id' => $hotel->id,
            'reservation_id' => $reservationId,
            'amount' => $amount,
            'currency' => $reservation->currency ?? ($hotel->currency ?? 'RWF'),
            'payment_type' => $method,
            'payment_method' => $method,
            'payment_status' => $pStatus,
            'received_by' => $user->id,
            'received_at' => Carbon::now(),
            'receipt_number' => $this->generateReservationReceiptNumber($reservation),
            'status' => 'confirmed',
            'comment' => $comment !== '' ? $comment : null,
            'total_paid_after' => 0,
            'balance_after' => 0,
            'is_debt_settlement' => $this->sidebarDebtSettlement,
            'revenue_attribution_date' => $revenueAttr,
        ]);

        ActivityLogger::log(
            'payment.recorded',
            sprintf(
                'Recorded payment %s %s for %s (sidebar) — reservation %s',
                $reservation->currency ?? ($hotel->currency ?? 'RWF'),
                number_format($amount, 2, '.', ''),
                $reservation->guest_name ?? '—',
                $reservation->reservation_number ?? $reservationId
            ),
            ReservationPayment::class,
            $payment->id,
            null,
            [
                'amount' => $amount,
                'payment_method' => $method,
                'payment_status' => $pStatus,
                'reservation_id' => $reservationId,
                'received_by' => $user->id,
            ],
            ActivityLogModule::FRONT_OFFICE
        );

        $this->recomputeReservationPaymentBalances($reservationId);
        $this->closeSidebarRecordPayment();
        $this->refreshSelectedBookingAfterAction($reservationId);
        $this->maybeRefreshAuditTrail();
        session()->flash('message', 'Payment of ' . ($reservation->currency ?? 'RWF') . ' ' . number_format($amount, 2) . ' recorded successfully.');
    }

    /** Whether to show Check-in in the sidebar (confirmed, arrival today or past). */
    public function canShowSidebarCheckIn(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            return false;
        }
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return false;
        }
        $status = $this->selectedBooking['status_raw'] ?? '';
        if ($status !== Reservation::STATUS_CONFIRMED) {
            return false;
        }
        $today = Hotel::getTodayForHotel();
        $from = $this->selectedBooking['from'] ?? '';

        return $from !== '' && $from <= $today;
    }

    /** Whether to show Check-out in the sidebar (in-house). */
    public function canShowSidebarCheckOut(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_check_in_out')) {
            return false;
        }
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return false;
        }

        return ($this->selectedBooking['status_raw'] ?? '') === Reservation::STATUS_CHECKED_IN;
    }

    /** Whether to show Record payment in the sidebar (balance due). */
    public function canShowSidebarRecordPayment(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_collect_payment')) {
            return false;
        }
        if (! $this->selectedBooking || empty($this->selectedBooking['reservation_id'])) {
            return false;
        }
        $balance = (float) ($this->selectedBooking['balance'] ?? 0);

        return $balance > 0;
    }

    /** Open Edit Stay / Folio Operations for the currently selected reservation. */
    public function openEditStay(): void
    {
        if ($this->selectedBooking) {
            $this->editingReservation = $this->selectedBooking;
            $this->selectedBooking = null;
            $this->editStayTab = 'folio_operations';
            $this->seedFolioPayments();
            $this->folioCharges = [];
        }
    }

    /** Open Edit Stay / folio view directly for a given reservation ID (used from room sidebar). */
    public function openEditStayForReservation(int $reservationId): void
    {
        foreach ($this->bookings as $b) {
            if ((int) ($b['reservation_id'] ?? 0) === $reservationId) {
                $this->editingReservation = $b;
                $this->selectedBooking = null;
                $this->selectedRoomUnitId = null;
                $this->selectedRoomLabel = '';
                $this->editStayTab = 'folio_operations';
                $this->seedFolioPayments();
                $this->folioCharges = [];
                return;
            }
        }

        // Fallback: reservation might be outside the currently loaded calendar window.
        // Load minimal data directly from DB so modals opened from other pages still work.
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            return;
        }

        $from = $reservation->check_in_date?->format('Y-m-d') ?? '';
        $to = $reservation->check_out_date?->format('Y-m-d') ?? '';
        $nights = $reservation->check_in_date && $reservation->check_out_date
            ? (int) $reservation->check_in_date->diffInDays($reservation->check_out_date)
            : 0;
        $rate = $nights > 0 && $reservation->total_amount ? (float) $reservation->total_amount / $nights : 0;

        $this->editingReservation = [
            'reservation_id' => $reservation->id,
            'from' => $from,
            'to' => $to,
            'reservation_number' => $reservation->reservation_number,
            'guest_name' => $reservation->guest_name,
            'currency' => $reservation->currency ?? 'RWF',
            'avg_daily_rate' => number_format((float) $rate, 2, '.', ''),
            'total' => number_format((float) ($reservation->total_amount ?? 0), 2, '.', ''),
            'paid' => number_format((float) ($reservation->paid_amount ?? 0), 2, '.', ''),
            'balance' => number_format(max(0, (float) ($reservation->total_amount ?? 0) - (float) ($reservation->paid_amount ?? 0)), 2, '.', ''),
            'pax_adults' => $reservation->adult_count ?? 1,
            'pax_infants' => $reservation->child_count ?? 0,
            'status_raw' => $reservation->status,
        ];
        $this->selectedBooking = null;
        $this->selectedRoomUnitId = null;
        $this->selectedRoomLabel = '';
        $this->editStayTab = 'folio_operations';
        $this->seedFolioPayments();
        $this->folioCharges = [];
    }

    /** Seed or reset payment entries for the current folio (mock one payment so we can edit/void). */
    protected function seedFolioPayments(): void
    {
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            $this->folioPayments = [];
            return;
        }

        $hotel = Hotel::getHotel();
        $payments = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservationId)
            ->where('status', 'confirmed')
            ->with('receivedBy')
            ->orderBy('received_at')
            ->get();

        $currency = (string) ($this->editingReservation['currency'] ?? ($hotel->currency ?? 'RWF'));

        $this->folioPayments = $payments->map(function (ReservationPayment $p) use ($currency) {
            $nm = PaymentCatalog::normalizeReservationMethod($p->payment_method ?? '');
            $ns = PaymentCatalog::normalizeStatus($p->payment_status ?? PaymentCatalog::STATUS_PAID);

            return [
                'id' => (string) $p->id,
                'day' => $p->received_at ? Carbon::parse($p->received_at)->format('d/m/Y') : '',
                'ref_no' => $p->receipt_number ?? '—',
                'particulars' => 'Payment',
                'type' => $p->payment_type ?? '',
                'method' => $nm,
                'settlement_status' => $ns,
                'payment_display' => PaymentCatalog::formatPaymentLineForReport($p->payment_method, $p->payment_status),
                'amount' => number_format((float) $p->amount, 2, '.', ''),
                'currency' => $p->currency ?? $currency,
                'user' => $p->receivedBy?->name ?? '—',
                'balance_after' => number_format((float) ($p->balance_after ?? 0), 2, '.', ''),
                'status' => $p->status,
                'is_debt_settlement' => (bool) ($p->is_debt_settlement ?? false),
                'revenue_attribution_date' => $p->revenue_attribution_date?->format('Y-m-d') ?? '',
            ];
        })->all();
    }

    /**
     * Recompute:
     * - reservations.paid_amount
     * - reservation_payments.total_paid_after + balance_after
     * after create/update/void.
     */
    protected function recomputeReservationPaymentBalances(int $reservationId): void
    {
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            return;
        }

        $total = (float) ($reservation->total_amount ?? 0);
        $paidCum = 0.0;

        $payments = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservationId)
            ->where('status', 'confirmed')
            ->orderBy('received_at')
            ->get();

        foreach ($payments as $p) {
            $paidCum += (float) ($p->amount ?? 0);
            $p->total_paid_after = $paidCum;
            $p->balance_after = max(0, $total - $paidCum);
            $p->save();
        }

        $reservation->paid_amount = max(0, min($total, $paidCum));
        $reservation->save();

        if ($this->editingReservation && ((int) ($this->editingReservation['reservation_id'] ?? 0)) === $reservationId) {
            $this->editingReservation['paid'] = number_format((float) ($reservation->paid_amount ?? 0), 2, '.', '');
            $this->editingReservation['balance'] = number_format(max(0, (float) $reservation->total_amount - (float) $reservation->paid_amount), 2, '.', '');
        }
    }

    protected function generateReservationReceiptNumber(Reservation $reservation): string
    {
        $hotelId = (int) ($reservation->hotel_id ?? 0);
        // Keep it short to respect `receipt_number` length (<= 50).
        $base = 'RCPT-' . date('Ymd-His') . '-' . random_int(100, 999) . '-' . ($hotelId ?: 'H');
        return substr($base, 0, 50);
    }

    public function closeEditStay(): void
    {
        $this->editingReservation = null;
        $this->loadBookingsFromDb();
        $this->computeGrid();
    }

    /** Cancel/delete a reservation (set status to cancelled). */
    public function cancelReservation(int $reservationId): void
    {
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->where('id', $reservationId)->firstOrFail();

        $user = Auth::user();
        $isManagerRole = $user && ($user->isEffectiveGeneralManager() || $user->canNavigateModules());

        $hasPayment = (float) ($reservation->paid_amount ?? 0) > 0;
        $isCheckedIn = $reservation->status === Reservation::STATUS_CHECKED_IN;

        // Never allow direct cancel when guest is checked in or has payments – always create a request.
        if ($isCheckedIn || $hasPayment || ! $isManagerRole) {
            $supportRequest = SupportRequest::create([
                'hotel_id' => $hotel->id,
                'user_id' => $user?->id,
                'subject' => 'Reservation cancellation request: ' . ($reservation->reservation_number ?? $reservation->id),
                'message' => sprintf(
                    "User %s requested to cancel reservation %s for guest %s.\nStatus: %s\nPaid amount: %s\nCheck-in: %s\nCheck-out: %s",
                    $user?->name ?? 'Unknown',
                    $reservation->reservation_number ?? $reservation->id,
                    $reservation->guest_name ?? '—',
                    $reservation->status,
                    (string) ($reservation->paid_amount ?? 0),
                    optional($reservation->check_in_date)->format('Y-m-d') ?? '—',
                    optional($reservation->check_out_date)->format('Y-m-d') ?? '—'
                ),
                'status' => 'open',
            ]);

            ActivityLogger::log(
                'reservation.cancel_request',
                sprintf(
                    'Cancellation request for guest %s — reservation %s (support request #%s)',
                    $reservation->guest_name ?? '—',
                    $reservation->reservation_number ?? $reservation->id,
                    $supportRequest->id
                ),
                Reservation::class,
                $reservation->id,
                null,
                [
                    'support_request_id' => (int) $supportRequest->id,
                    'reservation_number' => $reservation->reservation_number,
                    'guest_name' => $reservation->guest_name,
                    'status' => $reservation->status,
                ],
                ActivityLogModule::FRONT_OFFICE
            );

            session()->flash('message', 'Cancellation request sent for manager approval. Reservation is not cancelled yet.');
            $this->maybeRefreshAuditTrail();
            return;
        }

        // Manager / admin cancelling an unpaid, not-checked-in reservation.
        $previousStatus = $reservation->status;
        $reservation->status = Reservation::STATUS_CANCELLED;
        $reservation->save();
        ActivityLogger::log(
            'reservation.cancelled',
            sprintf('Cancelled reservation %s for guest %s', $reservation->reservation_number ?? $reservation->id, $reservation->guest_name ?? '—'),
            Reservation::class,
            $reservation->id,
            ['status' => $previousStatus],
            ['status' => Reservation::STATUS_CANCELLED],
            ActivityLogModule::FRONT_OFFICE
        );
        $this->selectedBooking = null;
        $this->editingReservation = null;
        $this->loadBookingsFromDb();
        $this->computeGrid();
        session()->flash('message', 'Reservation cancelled.');
    }

    public function setEditStayTab(string $tab): void
    {
        $this->editStayTab = $tab;
        if ($tab === 'audit_trail') {
            $this->loadAuditTrail();
        }
    }

    /** Load activity log rows for the current reservation (and its payments). */
    public function loadAuditTrail(): void
    {
        $this->auditTrailRows = [];
        if (! $this->editingReservation) {
            return;
        }
        $rid = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($rid <= 0) {
            return;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return;
        }

        $paymentIds = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $rid)
            ->pluck('id');

        $logsQuery = ActivityLog::query()
            ->with('user')
            ->where(function ($q) use ($rid, $paymentIds) {
                $q->where(
                    fn ($q2) => $q2->where('model_type', Reservation::class)->where('model_id', $rid)
                );
                if ($paymentIds->isNotEmpty()) {
                    $q->orWhere(
                        fn ($q2) => $q2->where('model_type', ReservationPayment::class)->whereIn('model_id', $paymentIds)
                    );
                }
            });

        if (Schema::hasColumn('activity_logs', 'module')) {
            $logsQuery->where(function ($q) {
                $q->where('module', ActivityLogModule::FRONT_OFFICE)
                    ->orWhereNull('module');
            });
        }

        $logs = $logsQuery->orderByDesc('created_at')->limit(200)->get();

        $this->auditTrailRows = $logs->map(function (ActivityLog $log) {
            return [
                'at' => $log->created_at ? $log->created_at->format('d/m/Y H:i') : '',
                'user' => $log->user?->name ?? '—',
                'action' => $log->action,
                'description' => (string) ($log->description ?? ''),
                'ip' => (string) ($log->ip_address ?? ''),
            ];
        })->all();
    }

    protected function maybeRefreshAuditTrail(): void
    {
        if ($this->editStayTab === 'audit_trail') {
            $this->loadAuditTrail();
        }
    }

    /**
     * Mock folio transactions (room charges per night) for the stay being edited.
     * @return array [ [ day, ref_no, particulars, description, user, amount, posted ], ... ]
     */
    public function getFolioTransactions(): array
    {
        if (!$this->editingReservation) {
            return [];
        }
        $from = Carbon::parse($this->editingReservation['from']);
        $to = Carbon::parse($this->editingReservation['to']);
        $nights = (int) $from->diffInDays($to);
        $rate = (float) ($this->editingReservation['avg_daily_rate'] ?? 300);
        $user = 'Dhruv';
        $transactions = [];
        for ($i = 0; $i < $nights; $i++) {
            $date = $from->copy()->addDays($i);
            $transactions[] = [
                'day' => $date->format('d/m/Y l'),
                'ref_no' => '',
                'particulars' => 'Room Charges',
                'description' => '',
                'user' => $user,
                'amount' => number_format($rate, 2, '.', ''),
                'posted' => true,
            ];
        }
        return $transactions;
    }

    public function openAddPaymentModal(): void
    {
        $this->editingPaymentId = null;
        $this->showAddPaymentModal = true;
        $this->payment_date = Carbon::now()->format('d/m/Y');
        $this->payment_folio_display = $this->editingReservation
            ? ($this->editingReservation['reservation_number'] ?? '155') . ' - ' . ($this->editingReservation['guest_name'] ?? '—')
            : '—';
        $this->payment_rec_vou_no = 'New';
        $this->payment_unified = PaymentCatalog::METHOD_CASH;
        $this->payment_cash_submit_later = false;
        $this->payment_client_reference = '';
        $totals = $this->getFolioTotals();
        $this->payment_amount = $totals['balance'] ?? '0.00';
        $hotelCurrency = Hotel::getHotel()->currency ?? 'RWF';
        $this->payment_currency = in_array($hotelCurrency, ['INR', 'USD', 'RWF', 'EUR'], true) ? $hotelCurrency : 'RWF';
        $this->payment_comment = '';
        $this->payment_is_debt_settlement = false;
        $resId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        $resRow = $resId > 0 ? Reservation::where('hotel_id', Hotel::getHotel()->id)->find($resId) : null;
        $this->payment_revenue_attribution_date = $resRow?->check_out_date?->format('Y-m-d') ?? '';

        // Used to render current balance + linked POS invoices in the modal.
        $this->checkoutReservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        $this->paymentCheckoutSummary = $this->getCheckoutSummary();
    }

    public function updatedPaymentIsDebtSettlement(bool $value): void
    {
        if ($value && $this->payment_revenue_attribution_date === '' && $this->editingReservation) {
            $this->payment_revenue_attribution_date = (string) ($this->editingReservation['to'] ?? '');
        }
    }

    public function openEditPaymentModal(string $paymentId): void
    {
        $payment = $this->getPaymentById($paymentId);
        if (!$payment) {
            return;
        }
        $this->editingPaymentId = $paymentId;
        $this->showAddPaymentModal = true;
        $this->payment_date = $payment['day'] ?? Carbon::now()->format('d/m/Y');
        $this->payment_folio_display = $this->editingReservation
            ? ($this->editingReservation['reservation_number'] ?? '155') . ' - ' . ($this->editingReservation['guest_name'] ?? '—')
            : '—';
        $this->payment_rec_vou_no = $payment['ref_no'] ?? 'New';
        $this->payment_unified = $payment['unified'] ?? PaymentCatalog::METHOD_CASH;
        $this->payment_cash_submit_later = (bool) ($payment['cash_submit_later'] ?? false);
        $this->payment_client_reference = '';
        $this->payment_amount = $payment['amount'] ?? '0.00';
        $this->payment_currency = $payment['currency'] ?? 'INR';
        $this->payment_comment = (string) ($payment['comment'] ?? '');
        $this->payment_is_debt_settlement = (bool) ($payment['is_debt_settlement'] ?? false);
        $this->payment_revenue_attribution_date = (string) ($payment['revenue_attribution_date'] ?? '');
    }

    protected function getPaymentById(string $id): ?array
    {
        $hotel = Hotel::getHotel();
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            return null;
        }

        $payment = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservationId)
            ->where('id', (int) $id)
            ->first();

        if (! $payment) {
            return null;
        }

        $normMethod = PaymentCatalog::normalizeReservationMethod($payment->payment_method ?? '');
        $normStatus = PaymentCatalog::normalizeStatus($payment->payment_status ?? PaymentCatalog::STATUS_PAID);

        return [
            'day' => $payment->received_at ? Carbon::parse($payment->received_at)->format('d/m/Y') : Carbon::now()->format('d/m/Y'),
            'ref_no' => $payment->receipt_number ?? 'New',
            'particulars' => 'Payment',
            'type' => $payment->payment_type ?? '',
            'method' => $normMethod,
            'settlement_status' => $normStatus,
            'unified' => PaymentCatalog::collapseStorageToUnified($payment->payment_method, $payment->payment_status),
            'cash_submit_later' => $normMethod === PaymentCatalog::METHOD_CASH && $normStatus === PaymentCatalog::STATUS_PENDING,
            'amount' => number_format((float) ($payment->amount ?? 0), 2, '.', ''),
            'currency' => $payment->currency ?? ($this->editingReservation['currency'] ?? ($hotel->currency ?? 'RWF')),
            'user' => $payment->receivedBy?->name ?? '—',
            'status' => $payment->status,
            'comment' => $payment->comment,
            'is_debt_settlement' => (bool) ($payment->is_debt_settlement ?? false),
            'revenue_attribution_date' => $payment->revenue_attribution_date?->format('Y-m-d') ?? '',
        ];
    }

    public function closeAddPaymentModal(): void
    {
        $this->showAddPaymentModal = false;
        $this->editingPaymentId = null;
        $this->paymentCheckoutSummary = [];
        $this->payment_is_debt_settlement = false;
        $this->payment_revenue_attribution_date = '';
    }

    public function closeVoidModal(): void
    {
        $this->showVoidModal = false;
        $this->voidPaymentId = null;
        $this->voidReason = '';
    }

    public function openVoidModal(string $paymentId): void
    {
        $this->voidPaymentId = $paymentId;
        $this->voidReason = '';
        $this->showVoidModal = true;
    }

    public function submitVoid(): void
    {
        $this->validate(['voidReason' => 'required|string|min:1'], [], ['voidReason' => 'Reason']);
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            session()->flash('error', 'Reservation not found.');
            return;
        }

        $hotel = Hotel::getHotel();
        $payment = ReservationPayment::where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservationId)
            ->where('id', (int) $this->voidPaymentId)
            ->where('status', 'confirmed')
            ->first();

        if (! $payment) {
            session()->flash('error', 'Payment record not found.');
            return;
        }

        $snapshot = [
            'amount' => (float) ($payment->amount ?? 0),
            'payment_method' => $payment->payment_method,
            'receipt_number' => $payment->receipt_number,
            'status' => $payment->status,
        ];

        $payment->update([
            'status' => 'voided',
            'void_reason' => $this->voidReason,
            'voided_by' => (int) (Auth::user()?->id ?? 0),
            'voided_at' => Carbon::now(),
        ]);

        ActivityLogger::log(
            'payment.voided',
            sprintf(
                'Voided payment %s — amount %s %s. Reason: %s',
                $payment->receipt_number ?? ('#'.$payment->id),
                $payment->currency ?? '',
                number_format($snapshot['amount'], 2, '.', ''),
                mb_substr((string) $this->voidReason, 0, 200)
            ),
            ReservationPayment::class,
            $payment->id,
            $snapshot,
            [
                'status' => 'voided',
                'void_reason' => $this->voidReason,
                'voided_by' => Auth::id(),
            ],
            ActivityLogModule::FRONT_OFFICE
        );

        $this->recomputeReservationPaymentBalances($reservationId);
        $this->seedFolioPayments();

        session()->flash('message', 'Payment voided successfully.');
        $this->closeVoidModal();
        $this->maybeRefreshAuditTrail();
    }

    public function selectVoidReason(string $reason): void
    {
        $this->voidReason = $reason;
    }

    /** Add Charge modal */
    public function openAddChargeModal(): void
    {
        $this->showAddChargeModal = true;
        $this->charge_date = Carbon::now()->format('d/m/Y');
        $this->charge_folio_display = $this->editingReservation
            ? ($this->editingReservation['reservation_number'] ?? '155') . ' - ' . ($this->editingReservation['guest_name'] ?? '—')
            : '—';
        $this->charge_rec_vou_no = 'New';
        $this->charge_apply_when = 'check_in_and_check_out';
        $additionalCharges = AdditionalCharge::where('hotel_id', Hotel::getHotel()->id)->where('is_active', true)->orderBy('name')->get();
        $first = $additionalCharges->first();
        if ($first) {
            $this->charge_additional_charge_id = $first->id;
            $this->charge_type = $first->name;
            $this->charge_amount = $first->default_amount !== null ? (string) $first->default_amount : '';
            $this->charge_rule = $first->charge_rule ?? 'per_instance';
            $this->charge_tax_inclusive = (bool) ($first->is_tax_inclusive ?? true);
        } else {
            $this->charge_additional_charge_id = null;
            $this->charge_type = '';
            $this->charge_amount = '';
            $this->charge_rule = '';
            $this->charge_tax_inclusive = true;
        }
        $this->charge_add_as_inclusion = false;
        $this->charge_qty = 1;
        $this->charge_comment = '';
    }

    public function closeAddChargeModal(): void
    {
        $this->showAddChargeModal = false;
    }

    public function submitAddCharge(): void
    {
        if (AdditionalCharge::where('hotel_id', Hotel::getHotel()->id)->where('is_active', true)->count() === 0) {
            session()->flash('error', 'No extra charges defined. Add them in Backend → Additional charges.');
            return;
        }
        $this->validate([
            'charge_date' => 'required|string',
            'charge_additional_charge_id' => 'required|exists:additional_charges,id',
            'charge_qty' => 'required|integer|min:1',
            'charge_amount' => 'required|numeric|min:0',
        ], [], [
            'charge_date' => 'Date',
            'charge_additional_charge_id' => 'Charge',
            'charge_qty' => 'Qty',
            'charge_amount' => 'Amount',
        ]);
        $chargeDef = AdditionalCharge::findOrFail($this->charge_additional_charge_id);
        $chargeAmount = (float) $this->charge_amount;
        $chargeQty = max(1, (int) $this->charge_qty);

        // Recurrence (daily/once) based on "when to charge".
        $nights = 0;
        if (! empty($this->editingReservation['from']) && ! empty($this->editingReservation['to'])) {
            try {
                $from = Carbon::parse($this->editingReservation['from']);
                $to = Carbon::parse($this->editingReservation['to']);
                $nights = (int) $from->diffInDays($to);
            } catch (\Throwable $e) {
                $nights = 0;
            }
        }

        $recurrenceCount = match ($this->charge_apply_when) {
            'check_in_and_check_out' => 2,
            'only_on_check_in' => 1,
            'on_custom_date' => 1,
            'everyday' => max(1, $nights),
            'everyday_except_check_in' => max(0, $nights - 1),
            'everyday_except_check_in_and_check_out' => max(0, $nights - 2),
            'everyday_except_check_out' => max(0, $nights - 1),
            default => 1,
        };

        $amount = $chargeAmount * $chargeQty * $recurrenceCount;
        $this->folioCharges[] = [
            'id' => 'c' . (count($this->folioCharges) + 1),
            'day' => $this->charge_date,
            'ref_no' => $this->charge_rec_vou_no,
            'particulars' => $chargeDef->name,
            'description' => $this->charge_comment,
            'user' => 'Dhruv',
            'amount' => number_format($amount, 2, '.', ''),
            'tax_inclusive' => $this->charge_tax_inclusive,
            'add_as_inclusion' => $this->charge_add_as_inclusion,
            'apply_when' => $this->charge_apply_when,
            'charge_rule' => $this->charge_rule,
        ];

        // Persist additional charges to reservation totals so checkout updates immediately.
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($reservationId > 0) {
            $hotel = Hotel::getHotel();
            $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
            if ($reservation) {
                $previousTotal = (float) ($reservation->total_amount ?? 0);
                $reservation->total_amount = $previousTotal + (float) $amount;
                $reservation->save();
                // Update payment balances snapshots after total changes (so receipts stay correct).
                $this->recomputeReservationPaymentBalances($reservationId);

                // Keep modal/edit-stay data in sync.
                $this->editingReservation['total'] = number_format((float) $reservation->total_amount, 2, '.', '');
                $this->editingReservation['paid'] = number_format((float) ($reservation->paid_amount ?? 0), 2, '.', '');
                $this->editingReservation['balance'] = number_format(max(0, (float) $reservation->total_amount - (float) ($reservation->paid_amount ?? 0)), 2, '.', '');

                ActivityLogger::log(
                    'reservation.charge_added',
                    sprintf(
                        'Added folio charge %s — %s %s for reservation %s (%s)',
                        $chargeDef->name,
                        $reservation->currency ?? ($hotel->currency ?? 'RWF'),
                        number_format($amount, 2, '.', ''),
                        $reservation->reservation_number ?? $reservationId,
                        $reservation->guest_name ?? '—'
                    ),
                    Reservation::class,
                    $reservation->id,
                    ['total_amount' => $previousTotal],
                    [
                        'total_amount' => (float) $reservation->total_amount,
                        'charge' => $chargeDef->name,
                        'charge_amount' => $amount,
                    ],
                    ActivityLogModule::FRONT_OFFICE
                );
            }
        }

        session()->flash('message', 'Charge added successfully.' . ($this->charge_tax_inclusive ? ' (Tax inclusive.)' : ''));
        $this->closeAddChargeModal();
        $this->maybeRefreshAuditTrail();
    }

    /** More menu actions (placeholders; can be expanded later) */
    public function moreAdjustment(): void
    {
        session()->flash('message', 'Adjustment: this option will be implemented.');
    }

    public function moreRoomCharges(): void
    {
        session()->flash('message', 'Room Charges: this option will be implemented.');
    }

    public function moreTransfer(): void
    {
        session()->flash('message', 'Transfer: this option will be implemented.');
    }

    public function moreSplitFolio(): void
    {
        session()->flash('message', 'Split Folio: this option will be implemented.');
    }

    public function moreUploadFiles(): void
    {
        session()->flash('message', 'Upload files: this option will be implemented.');
    }

    public function moreMealPlan(): void
    {
        session()->flash('message', 'Meal Plan: this option will be implemented.');
    }

    public function moreInclusion(): void
    {
        session()->flash('message', 'Inclusion: this option will be implemented.');
    }

    public function submitAddPayment(): void
    {
        $rules = [
            'payment_date' => 'required|string',
            'payment_unified' => ['required', Rule::in(PaymentCatalog::unifiedAccommodationValues())],
            'payment_amount' => 'required|numeric|min:0.01',
        ];
        if (PaymentCatalog::unifiedChoiceRequiresClientDetails($this->payment_unified)) {
            $rules['payment_client_reference'] = 'required|string|min:2|max:500';
        }
        if ($this->payment_is_debt_settlement) {
            $rules['payment_revenue_attribution_date'] = 'required|date';
        }
        $this->validate($rules, [], [
            'payment_date' => 'Date',
            'payment_unified' => 'Payment type',
            'payment_client_reference' => 'Client / account details',
            'payment_amount' => 'Amount',
            'payment_revenue_attribution_date' => 'Sales / revenue date',
        ]);
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            session()->flash('error', 'Reservation not found.');
            return;
        }

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            session()->flash('error', 'Reservation not found.');
            return;
        }

        $receivedAt = null;
        try {
            $receivedAt = Carbon::createFromFormat('d/m/Y', (string) $this->payment_date)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $receivedAt = Carbon::now()->format('Y-m-d H:i:s');
        }

        $currency = $this->payment_currency ?: ($reservation->currency ?? ($hotel->currency ?? 'RWF'));
        $amount = (float) $this->payment_amount;
        $cashLater = $this->payment_unified === PaymentCatalog::METHOD_CASH && $this->payment_cash_submit_later;
        $stored = PaymentCatalog::expandUnifiedToStorage($this->payment_unified, $cashLater);
        $method = PaymentCatalog::normalizeReservationMethod($stored['payment_method']);
        $pStatus = PaymentCatalog::normalizeStatus($stored['payment_status']);
        $finalComment = PaymentCatalog::mergeClientReferenceIntoComment($this->payment_comment ?? '', $this->payment_client_reference ?? '');

        $revenueAttr = null;
        if ($this->payment_is_debt_settlement) {
            $revenueAttr = $this->payment_revenue_attribution_date ?: ($reservation->check_out_date?->format('Y-m-d'));
        }

        $userId = (int) (Auth::user()?->id ?? 0);
        if ($userId <= 0) {
            session()->flash('error', 'User not authenticated.');
            return;
        }

        // Update existing payment or create a new one.
        if ($this->editingPaymentId) {
            $payment = ReservationPayment::where('hotel_id', $hotel->id)
                ->where('reservation_id', $reservationId)
                ->where('id', (int) $this->editingPaymentId)
                ->first();

            if (! $payment) {
                session()->flash('error', 'Payment record not found.');
                return;
            }

            $before = [
                'amount' => (float) ($payment->amount ?? 0),
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'payment_status' => $payment->payment_status,
                'received_at' => $payment->received_at ? (string) $payment->received_at : null,
                'received_by' => $payment->received_by,
            ];

            $payment->update([
                'amount' => $amount,
                'currency' => $currency,
                'payment_type' => $method,
                'payment_method' => $method,
                'payment_status' => $pStatus,
                'received_at' => $receivedAt,
                'received_by' => $userId,
                'comment' => $finalComment !== '' ? $finalComment : null,
                'status' => 'confirmed',
                'voided_at' => null,
                'voided_by' => null,
                'void_reason' => null,
                'is_debt_settlement' => $this->payment_is_debt_settlement,
                'revenue_attribution_date' => $revenueAttr,
            ]);

            ActivityLogger::log(
                'payment.updated',
                sprintf(
                    'Updated payment %s for reservation %s — guest %s',
                    $payment->receipt_number ?? ('#'.$payment->id),
                    $reservation->reservation_number ?? $reservationId,
                    $reservation->guest_name ?? '—'
                ),
                ReservationPayment::class,
                $payment->id,
                $before,
                [
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_method' => $method,
                    'payment_status' => $pStatus,
                    'received_at' => $receivedAt,
                    'received_by' => $userId,
                ],
                ActivityLogModule::FRONT_OFFICE
            );

            session()->flash('message', 'Payment updated successfully.');
        } else {
            $receiptNumber = $this->payment_rec_vou_no ?: $this->generateReservationReceiptNumber($reservation);

            $payment = ReservationPayment::create([
                'hotel_id' => $hotel->id,
                'reservation_id' => $reservationId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_type' => $method,
                'payment_method' => $method,
                'payment_status' => $pStatus,
                'received_by' => $userId,
                'received_at' => $receivedAt,
                'receipt_number' => $receiptNumber,
                'status' => 'confirmed',
                'comment' => $finalComment !== '' ? $finalComment : null,
                'total_paid_after' => 0,
                'balance_after' => 0,
                'is_debt_settlement' => $this->payment_is_debt_settlement,
                'revenue_attribution_date' => $revenueAttr,
            ]);
            $this->lastRecordedPaymentId = (int) $payment->id;

            ActivityLogger::log(
                'payment.recorded',
                sprintf(
                    'Recorded payment %s %s for %s (folio) — reservation %s',
                    $currency,
                    number_format($amount, 2, '.', ''),
                    $reservation->guest_name ?? '—',
                    $reservation->reservation_number ?? $reservationId
                ),
                ReservationPayment::class,
                $payment->id,
                null,
                [
                    'amount' => $amount,
                    'payment_method' => $method,
                    'payment_status' => $pStatus,
                    'reservation_id' => $reservationId,
                    'received_by' => $userId,
                    'receipt_number' => $receiptNumber,
                ],
                ActivityLogModule::FRONT_OFFICE
            );

            session()->flash('message', 'Payment recorded successfully.');
        }

        // Recompute reservation balance snapshots and refresh UI lists.
        $this->recomputeReservationPaymentBalances($reservationId);
        $this->seedFolioPayments();
        $this->editingPaymentId = null;
        $this->paymentCheckoutSummary = $this->getCheckoutSummary();
        $this->maybeRefreshAuditTrail();

        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        $remaining = (float) ($reservation->total_amount ?? 0) - (float) ($reservation->paid_amount ?? 0);
        if ($remaining > 0) {
            session()->flash('message', 'Payment recorded (partial). Remaining balance: ' . ($reservation->currency ?? $currency) . ' ' . number_format($remaining, 2, '.', '') . '.');
        } else {
            session()->flash('message', 'Payment confirmed in full.');
        }

        // Keep the modal open so user can print receipt right away.
        $this->payment_amount = $remaining > 0 ? number_format($remaining, 2, '.', '') : '0.00';
        $this->payment_comment = '';
        $this->payment_client_reference = '';
        $this->payment_is_debt_settlement = false;
        $this->payment_revenue_attribution_date = $reservation->check_out_date?->format('Y-m-d') ?? '';
    }

    /** Total and balance for the editing stay folio. */
    public function getFolioTotals(): array
    {
        if (!$this->editingReservation) {
            return ['total' => '0.00', 'balance' => '0.00', 'currency' => 'Rs'];
        }

        // Totals for payment/checkout must come from the reservation record (source of truth).
        $reservationId = (int) ($this->editingReservation['reservation_id'] ?? 0);
        $hotel = Hotel::getHotel();
        $reservation = $reservationId > 0 ? Reservation::where('hotel_id', $hotel->id)->find($reservationId) : null;

        $total = $reservation ? (float) ($reservation->total_amount ?? 0) : (float) ($this->editingReservation['total'] ?? 0);
        $paid = $reservation ? (float) ($reservation->paid_amount ?? 0) : (float) ($this->editingReservation['paid'] ?? 0);
        $balance = max(0, $total - $paid);
        return [
            'total' => number_format($total, 2, '.', ''),
            'balance' => number_format($balance, 2, '.', ''),
            'currency' => $reservation?->currency ?? ($this->editingReservation['currency'] ?? 'Rs'),
        ];
    }

    public function getDateLabel(string $ymd): string
    {
        $d = Carbon::parse($ymd);
        return $d->format('D d M');
    }

    public function isWeekend(string $ymd): bool
    {
        $d = Carbon::parse($ymd);
        return $d->isWeekend();
    }

    /**
     * For a bed and date, return booking that spans this date (if any).
     * @return array|null [ guest_name, from, to, paid ]
     */
    public function getBookingFor(string $bedKey, string $date): ?array
    {
        $bedKey = (string) $bedKey;
        foreach ($this->bookings as $b) {
            if ((string) ($b['bed_key'] ?? '') !== $bedKey) {
                continue;
            }
            $from = $b['from'] ?? '';
            $to = $b['to'] ?? '';
            // Occupied nights: check_in <= date < check_out
            if ($from !== '' && $to !== '' && $date >= $from && $date < $to) {
                return $b;
            }
        }
        return null;
    }

    /**
     * For a bed and date, return whether this is the first day of a booking (to show bar start).
     */
    public function isBookingStart(string $bedKey, string $date): bool
    {
        $b = $this->getBookingFor($bedKey, $date);
        return $b && $b['from'] === $date;
    }

    /**
     * Span length in days for a booking bar starting at this visible date.
     * If the stay started before the visible window, we only span from the
     * current visible date up to (but not including) check_out.
     */
    public function getBookingSpan(string $bedKey, string $date): int
    {
        $b = $this->getBookingFor($bedKey, $date);
        if (! $b) {
            return 1;
        }
        $visibleStart = Carbon::parse($date);
        $stayStart = Carbon::parse($b['from']);
        $from = $visibleStart->greaterThan($stayStart) ? $visibleStart : $stayStart;
        $to = Carbon::parse($b['to']);
        // Number of occupied nights from max(check_in, visible_start) to (but not including) check_out.
        return max(1, $from->diffInDays($to));
    }

    protected function computeGrid(): void
    {
        $totalBeds = 0;
        $allBedIds = [];
        foreach ($this->roomTypes as $rt) {
            foreach ($rt['beds'] ?? [] as $bed) {
                $id = $bed['id'] ?? '';
                if ($id !== '') {
                    $totalBeds++;
                    $allBedIds[$id] = true;
                }
            }
        }

        $today = Hotel::getTodayForHotel();
        $this->unitIdsInRange = [];
        $this->unitIdsOccupiedToday = [];
        $this->unitIdsDueOut = [];
        $this->unitIdsNoShow = [];
        $this->unitIdsDueIn = [];
        $recentCutoff = \Carbon\Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $this->unitIdsRecentBookings = [];
        foreach ($this->bookings as $b) {
            $bedKey = $b['bed_key'] ?? null;
            if ($bedKey === null || $bedKey === '') {
                continue;
            }
            $from = $b['from'] ?? '';
            $to = $b['to'] ?? '';
            $statusRaw = $b['status_raw'] ?? '';
            $createdAt = $b['created_at'] ?? '';

            // Any reservation (except cancelled/checked-out which we already excluded in the query)
            // means the room is "reserved / has booking in this range".
            $this->unitIdsInRange[$bedKey] = true;

            // Explicit No-show flag for dedicated tab.
            if ($statusRaw === Reservation::STATUS_NO_SHOW) {
                $this->unitIdsNoShow[$bedKey] = true;
            }

            // In-house = guest has been actually checked in today (status CHECKED_IN)
            // and today is one of the occupied nights (check_in <= today < check_out).
            if ($statusRaw === Reservation::STATUS_CHECKED_IN && $from <= $today && $today < $to) {
                $this->unitIdsOccupiedToday[$bedKey] = true;
            }

            // Today's departure = in-house stay checking out today.
            if ($statusRaw === Reservation::STATUS_CHECKED_IN && $to === $today) {
                $this->unitIdsDueOut[$bedKey] = true;
            }

            // Today's arrival = confirmed booking (not yet checked-in) whose arrival is today.
            if ($statusRaw === Reservation::STATUS_CONFIRMED && $from === $today) {
                $this->unitIdsDueIn[$bedKey] = true;
            }

            // Recent bookings = created in the last 7 days (for the "Recent bookings" tab).
            if ($createdAt !== '' && $createdAt >= $recentCutoff) {
                $this->unitIdsRecentBookings[$bedKey] = true;
            }
        }

        // Count only real beds (keys that exist in roomTypes) so tab counts match the filtered list
        $reservedInRangeCount = count(array_intersect_key($this->unitIdsInRange, $allBedIds));
        $occupiedTodayCount = count(array_intersect_key($this->unitIdsOccupiedToday, $allBedIds));
        $dueOutCount = count(array_intersect_key($this->unitIdsDueOut, $allBedIds));
        $dueInCount = count(array_intersect_key($this->unitIdsDueIn, $allBedIds));
        $noShowCount = count(array_intersect_key($this->unitIdsNoShow, $allBedIds));
        $recentBookingsCount = count(array_intersect_key($this->unitIdsRecentBookings, $allBedIds));
        $vacantCount = max(0, $totalBeds - $reservedInRangeCount);

        $this->counts = [
            'all' => $totalBeds,
            'vacant' => $vacantCount,
            'occupied' => $occupiedTodayCount,
            'reserved' => $reservedInRangeCount,
            'blocked' => 0,
            'due_out' => $dueOutCount,
            'due_in' => $dueInCount,
            'dirty' => 0,
            'no_show' => $noShowCount,
            'recent_bookings' => $recentBookingsCount,
        ];

        foreach ($this->dates as $date) {
            $this->capacityByDate[$date] = $totalBeds;
            $this->rateByDate[$date] = '—';
            $bookedOnDate = 0;
            foreach ($this->bookings as $b) {
                $key = $b['bed_key'] ?? '';
                if (! isset($allBedIds[$key])) {
                    continue;
                }
                // Occupied nights: check_in <= date < check_out
                if ($date >= ($b['from'] ?? '') && $date < ($b['to'] ?? '')) {
                    $bookedOnDate++;
                }
            }
            $this->occupancyByDate[$date] = $totalBeds > 0 ? round($bookedOnDate / $totalBeds * 100) : 0;
            $this->occupancyDetailByDate[$date] = ['booked' => $bookedOnDate, 'total' => $totalBeds];
        }
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['grid', 'cards'], true)) {
            $this->viewMode = $mode;
        }
    }

    /**
     * Rooms (beds) for current status + room type filter, with label and status for card view.
     * @return array [ ['id' => ..., 'label' => ..., 'room_type' => ..., 'status' => ...], ... ]
     */
    public function getRoomsForCurrentFilter(): array
    {
        $today = Hotel::getTodayForHotel();
        $list = [];
        foreach ($this->roomTypes as $slug => $rt) {
            if ($this->roomTypeFilter && $this->roomTypeFilter !== $slug) {
                continue;
            }
            foreach ($rt['beds'] ?? [] as $bed) {
                $id = $bed['id'] ?? '';
                if ($id === '' || ! $this->bedMatchesStatusFilter($id)) {
                    continue;
                }
                $status = 'vacant';
                if (isset($this->unitIdsOccupiedToday[$id])) {
                    $status = 'occupied';
                } elseif (isset($this->unitIdsDueOut[$id])) {
                    $status = 'due_out';
                } elseif (isset($this->unitIdsNoShow[$id])) {
                    $status = 'no_show';
                } elseif (isset($this->unitIdsInRange[$id])) {
                    $status = 'reserved';
                }
                if (isset($this->unitIdsDueIn[$id]) && $status === 'reserved') {
                    $status = 'due_in';
                }
                $list[] = [
                    'id' => $id,
                    'label' => $bed['label'] ?? $id,
                    'room_type' => $rt['name'] ?? '',
                    'status' => $status,
                ];
            }
        }
        return $list;
    }

    /** Number of rooms (beds) matching the current status and type filter. */
    public function getFilteredRoomsCount(): int
    {
        return count($this->getRoomsForCurrentFilter());
    }

    /** Bookings with no room assigned (bed_key starts with 'r') for the Unassigned row in the grid. */
    public function getUnassignedBookingsForGrid(): array
    {
        $list = [];
        foreach ($this->bookings as $b) {
            $key = (string) ($b['bed_key'] ?? '');
            if ($key !== '' && str_starts_with($key, 'r')) {
                $list[] = [
                    'bed_key' => $key,
                    'label' => ($b['guest_name'] ?? 'Guest') . ' (Unassigned)',
                ];
            }
        }
        return $list;
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $additionalCharges = AdditionalCharge::where('hotel_id', $hotel->id)->where('is_active', true)->orderBy('name')->get();
        return view('livewire.front-office.front-office-admin', [
            'additionalCharges' => $additionalCharges,
        ]);
    }
}
