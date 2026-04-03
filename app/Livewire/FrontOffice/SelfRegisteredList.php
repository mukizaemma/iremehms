<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\PreRegistration;
use App\Models\Reservation;
use App\Models\RoomUnit;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * List of self-registered guests. Filter by status/reservation; assign guest to room.
 */
class SelfRegisteredList extends Component
{
    use ChecksModuleStatus;

    public string $statusFilter = '';
    public string $reservationFilter = '';
    public string $groupFilter = '';
    public ?int $assigningId = null;
    public string $assignRoomUnitId = '';
    public string $assignCheckInDate = '';
    public string $assignCheckOutDate = '';
    public ?int $editingId = null;
    public string $edit_guest_name = '';
    public string $edit_guest_id_number = '';
    public string $edit_guest_country = '';
    public string $edit_guest_email = '';
    public string $edit_guest_phone = '';
    public string $edit_guest_profession = '';
    public string $edit_guest_stay_purpose = '';
    public string $edit_organization = '';
    public string $edit_private_notes = '';
    public string $search = '';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        // Allow deep-linking from calendar / group sidebar: ?reservation=<id>
        if (request()->filled('reservation')) {
            $this->reservationFilter = (string) request()->get('reservation');
        }
    }

    public function openAssign(int $id): void
    {
        $this->assigningId = $id;
        $this->assignRoomUnitId = '';
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->with('reservation')->find($id);
        // Default dates: use linked reservation dates when present, otherwise today / +1 night.
        if ($pre && $pre->reservation) {
            $this->assignCheckInDate = $pre->reservation->check_in_date?->format('Y-m-d') ?? Hotel::getTodayForHotel();
            $this->assignCheckOutDate = $pre->reservation->check_out_date?->format('Y-m-d') ?? now()->addDay()->format('Y-m-d');
        } else {
            $today = Hotel::getTodayForHotel();
            $this->assignCheckInDate = $today;
            $this->assignCheckOutDate = now()->addDay()->format('Y-m-d');
        }
    }

    public function cancelAssign(): void
    {
        $this->assigningId = null;
        $this->assignRoomUnitId = '';
        $this->assignCheckInDate = '';
        $this->assignCheckOutDate = '';
    }

    public function saveAssign()
    {
        $this->validate([
            'assignRoomUnitId' => 'required|exists:room_units,id',
            'assignCheckInDate' => 'required|date|after_or_equal:' . now()->toDateString(),
            'assignCheckOutDate' => 'required|date|after:assignCheckInDate',
        ], [], [
            'assignRoomUnitId' => 'Room',
            'assignCheckInDate' => 'Check-in date',
            'assignCheckOutDate' => 'Check-out date',
        ]);
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->with('reservation.roomUnits')->find($this->assigningId);
        if (! $pre) {
            $this->cancelAssign();
            return;
        }
        $roomUnit = RoomUnit::where('id', $this->assignRoomUnitId)->whereHas('room', fn ($q) => $q->where('hotel_id', $hotel->id))->first();
        if (! $roomUnit) {
            $this->addError('assignRoomUnitId', 'Invalid room.');
            return;
        }

        $pre->update(['room_unit_id' => $roomUnit->id, 'status' => PreRegistration::STATUS_ASSIGNED]);
        // If this pre-reg is linked to a reservation and we assigned one of that reservation's rooms, just confirm (verify & assign). No need to open full reservation form.
        $isReservedRoom = $pre->reservation_id && $pre->reservation && $pre->reservation->roomUnits->contains('id', $roomUnit->id);
        if ($isReservedRoom) {
            $this->cancelAssign();
            session()->flash('message', 'Guest assigned to room ' . $roomUnit->label . '. You can verify details above or use Check in when ready.');
            return;
        }

        // Otherwise send to reservation form to complete/confirm (e.g. new reservation or different room).
        $this->cancelAssign();
        return $this->redirect(route('front-office.add-reservation', [
            'pre_registration' => $pre->id,
            'room_unit_id' => $roomUnit->id,
            'check_in' => $this->assignCheckInDate,
            'check_out' => $this->assignCheckOutDate,
        ]));
    }

    public function markCheckedIn(int $id)
    {
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->find($id);
        if ($pre) {
            // When checking in from pre-arrival list, continue on the reservation form
            // with guest details prefilled.
            $params = ['pre_registration' => $pre->id];
            if ($pre->room_unit_id) {
                $params['room_unit_id'] = $pre->room_unit_id;
            }

            return $this->redirect(route('front-office.add-reservation', $params));
        }
    }

    public function openEdit(int $id): void
    {
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->find($id);
        if (! $pre) {
            return;
        }
        $this->editingId = $id;
        $this->edit_guest_name = $pre->guest_name ?? '';
        $this->edit_guest_id_number = $pre->guest_id_number ?? '';
        $this->edit_guest_country = $pre->guest_country ?? '';
        $this->edit_guest_email = $pre->guest_email ?? '';
        $this->edit_guest_phone = $pre->guest_phone ?? '';
        $this->edit_guest_profession = $pre->guest_profession ?? '';
        $this->edit_guest_stay_purpose = $pre->guest_stay_purpose ?? '';
        $this->edit_organization = $pre->organization ?? '';
        $this->edit_private_notes = $pre->private_notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'edit_guest_name' => 'required|string|min:2|max:255',
            'edit_guest_id_number' => 'nullable|string|max:100',
            'edit_guest_country' => 'nullable|string|max:100',
            'edit_guest_email' => 'nullable|email|max:255',
            'edit_guest_phone' => 'nullable|string|max:50',
            'edit_guest_profession' => 'nullable|string|max:100',
            'edit_guest_stay_purpose' => 'nullable|string|max:100',
            'edit_organization' => 'nullable|string|max:200',
            'edit_private_notes' => 'nullable|string|max:2000',
        ]);
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->find($this->editingId);
        if (! $pre) {
            $this->cancelEdit();
            return;
        }
        $pre->update([
            'guest_name' => $this->edit_guest_name,
            'guest_id_number' => $this->edit_guest_id_number ?: null,
            'guest_country' => $this->edit_guest_country ?: null,
            'guest_email' => $this->edit_guest_email ?: null,
            'guest_phone' => $this->edit_guest_phone ?: null,
            'guest_profession' => $this->edit_guest_profession ?: null,
            'guest_stay_purpose' => $this->edit_guest_stay_purpose ?: null,
            'organization' => $this->edit_organization ?: null,
            'private_notes' => $this->edit_private_notes ?: null,
        ]);
        $this->cancelEdit();
        session()->flash('message', 'Pre-arrival information updated.');
    }

    public function getPreRegistrationsProperty()
    {
        $hotel = Hotel::getHotel();
        $q = PreRegistration::where('hotel_id', $hotel->id)->with(['reservation', 'roomUnit.room']);
        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }
        if ($this->reservationFilter !== '') {
            $q->where('reservation_id', $this->reservationFilter);
        }
        if ($this->groupFilter !== '') {
            $q->where('group_identifier', $this->groupFilter);
        }
        if ($this->search !== '') {
            $q->where(function ($q) {
                $q->where('guest_name', 'like', '%' . $this->search . '%')
                    ->orWhere('guest_id_number', 'like', '%' . $this->search . '%')
                    ->orWhere('reservation_reference', 'like', '%' . $this->search . '%');
            });
        }
        return $q->orderByDesc('submitted_at')->get();
    }

    public function getReservationsWithGroupsProperty()
    {
        $hotel = Hotel::getHotel();
        return Reservation::where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->where(function ($q) {
                $q->whereNotNull('group_name')->where('group_name', '!=', '');
            })
            ->orderByDesc('check_in_date')
            ->limit(100)
            ->get(['id', 'reservation_number', 'group_name', 'check_in_date']);
    }

    public function getGroupIdentifiersProperty()
    {
        $hotel = Hotel::getHotel();
        return PreRegistration::where('hotel_id', $hotel->id)
            ->whereNotNull('group_identifier')
            ->where('group_identifier', '!=', '')
            ->distinct()
            ->pluck('group_identifier')
            ->take(50);
    }

    public function getRoomUnitsProperty()
    {
        $hotel = Hotel::getHotel();
        return RoomUnit::with('room')
            ->whereHas('room', fn ($q) => $q->where('hotel_id', $hotel->id)->where('is_active', true))
            ->orderBy('label')
            ->get();
    }

    /** When assigning a pre-reg that has a reservation, return that reservation's room units (so staff can assign to reserved rooms only or also pick other). */
    public function getReservedRoomUnitsForAssignProperty()
    {
        if (! $this->assigningId) {
            return collect();
        }
        $hotel = Hotel::getHotel();
        $pre = PreRegistration::where('hotel_id', $hotel->id)->with('reservation.roomUnits.room')->find($this->assigningId);
        if (! $pre || ! $pre->reservation_id || ! $pre->reservation) {
            return collect();
        }
        return $pre->reservation->roomUnits->filter(function ($u) use ($hotel) {
            return $u->room && $u->room->hotel_id == $hotel->id;
        })->sortBy('label')->values();
    }

    /** Other room units (not in the pre-reg's reservation) for "assign to different room" option. */
    public function getOtherRoomUnitsForAssignProperty()
    {
        $reservedIds = $this->reservedRoomUnitsForAssign->pluck('id')->all();
        $all = $this->roomUnits;
        if (empty($reservedIds)) {
            return $all;
        }
        return $all->whereNotIn('id', $reservedIds)->values();
    }

    public function render()
    {
        return view('livewire.front-office.self-registered-list', [
            'preRegistrations' => $this->preRegistrations,
            'reservationsWithGroups' => $this->reservationsWithGroups,
            'groupIdentifiers' => $this->groupIdentifiers,
            'roomUnits' => $this->roomUnits,
        ])->layout('livewire.layouts.app-layout');
    }
}
