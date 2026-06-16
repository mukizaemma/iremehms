<?php

namespace App\Livewire\FrontOffice;

use App\Enums\MealPlan;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\ReservationFolioService;
use App\Services\ReservationManageService;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ReservationDetails extends Component
{
    use ChecksModuleStatus;

    /**
     * Do not type-hint as Reservation — the `{reservation}` route parameter name matches this property,
     * which triggers routing/binding quirks and breaks mount lookup when Livewire passes a model instance.
     *
     * @var Reservation|null
     */
    public $reservation = null;

    public string $editGuestName = '';

    public string $editGuestPhone = '';

    public string $editGuestEmail = '';

    public string $editCheckInDate = '';

    public string $editCheckOutDate = '';

    public string $editMealPlan = 'bb';

    public string $editRoomRateAmount = '0';

    public string $editMealPlanSupplement = '0';

    public string $editTotalAmount = '0';

    public string $editBreakfastTime = '';

    public string $editLunchTime = '';

    public string $editDinnerTime = '';

    public bool $editBreakfastInRoom = false;

    public bool $editLunchInRoom = false;

    public bool $editDinnerInRoom = false;

    public string $editMealServiceNotes = '';

    public bool $editIsRoomComplimentary = false;

    public bool $editIsMealComplimentary = false;

    public string $editComplimentaryReason = '';

    /** @var list<array<string, mixed>> */
    public array $editGuestRows = [];

    public bool $showMoveRoomModal = false;

    public string $moveRoomUnitId = '';

    public string $moveRoomReason = '';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');

        if (! Auth::user()) {
            abort(403);
        }

        /** @var Reservation|string|int|null $routeKey */
        $routeKey = request()->route('reservation');

        // Handle edge cases where a model instance is already on the route (custom binding).
        if ($routeKey instanceof Reservation) {
            $hotel = Hotel::getHotel();
            if (! $hotel || (int) $routeKey->hotel_id !== (int) $hotel->id) {
                abort(404, 'Reservation not found.');
            }

            $this->reservation = $routeKey->load(ReservationFolioService::FOLIO_RELATIONS);
            $this->loadFormFromReservation();

            return;
        }

        if ($routeKey === null || $routeKey === '') {
            abort(404, 'Reservation not found.');
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'No hotel context for this reservation.');
        }

        $withRelations = ReservationFolioService::FOLIO_RELATIONS;

        $keyStr = trim((string) $routeKey);

        if ($keyStr !== '' && ctype_digit($keyStr)) {
            $this->reservation = Reservation::query()
                ->where('hotel_id', $hotel->id)
                ->with($withRelations)
                ->where('id', (int) $keyStr)
                ->first();
        } else {
            $this->reservation = Reservation::query()
                ->where('hotel_id', $hotel->id)
                ->with($withRelations)
                ->where('reservation_number', $keyStr)
                ->first();
        }

        if (! $this->reservation) {
            abort(404, 'Reservation not found.');
        }

        $this->loadFormFromReservation();
    }

    protected function refreshReservationForView(): void
    {
        $this->reservation?->loadMissing(ReservationFolioService::FOLIO_RELATIONS);
    }

    protected function loadFormFromReservation(): void
    {
        $r = $this->reservation;
        if (! $r) {
            return;
        }

        $this->editGuestName = (string) ($r->guest_name ?? '');
        $this->editGuestPhone = (string) ($r->guest_phone ?? '');
        $this->editGuestEmail = (string) ($r->guest_email ?? '');
        $this->editCheckInDate = $r->check_in_date->format('Y-m-d');
        $this->editCheckOutDate = $r->check_out_date->format('Y-m-d');
        $this->editMealPlan = $r->mealPlanEnum()->value;
        $this->editIsRoomComplimentary = $r->isRoomComplimentary();
        $this->editIsMealComplimentary = $r->isMealComplimentary();
        $this->editComplimentaryReason = (string) ($r->complimentary_reason ?? '');
        $roomRate = $r->room_rate_amount ?? $r->total_amount ?? 0;
        $this->editRoomRateAmount = number_format((float) $roomRate, 2, '.', '');
        $this->editMealPlanSupplement = number_format((float) ($r->meal_plan_supplement ?? 0), 2, '.', '');
        $this->editTotalAmount = number_format((float) ($r->total_amount ?? 0), 2, '.', '');
        $this->editBreakfastTime = ReservationManageService::formatTimeForInput($r->breakfast_preferred_time);
        $this->editLunchTime = ReservationManageService::formatTimeForInput($r->lunch_preferred_time);
        $this->editDinnerTime = ReservationManageService::formatTimeForInput($r->dinner_preferred_time);
        $this->editBreakfastInRoom = (bool) $r->breakfast_in_room;
        $this->editLunchInRoom = (bool) $r->lunch_in_room;
        $this->editDinnerInRoom = (bool) $r->dinner_in_room;
        $this->editMealServiceNotes = (string) ($r->meal_service_notes ?? '');

        $this->editGuestRows = $r->guests->map(fn ($g) => [
            'id' => $g->id,
            'full_name' => $g->full_name,
            'check_in_date' => $g->check_in_date?->format('Y-m-d') ?? $this->editCheckInDate,
            'check_out_date' => $g->check_out_date?->format('Y-m-d') ?? $this->editCheckOutDate,
            'breakfast_preferred_time' => ReservationManageService::formatTimeForInput($g->breakfast_preferred_time),
            'dinner_preferred_time' => ReservationManageService::formatTimeForInput($g->dinner_preferred_time),
            'breakfast_in_room' => (bool) $g->breakfast_in_room,
            'dinner_in_room' => (bool) $g->dinner_in_room,
            'meal_service_notes' => (string) ($g->meal_service_notes ?? ''),
        ])->values()->all();

        $this->syncEditPricing();
    }

    public function updatedEditMealPlan(): void
    {
        $this->syncEditPricing();
    }

    public function updatedEditRoomRateAmount(): void
    {
        $this->syncEditPricing();
    }

    public function updatedEditMealPlanSupplement(): void
    {
        $this->syncEditPricing();
    }

    public function updatedEditIsRoomComplimentary(): void
    {
        $this->syncEditPricing();
    }

    public function updatedEditIsMealComplimentary(): void
    {
        $this->syncEditPricing();
    }

    protected function syncEditPricing(): void
    {
        $plan = MealPlan::parse($this->editMealPlan);
        $roomRef = max(0, (float) preg_replace('/[^\d.]/', '', $this->editRoomRateAmount));

        if (! $plan->allowsMealSupplement() || $this->editIsMealComplimentary) {
            $this->editMealPlanSupplement = '0.00';
        }

        $suppRef = $plan->allowsMealSupplement()
            ? max(0, (float) preg_replace('/[^\d.]/', '', $this->editMealPlanSupplement))
            : 0.0;

        $this->editMealPlanSupplement = number_format($suppRef, 2, '.', '');
        [, , $total] = ReservationManageService::computeCharges(
            $plan,
            $roomRef,
            $suppRef,
            $this->editIsRoomComplimentary,
            $this->editIsMealComplimentary,
        );
        $this->editTotalAmount = number_format($total, 2, '.', '');
    }

    public function saveReservation(): void
    {
        if (! $this->reservation || ! $this->canEdit) {
            return;
        }

        $rules = [
            'editGuestName' => 'required|string|min:2',
            'editCheckInDate' => 'required|date',
            'editCheckOutDate' => 'required|date|after:editCheckInDate',
            'editMealPlan' => 'required|in:bb,hb,fb',
            'editRoomRateAmount' => 'required|numeric|min:0',
            'editMealPlanSupplement' => 'nullable|numeric|min:0',
            'editTotalAmount' => 'required|numeric|min:0',
            'editBreakfastTime' => 'nullable',
            'editLunchTime' => 'nullable',
            'editDinnerTime' => 'nullable',
            'editMealServiceNotes' => 'nullable|string|max:2000',
        ];
        if ($this->editIsRoomComplimentary || $this->editIsMealComplimentary) {
            $rules['editComplimentaryReason'] = 'required|string|min:3|max:2000';
        }

        $this->validate($rules);
        $this->syncEditPricing();

        try {
            $this->reservation = ReservationManageService::updateReservation(
                $this->reservation,
                [
                    'guest_name' => $this->editGuestName,
                    'guest_phone' => $this->editGuestPhone,
                    'guest_email' => $this->editGuestEmail,
                    'check_in_date' => $this->editCheckInDate,
                    'check_out_date' => $this->editCheckOutDate,
                    'meal_plan' => $this->editMealPlan,
                    'room_rate_amount' => $this->editRoomRateAmount,
                    'meal_plan_supplement' => $this->editMealPlanSupplement,
                    'total_amount' => $this->editTotalAmount,
                    'breakfast_preferred_time' => $this->editBreakfastTime,
                    'lunch_preferred_time' => $this->editLunchTime,
                    'dinner_preferred_time' => $this->editDinnerTime,
                    'breakfast_in_room' => $this->editBreakfastInRoom,
                    'lunch_in_room' => $this->editLunchInRoom,
                    'dinner_in_room' => $this->editDinnerInRoom,
                    'meal_service_notes' => $this->editMealServiceNotes,
                    'is_room_complimentary' => $this->editIsRoomComplimentary,
                    'is_meal_complimentary' => $this->editIsMealComplimentary,
                    'complimentary_reason' => $this->editComplimentaryReason,
                ],
                $this->editGuestRows !== [] ? $this->editGuestRows : null,
                Auth::user(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->refreshReservationForView();
        $this->loadFormFromReservation();
        session()->flash('message', 'Reservation updated successfully.');
    }

    public function openMoveRoomModal(): void
    {
        if (! $this->canMoveRoom) {
            return;
        }
        $this->moveRoomUnitId = '';
        $this->moveRoomReason = '';
        $this->showMoveRoomModal = true;
    }

    public function closeMoveRoomModal(): void
    {
        $this->showMoveRoomModal = false;
        $this->resetValidation(['moveRoomUnitId', 'moveRoomReason']);
    }

    /** @return \Illuminate\Support\Collection<int, RoomUnit> */
    public function getMoveRoomUnitsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->reservation) {
            return collect();
        }

        $hotel = Hotel::getHotel();
        $checkIn = $this->reservation->check_in_date->format('Y-m-d');
        $checkOut = $this->reservation->check_out_date->format('Y-m-d');
        $currentIds = $this->reservation->roomUnits->pluck('id')->all();

        $bookedIds = Reservation::where('hotel_id', $hotel->id)
            ->where('id', '!=', $this->reservation->id)
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
        if ($this->reservation->room_type_id) {
            $roomQuery->where('room_type_id', $this->reservation->room_type_id);
        }

        return RoomUnit::whereIn('room_id', $roomQuery->pluck('id'))
            ->where('is_active', true)
            ->whereNotIn('id', $bookedIds)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->reject(fn (RoomUnit $u) => in_array($u->id, $currentIds, true));
    }

    public function confirmMoveRoom(): void
    {
        if (! $this->reservation || ! $this->canMoveRoom) {
            return;
        }

        $this->validate([
            'moveRoomUnitId' => 'required',
            'moveRoomReason' => 'nullable|string|max:500',
        ], [], [
            'moveRoomUnitId' => 'new room',
        ]);

        try {
            $this->reservation = ReservationManageService::moveToRoom(
                $this->reservation,
                (int) $this->moveRoomUnitId,
                trim($this->moveRoomReason) ?: null,
                Auth::user(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->closeMoveRoomModal();
        $this->refreshReservationForView();
        $this->loadFormFromReservation();
        session()->flash('message', 'Guest moved to the new room successfully.');
    }

    public function getCanEditProperty(): bool
    {
        if (! $this->reservation) {
            return false;
        }

        if (! ReservationManageService::userCanEdit(Auth::user())) {
            return false;
        }

        return ! in_array($this->reservation->status, [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT], true);
    }

    public function getCanMoveRoomProperty(): bool
    {
        if (! $this->canEdit || ! $this->reservation) {
            return false;
        }

        return in_array($this->reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN], true);
    }

    /**
     * Live accommodation receipt lines (qty = room-nights) from the form — mirrors saved folio logic.
     *
     * @return array<string, mixed>
     */
    public function getAccommodationInvoicePreviewProperty(): array
    {
        if (! $this->reservation) {
            return [];
        }

        try {
            $nights = max(1, Carbon::parse((string) $this->editCheckInDate)->startOfDay()->diffInDays(Carbon::parse((string) $this->editCheckOutDate)->startOfDay()));
        } catch (\Throwable) {
            $nights = max(1, $this->reservation->check_in_date->diffInDays($this->reservation->check_out_date));
        }

        $rooms = max(1, $this->reservation->roomUnits->count());
        $plan = MealPlan::parse((string) $this->editMealPlan);
        $roomRef = max(0, (float) preg_replace('/[^\d.]/', '', $this->editRoomRateAmount));
        $suppRef = max(0, (float) preg_replace('/[^\d.]/', '', $this->editMealPlanSupplement));
        $total = max(0, (float) preg_replace('/[^\d.]/', '', $this->editTotalAmount));

        return ReservationFolioService::buildAccommodationInvoiceTable(
            $plan,
            $roomRef,
            $suppRef,
            $this->editIsRoomComplimentary,
            $this->editIsMealComplimentary,
            $total,
            $nights,
            $rooms,
            $this->reservation->currency ?? 'RWF',
        );
    }

    public function render()
    {
        $hotel = Hotel::getHotel() ?? $this->reservation?->hotel;
        $canCollectPayment = Auth::user()?->hasPermission('fo_collect_payment') ?? false;
        $currency = $this->reservation?->currency ?? ($hotel->currency ?? 'RWF');
        $folio = $this->reservation ? ReservationFolioService::build($this->reservation) : null;
        $folioPrintUrl = $this->reservation
            ? route('front-office.reservation-folio-print', ['reservation' => $this->reservation->reservation_number])
            : '#';

        return view('livewire.front-office.reservation-details', [
            'hotel' => $hotel,
            'reservation' => $this->reservation,
            'canCollectPayment' => $canCollectPayment,
            'canEdit' => $this->canEdit,
            'canMoveRoom' => $this->canMoveRoom,
            'currency' => $currency,
            'folio' => $folio,
            'folioPrintUrl' => $folioPrintUrl,
            'accommodationInvoice' => $this->reservation
                ? ($this->canEdit
                    ? $this->accommodationInvoicePreview
                    : ReservationFolioService::buildAccommodationInvoiceTableFromReservation($this->reservation))
                : null,
        ])->layout('livewire.layouts.app-layout');
    }
}
