<?php

namespace App\Livewire\FrontOffice;

use App\Enums\MealPlan;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\ReservationCheckInService;
use App\Services\ReservationManageService;
use App\Services\ReservationStayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * All reservations page: tabs, search, grid/list view, and in-page check-in with guest details.
 */
class ReservationsList extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'all';

    public string $viewMode = 'grid';

    #[Url]
    public string $search = '';

    public bool $showCheckInModal = false;

    public ?int $checkInReservationId = null;

    public string $checkInReservationNumber = '';

    public string $checkInBookerName = '';

    public string $checkInBookerPhone = '';

    public string $checkInBookerEmail = '';

    public bool $contactIsStayingGuest = true;

    public int $checkInAdultCount = 1;

    /** @var list<array{full_name: string, id_number: string, phone: string, email: string, country: string, check_in_date: string, check_out_date: string, is_primary: bool}> */
    public array $checkInGuests = [];

    public bool $checkInNeedsRoom = false;

    public string $checkInRoomUnitId = '';

    public string $checkInRoomTypeName = '';

    public string $checkInReservationCheckIn = '';

    public string $checkInReservationCheckOut = '';

    /** @var 'bb'|'hb'|'fb' */
    public string $checkInMealPlan = 'bb';

    public bool $checkInIsRoomComplimentary = false;

    public bool $checkInIsMealComplimentary = false;

    public string $checkInComplimentaryReason = '';

    public string $checkInRoomRateAmount = '0';

    public string $checkInMealPlanSupplement = '0';

    public string $checkInTotalAmount = '0';

    public string $checkInCurrency = 'RWF';

    public bool $showExtendModal = false;

    public ?int $extendReservationId = null;

    public string $extendReservationNumber = '';

    public string $extendCurrentCheckOut = '';

    public string $extendNewCheckOutDate = '';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);

        $tab = request()->query('tab', $this->tab);
        if (in_array($tab, ['all', 'arrivals', 'departures', 'in_house', 'no_show', 'cancelled', 'checked_out_today'], true)) {
            $this->tab = $tab;
        }

        if (request()->filled('search')) {
            $this->search = (string) request()->query('search');
        }

        $checkin = request()->query('checkin');
        if ($checkin) {
            $this->openCheckInModal((int) $checkin);
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['all', 'arrivals', 'departures', 'in_house', 'no_show', 'cancelled', 'checked_out_today'], true)) {
            $this->tab = $tab;
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function openCheckInModal(int $reservationId): void
    {
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)
            ->with(['guests', 'roomUnits.room', 'roomType'])
            ->find($reservationId);

        if (! $reservation) {
            session()->flash('error', 'Reservation not found.');

            return;
        }

        if (! ReservationCheckInService::canCheckInToday($reservation)) {
            session()->flash('error', 'This reservation cannot be checked in yet.');

            return;
        }

        $this->checkInReservationId = $reservation->id;
        $this->checkInReservationNumber = $reservation->reservation_number ?? '';
        $this->checkInBookerName = $reservation->booker_name ?: $reservation->guest_name ?? '';
        $this->checkInBookerPhone = $reservation->guest_phone ?? '';
        $this->checkInBookerEmail = $reservation->guest_email ?? '';
        $this->checkInAdultCount = max(1, (int) ($reservation->adult_count ?? 1));
        $this->contactIsStayingGuest = $reservation->booker_name === null || $reservation->booker_name === $reservation->guest_name;
        $this->checkInGuests = ReservationCheckInService::defaultGuestRowsForReservation($reservation);
        $stay = ReservationCheckInService::defaultReservationStayDates($reservation);
        $this->checkInReservationCheckIn = $stay['check_in_date'];
        $this->checkInReservationCheckOut = $stay['check_out_date'];
        $this->checkInNeedsRoom = ReservationCheckInService::reservationNeedsRoomAssignment($reservation);
        $this->checkInRoomTypeName = $reservation->roomType?->name ?? '—';
        $this->checkInRoomUnitId = '';
        $this->checkInCurrency = $reservation->currency ?? ($hotel->currency ?? 'RWF');
        $this->checkInMealPlan = $reservation->mealPlanEnum()->value;
        $this->checkInIsRoomComplimentary = $reservation->isRoomComplimentary();
        $this->checkInIsMealComplimentary = $reservation->isMealComplimentary();
        $this->checkInComplimentaryReason = (string) ($reservation->complimentary_reason ?? '');
        $roomRate = $reservation->room_rate_amount ?? $reservation->total_amount ?? 0;
        $this->checkInRoomRateAmount = number_format((float) $roomRate, 2, '.', '');
        $this->checkInMealPlanSupplement = number_format((float) ($reservation->meal_plan_supplement ?? 0), 2, '.', '');
        $this->checkInTotalAmount = number_format((float) ($reservation->total_amount ?? $roomRate), 2, '.', '');
        $this->syncCheckInPricing();
        $this->showCheckInModal = true;
    }

    public function updatedCheckInMealPlan(): void
    {
        $this->syncCheckInPricing();
    }

    public function updatedCheckInRoomRateAmount(): void
    {
        $this->syncCheckInPricing();
    }

    public function updatedCheckInMealPlanSupplement(): void
    {
        $this->syncCheckInPricing();
    }

    public function updatedCheckInIsRoomComplimentary(): void
    {
        $this->syncCheckInPricing();
    }

    public function updatedCheckInIsMealComplimentary(): void
    {
        $this->syncCheckInPricing();
    }

    protected function syncCheckInPricing(): void
    {
        $plan = MealPlan::parse($this->checkInMealPlan);
        $roomRef = max(0, (float) preg_replace('/[^\d.]/', '', $this->checkInRoomRateAmount));

        if (! $plan->allowsMealSupplement() || $this->checkInIsMealComplimentary) {
            $this->checkInMealPlanSupplement = '0.00';
        }

        $suppRef = $plan->allowsMealSupplement()
            ? max(0, (float) preg_replace('/[^\d.]/', '', $this->checkInMealPlanSupplement))
            : 0.0;

        $this->checkInMealPlanSupplement = number_format($suppRef, 2, '.', '');
        [, , $total] = ReservationManageService::computeCharges(
            $plan,
            $roomRef,
            $suppRef,
            $this->checkInIsRoomComplimentary,
            $this->checkInIsMealComplimentary,
        );
        $this->checkInTotalAmount = number_format($total, 2, '.', '');
    }

    /** @return \Illuminate\Support\Collection<int, RoomUnit> */
    public function getCheckInAvailableRoomUnitsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->checkInReservationId || ! $this->checkInNeedsRoom) {
            return collect();
        }

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($this->checkInReservationId);
        if (! $reservation) {
            return collect();
        }

        $checkIn = $reservation->check_in_date->format('Y-m-d');
        $checkOut = $reservation->check_out_date->format('Y-m-d');
        $bookedIds = Reservation::where('hotel_id', $hotel->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>', $checkIn)
            ->where('check_in_date', '<', $checkOut)
            ->whereHas('roomUnits')
            ->with('roomUnits')
            ->get()
            ->pluck('roomUnits')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->all();

        $roomQuery = Room::where('hotel_id', $hotel->id)->where('is_active', true);
        if ($reservation->room_type_id) {
            $roomQuery->where('room_type_id', $reservation->room_type_id);
        }

        $roomIds = $roomQuery->pluck('id');

        return RoomUnit::whereIn('room_id', $roomIds)
            ->where('is_active', true)
            ->whereNotIn('id', $bookedIds)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    public function closeCheckInModal(): void
    {
        $this->showCheckInModal = false;
        $this->checkInReservationId = null;
        $this->resetValidation();
    }

    public function updatedContactIsStayingGuest(bool $value): void
    {
        if ($value && $this->checkInGuests !== []) {
            $this->checkInGuests[0]['full_name'] = $this->checkInBookerName;
            $this->checkInGuests[0]['phone'] = $this->checkInBookerPhone;
            $this->checkInGuests[0]['email'] = $this->checkInBookerEmail;
        } elseif (! $value && isset($this->checkInGuests[0])) {
            $this->checkInGuests[0]['full_name'] = '';
        }
    }

    public function addCheckInGuestRow(): void
    {
        if (count($this->checkInGuests) >= $this->checkInAdultCount) {
            return;
        }
        $this->checkInGuests[] = [
            'full_name' => '',
            'id_number' => '',
            'phone' => '',
            'email' => '',
            'country' => '',
            'check_in_date' => $this->checkInReservationCheckIn,
            'check_out_date' => $this->checkInReservationCheckOut,
            'is_primary' => false,
        ];
    }

    public function removeCheckInGuestRow(int $index): void
    {
        if ($index <= 0 || ! isset($this->checkInGuests[$index])) {
            return;
        }
        unset($this->checkInGuests[$index]);
        $this->checkInGuests = array_values($this->checkInGuests);
    }

    public function confirmCheckIn(): void
    {
        if (! $this->checkInReservationId) {
            return;
        }

        $rules = [];
        foreach ($this->checkInGuests as $i => $guest) {
            $rules["checkInGuests.{$i}.full_name"] = $i === 0 ? 'required|string|min:2' : 'nullable|string|min:2';
            $rules["checkInGuests.{$i}.id_number"] = 'nullable|string|max:100';
            if ($i > 0 && trim((string) ($guest['full_name'] ?? '')) !== '') {
                $rules["checkInGuests.{$i}.phone"] = 'required|string|max:50';
                $rules["checkInGuests.{$i}.check_in_date"] = 'required|date';
                $rules["checkInGuests.{$i}.check_out_date"] = 'required|date|after:checkInGuests.'.$i.'.check_in_date';
            }
        }
        if ($this->checkInNeedsRoom) {
            $rules['checkInRoomUnitId'] = 'required';
        }
        $rules['checkInMealPlan'] = 'required|in:bb,hb,fb';
        $rules['checkInRoomRateAmount'] = 'required|numeric|min:0';
        $rules['checkInMealPlanSupplement'] = 'nullable|numeric|min:0';
        $rules['checkInTotalAmount'] = 'required|numeric|min:0';
        if ($this->checkInIsRoomComplimentary || $this->checkInIsMealComplimentary) {
            $rules['checkInComplimentaryReason'] = 'required|string|min:3|max:2000';
        }

        $messages = [];
        $attributes = [
            'checkInGuests.0.full_name' => 'Primary guest name',
            'checkInRoomUnitId' => 'Room / unit',
        ];
        foreach ($this->checkInGuests as $i => $guest) {
            if ($i > 0 && trim((string) ($guest['full_name'] ?? '')) !== '') {
                $guestLabel = 'Guest '.($i + 1);
                $attributes["checkInGuests.{$i}.phone"] = $guestLabel.' phone';
                $attributes["checkInGuests.{$i}.check_in_date"] = $guestLabel.' check-in date';
                $attributes["checkInGuests.{$i}.check_out_date"] = $guestLabel.' check-out date';
                $messages["checkInGuests.{$i}.phone.required"] = 'Enter a phone number for '.$guestLabel.'.';
                $messages["checkInGuests.{$i}.check_in_date.required"] = 'Select a check-in date for '.$guestLabel.'.';
                $messages["checkInGuests.{$i}.check_out_date.required"] = 'Select a check-out date for '.$guestLabel.'.';
                $messages["checkInGuests.{$i}.check_out_date.after"] = 'Check-out must be after check-in for '.$guestLabel.'.';
            }
        }
        $this->validate($rules, $messages, $attributes);

        if (! $this->contactIsStayingGuest) {
            $primary = trim($this->checkInGuests[0]['full_name'] ?? '');
            if ($primary !== '' && strcasecmp($primary, trim($this->checkInBookerName)) === 0) {
                $this->addError('checkInGuests.0.full_name', 'Enter the name of the person staying in the room (contact person is different).');

                return;
            }
        }

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($this->checkInReservationId);
        if (! $reservation) {
            session()->flash('error', 'Reservation not found.');
            $this->closeCheckInModal();

            return;
        }

        $guests = array_values(array_filter($this->checkInGuests, fn ($g) => trim((string) ($g['full_name'] ?? '')) !== ''));
        if ($guests === []) {
            $this->addError('checkInGuests.0.full_name', 'Enter at least one guest who will stay in the room.');

            return;
        }
        $guests[0]['is_primary'] = true;

        $this->syncCheckInPricing();
        ReservationManageService::validateComplimentary(
            $this->checkInIsRoomComplimentary,
            $this->checkInIsMealComplimentary,
            $this->checkInComplimentaryReason,
        );
        $plan = MealPlan::parse($this->checkInMealPlan);
        $reservation->meal_plan = $plan->value;
        $reservation->room_rate_amount = (float) $this->checkInRoomRateAmount;
        $reservation->meal_plan_supplement = (float) $this->checkInMealPlanSupplement;
        $reservation->is_room_complimentary = $this->checkInIsRoomComplimentary;
        $reservation->is_meal_complimentary = $this->checkInIsMealComplimentary;
        $reservation->complimentary_reason = ($this->checkInIsRoomComplimentary || $this->checkInIsMealComplimentary)
            ? trim($this->checkInComplimentaryReason)
            : null;
        $reservation->total_amount = (float) $this->checkInTotalAmount;
        $reservation->save();

        try {
            ReservationCheckInService::checkIn(
                $reservation,
                $guests,
                $this->contactIsStayingGuest,
                Auth::user(),
                $this->checkInNeedsRoom ? (int) $this->checkInRoomUnitId : null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->closeCheckInModal();
        $this->tab = 'in_house';
        session()->flash('message', 'Guest checked in successfully for '.$reservation->reservation_number.'.');
    }

    public function openExtendModal(int $reservationId): void
    {
        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation || $reservation->status !== Reservation::STATUS_CHECKED_IN) {
            session()->flash('error', 'Only in-house guests can extend their stay.');

            return;
        }

        $this->extendReservationId = $reservation->id;
        $this->extendReservationNumber = $reservation->reservation_number ?? '';
        $this->extendCurrentCheckOut = $reservation->check_out_date->format('Y-m-d');
        $this->extendNewCheckOutDate = Carbon::parse($this->extendCurrentCheckOut)->addDay()->format('Y-m-d');
        $this->showExtendModal = true;
    }

    public function closeExtendModal(): void
    {
        $this->showExtendModal = false;
        $this->extendReservationId = null;
        $this->resetValidation();
    }

    public function confirmExtendStay(): void
    {
        if (! $this->extendReservationId) {
            return;
        }

        $this->validate([
            'extendNewCheckOutDate' => 'required|date|after:extendCurrentCheckOut',
        ], [], [
            'extendNewCheckOutDate' => 'New check-out date',
        ]);

        $hotel = Hotel::getHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)->find($this->extendReservationId);
        if (! $reservation) {
            session()->flash('error', 'Reservation not found.');
            $this->closeExtendModal();

            return;
        }

        $newCheckOut = $this->extendNewCheckOutDate;

        try {
            ReservationStayService::extendStay($reservation, $newCheckOut, Auth::user());
        } catch (ValidationException $e) {
            throw $e;
        }

        $number = $reservation->reservation_number;
        $this->closeExtendModal();
        session()->flash('message', 'Stay extended to '.$newCheckOut.' for '.$number.'.');
    }

    protected function getToday(): string
    {
        return Hotel::getTodayForHotel();
    }

    protected function baseQuery(): Builder
    {
        $hotel = Hotel::getHotel();

        return Reservation::where('hotel_id', $hotel->id)
            ->with(['roomType', 'roomUnits.room', 'guests']);
    }

    protected function applyTabFilter(Builder $query, ?string $tab = null): Builder
    {
        return $this->applyTabFilterFor($query, $tab ?? $this->tab);
    }

    protected function applyTabFilterFor(Builder $query, string $tab): Builder
    {
        $today = $this->getToday();

        return match ($tab) {
            'arrivals' => $query
                ->whereDate('check_in_date', $today)
                ->where('status', Reservation::STATUS_CONFIRMED),
            'departures' => $query
                ->whereDate('check_out_date', $today)
                ->where('status', Reservation::STATUS_CHECKED_IN),
            'in_house' => ReservationStayService::inHouseQuery($query, $today),
            'no_show' => $query->where('status', Reservation::STATUS_NO_SHOW),
            'cancelled' => $query->where('status', Reservation::STATUS_CANCELLED),
            'checked_out_today' => $query
                ->where('status', Reservation::STATUS_CHECKED_OUT)
                ->whereDate('check_out_date', $today),
            default => $query
                ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
                ->whereDate('check_out_date', '>=', $today),
        };
    }

    protected function applySearch(Builder $query): Builder
    {
        $q = trim($this->search);
        if ($q === '') {
            return $query;
        }

        $like = '%'.$q.'%';
        $digits = preg_replace('/\D+/', '', $q);

        return $query->where(function (Builder $b) use ($like, $digits) {
            $b->where('guest_name', 'like', $like)
                ->orWhere('booker_name', 'like', $like)
                ->orWhere('guest_phone', 'like', $like)
                ->orWhere('guest_email', 'like', $like)
                ->orWhere('reservation_number', 'like', $like)
                ->orWhereHas('guests', fn (Builder $g) => $g->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like))
                ->orWhereHas('roomUnits', function (Builder $u) use ($like) {
                    $u->where('label', 'like', $like)
                        ->orWhereHas('room', function (Builder $r) use ($like) {
                            $r->where('room_number', 'like', $like)->orWhere('name', 'like', $like);
                        });
                });

            if ($digits !== '') {
                $b->orWhere('guest_phone', 'like', '%'.$digits.'%');
            }
        });
    }

    protected function filteredReservationQuery(?string $tab = null): Builder
    {
        $query = $this->baseQuery();
        $query = $this->applyTabFilterFor($query, $tab ?? $this->tab);

        return $this->applySearch($query);
    }

    public function tabLabel(): string
    {
        return match ($this->tab) {
            'arrivals' => 'Expected arrivals',
            'departures' => 'Expected departures',
            'in_house' => 'In-house guests',
            'no_show' => 'No show',
            'cancelled' => 'Cancelled',
            'checked_out_today' => 'Checked out today',
            default => 'All reservations',
        };
    }

    public function getReservations(): \Illuminate\Support\Collection
    {
        $reservations = $this->filteredReservationQuery()
            ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', [
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CONFIRMED,
            ])
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get();
        $today = $this->getToday();
        $currency = Hotel::getHotel()->currency ?? 'RWF';
        $canCheckIn = ReservationCheckInService::userCanCheckIn(Auth::user());
        $canManageStay = ReservationStayService::userCanManageStay(Auth::user());

        $rows = [];
        foreach ($reservations as $r) {
            $checkInDt = $r->check_in_date->format('d/m/Y').($r->check_in_time ? ' '.Carbon::parse($r->check_in_time)->format('H:i:s') : '');
            $checkOutDt = $r->check_out_date->format('d/m/Y').($r->check_out_time ? ' '.Carbon::parse($r->check_out_time)->format('H:i:s') : '');
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            $bookingDate = $r->created_at ? $r->created_at->format('d/m/Y') : '—';
            $total = (float) ($r->total_amount ?? 0);
            $paid = (float) ($r->paid_amount ?? 0);
            $balance = $total - $paid;

            $units = $r->roomUnits;
            $checkOutYmd = $r->check_out_date->format('Y-m-d');
            $isDueToday = $checkOutYmd === $today;
            $isOverstay = $r->status === Reservation::STATUS_CHECKED_IN && $checkOutYmd < $today;
            $isArrivalToday = $r->status === Reservation::STATUS_CONFIRMED
                && $r->check_in_date->format('Y-m-d') === $today;
            $isInHouse = $r->status === Reservation::STATUS_CHECKED_IN;
            $canCheckout = $isInHouse && $canManageStay;
            $canExtendStay = $isInHouse && $canManageStay;
            $canAddPayment = $balance > 0;
            $canAddExtras = $isInHouse;
            $canCheckInRow = $canCheckIn && ReservationCheckInService::canCheckInToday($r);
            $statusLabel = match ($r->status) {
                Reservation::STATUS_CHECKED_IN => $isOverstay
                    ? 'In-house · Overstay'
                    : ($isDueToday ? 'In-house · Departs today' : 'In-house'),
                Reservation::STATUS_CHECKED_OUT => 'Checked-out',
                Reservation::STATUS_CANCELLED => 'Cancelled',
                Reservation::STATUS_NO_SHOW => 'No-show',
                default => $isArrivalToday ? 'Arrival today' : 'Booked / Reserved',
            };
            $statusBadge = match ($r->status) {
                Reservation::STATUS_CHECKED_IN => 'primary',
                Reservation::STATUS_CHECKED_OUT => 'secondary',
                Reservation::STATUS_CANCELLED => 'secondary',
                Reservation::STATUS_NO_SHOW => 'danger',
                default => ($isArrivalToday ? 'info' : 'success'),
            };
            $baseRow = [
                'reservation' => $r,
                'reservation_id' => $r->id,
                'reservation_number' => $r->reservation_number,
                'guest_name' => $r->guest_name,
                'booker_name' => $r->booker_name,
                'check_in' => $checkInDt,
                'check_out' => $checkOutDt,
                'nights' => $nights,
                'booking_date' => $bookingDate,
                'adult_count' => $r->adult_count ?? 0,
                'child_count' => $r->child_count ?? 0,
                'rate_plan' => $r->rate_plan ?? '—',
                'room_type' => $r->roomType?->name ?? '—',
                'total' => $total,
                'paid' => $paid,
                'balance' => $balance,
                'currency' => $currency,
                'status' => $r->status,
                'status_label' => $statusLabel,
                'status_badge' => $statusBadge,
                'can_checkout' => $canCheckout,
                'can_extend_stay' => $canExtendStay,
                'can_check_in' => $canCheckInRow,
                'can_add_payment' => $canAddPayment,
                'can_add_extras' => $canAddExtras,
                'is_arrival_today' => $isArrivalToday,
                'is_overstay' => $isOverstay,
                'is_due_today' => $isDueToday,
            ];
            if ($units->isEmpty()) {
                $rows[] = array_merge($baseRow, [
                    'room_label' => '—',
                    'room_number' => '—',
                ]);
            } else {
                foreach ($units as $unit) {
                    $room = $unit->room;
                    $roomNumber = $room ? ($room->room_number ?? $unit->label) : $unit->label;
                    $rows[] = array_merge($baseRow, [
                        'room_label' => $unit->label,
                        'room_number' => $roomNumber,
                    ]);
                }
            }
        }

        return collect($rows);
    }

    public function render()
    {
        return view('livewire.front-office.reservations-list', [
            'tabLabel' => $this->tabLabel(),
            'reservations' => $this->getReservations(),
            'currency' => Hotel::getHotel()->currency ?? 'RWF',
            'canCheckInPermission' => ReservationCheckInService::userCanCheckIn(Auth::user()),
            'canManageStay' => ReservationStayService::userCanManageStay(Auth::user()),
        ])->layout('livewire.layouts.app-layout');
    }
}
