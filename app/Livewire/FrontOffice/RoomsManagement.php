<?php

namespace App\Livewire\FrontOffice;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoomsManagement extends Component
{
    use WithFileUploads;

    public $rooms = [];
    public $roomTypes = [];
    public $showForm = false;
    public $editingId = null;
    public $showUnitsForm = false;
    public $unitsRoomId = null;
    public $unitsRoomName = '';
    public $unitLabel = '';
    public $units = [];

    public $room_type_id = '';
    public $room_number = '';
    public $name = '';
    public $floor = '';
    /** When creating a new room, number of bookable units to create (default 1). */
    public $number_of_units = 1;
    public $is_active = true;
    /** @var array<int, int> Room amenity IDs for this room */
    public $room_amenity_ids = [];

    /** @var array Room gallery (when editing) */
    public $roomImages = [];
    public $newRoomImage;
    public $newRoomImageCaption = '';

    /** @var array<int, string> Index (per RATE_TYPES) => amount; used when charge_level is 'room' */
    public $rate_amounts = [];

    public $filterType = '';
    public $search = '';

    /** Room detail: upcoming reservations (when user opens a room) */
    public $showRoomDetailModal = false;
    public $selectedRoomId = null;
    public $selectedRoomLabel = '';
    public $roomUpcomingReservations = [];
    /** First unit id of selected room (for New reservation link) */
    public $selectedRoomFirstUnitId = null;
    /** Selected room details for modal (floor, room_number, name, type_name, rate_display, status_label, guest_name) */
    public $selectedRoomDetails = [];

    /** @var bool When true, render without layout (for embedding in Rooms tabs) */
    public $embed = false;

    /** When set, show only rooms of this type (compact list with New reservation). Empty = full table. */
    public $viewByTypeId = null;
    public $stay_check_in = '';
    public $stay_check_out = '';

    /** When true (Ireme context only), Super Admin can confirm delete / reset and delete. In hotel app this is false and delete is never shown. */
    public $allowDeleteFromIreme = false;

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->stay_check_in = now()->format('Y-m-d');
        $this->stay_check_out = now()->addDay()->format('Y-m-d');
        $this->loadRoomTypes();
        $this->loadRooms();
    }

    /** Front office can view; Manager, Director, GM (or anyone with back_office_rooms) and Super Admin can create/update/delete. */
    public function canManageRooms(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        return $user->isSuperAdmin()
            || $user->isEffectiveGeneralManager()
            || $user->canNavigateModules()
            || $user->hasPermission('back_office_rooms');
    }

    /** Only Super Admin can delete rooms that have reservations, or reset then delete. */
    public function canForceDeleteOrResetRoom(): bool
    {
        return Auth::check() && Auth::user()->isSuperAdmin();
    }

    protected function authorizeManageRooms(): void
    {
        if (! $this->canManageRooms()) {
            abort(403, 'Unauthorized. Only Manager, Director, General Manager and Super Admin can create, update, or delete rooms.');
        }
    }

    public function loadRoomTypes()
    {
        $hotel = Hotel::getHotel();
        $this->roomTypes = RoomType::where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function loadRooms()
    {
        $hotel = Hotel::getHotel();
        $query = Room::where('hotel_id', $hotel->id)->with(['roomType', 'roomUnits', 'images', 'amenities']);

        if ($this->filterType) {
            $query->where('room_type_id', $this->filterType);
        }
        if ($this->viewByTypeId) {
            $query->where('room_type_id', $this->viewByTypeId);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('floor', 'like', '%' . $this->search . '%')
                    ->orWhere('room_number', 'like', '%' . $this->search . '%');
            });
        }

        $rooms = $query->with(['roomType.rates', 'rates', 'wing'])->orderBy('name')->get();
        $today = now()->format('Y-m-d');
        $periodCheckIn = $this->stay_check_in;
        $periodCheckOut = $this->stay_check_out;
        $hasValidRange = $periodCheckIn && $periodCheckOut && $periodCheckIn < $periodCheckOut;
        $this->rooms = $rooms->map(function ($room) use ($today) {
            $arr = $room->toArray();
            $arr['wing_id'] = $room->wing_id;
            $unitIds = $room->roomUnits->pluck('id')->all();
            $arr['has_reservations'] = $room->hasReservations();
            $arr['pending_deletion'] = (bool) ($room->pending_deletion ?? false);
            $arr['wing_name'] = $room->wing?->name;
            $arr['stay_reservation_id'] = null;
            $arr['stay_check_in'] = null;
            $arr['stay_check_out'] = null;
            $arr['period_block_reservation_id'] = null;
            $arr['period_block_guest_name'] = null;
            $arr['period_block_check_in'] = null;
            $arr['period_block_check_out'] = null;
            if (empty($unitIds)) {
                $arr['availability_status'] = 'vacant';
                $arr['current_guest_name'] = null;
                $arr['selected_period_blocked'] = false;
                $arr['selected_period_reason'] = null;
                return $arr;
            }
            $current = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                ->where('check_in_date', '<=', $today)
                ->where('check_out_date', '>=', $today)
                ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                ->first();
            if ($current) {
                $arr['availability_status'] = 'occupied';
                $arr['current_guest_name'] = $current->guest_name;
                $arr['stay_reservation_id'] = $current->id;
                $arr['stay_check_in'] = $current->check_in_date?->format('Y-m-d');
                $arr['stay_check_out'] = $current->check_out_date?->format('Y-m-d');
            } else {
                $dueOut = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                    ->where('check_out_date', $today)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
                    ->first();
                if ($dueOut) {
                    $arr['availability_status'] = 'due_out';
                    $arr['current_guest_name'] = $dueOut->guest_name;
                    $arr['stay_reservation_id'] = $dueOut->id;
                    $arr['stay_check_in'] = $dueOut->check_in_date?->format('Y-m-d');
                    $arr['stay_check_out'] = $dueOut->check_out_date?->format('Y-m-d');
                } else {
                    $arr['availability_status'] = 'vacant';
                    $arr['current_guest_name'] = null;
                }
            }
            $arr['selected_period_blocked'] = false;
            $arr['selected_period_reason'] = null;
            return $arr;
        })->toArray();

        if ($hasValidRange) {
            $this->rooms = collect($this->rooms)->map(function (array $room) use ($periodCheckIn, $periodCheckOut, $today) {
                $unitIds = collect($room['room_units'] ?? [])->pluck('id')->filter()->values()->all();
                if (empty($unitIds)) {
                    return $room;
                }
                $overlap = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                    ->where('check_out_date', '>', $periodCheckIn)
                    ->where('check_in_date', '<', $periodCheckOut)
                    ->orderBy('check_in_date')
                    ->first();
                if (! $overlap) {
                    return $room;
                }
                $room['selected_period_blocked'] = true;
                $occupiedToday = $overlap->check_in_date->format('Y-m-d') <= $today
                    && $overlap->check_out_date->format('Y-m-d') >= $today;
                $room['selected_period_reason'] = $occupiedToday ? 'occupied' : 'reserved';
                $room['period_block_reservation_id'] = $overlap->id;
                $room['period_block_guest_name'] = $overlap->guest_name;
                $room['period_block_check_in'] = $overlap->check_in_date?->format('Y-m-d');
                $room['period_block_check_out'] = $overlap->check_out_date?->format('Y-m-d');
                return $room;
            })->values()->all();
        }
    }

    public function openRoomDetail(int $roomId): void
    {
        $room = Room::with(['roomType', 'roomUnits', 'roomType.rates', 'rates'])->findOrFail($roomId);
        $this->selectedRoomId = $roomId;
        $this->selectedRoomLabel = ($room->room_number ?: $room->name) . ' — ' . ($room->roomType->name ?? '');
        $this->selectedRoomFirstUnitId = $room->roomUnits->isNotEmpty() ? $room->roomUnits->first()->id : null;

        $rates = $this->getChargeLevelIsRoomProperty() && $room->rates->isNotEmpty()
            ? $room->rates
            : ($room->roomType->rates ?? collect());
        $locals = $rates->firstWhere('rate_type', 'Locals');
        $fromAmount = $locals ? (float) $locals->amount : null;
        if ($fromAmount === null && $rates->isNotEmpty()) {
            $fromAmount = (float) $rates->min('amount');
        }
        $currency = Hotel::getHotel()->currency ?? 'RWF';
        $rateDisplay = $fromAmount !== null ? number_format($fromAmount) . ' ' . $currency : '—';

        $unitIds = $room->roomUnits->pluck('id')->all();
        $today = now()->format('Y-m-d');
        $statusLabel = 'Vacant';
        $guestName = null;
        if (! empty($unitIds)) {
            $current = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                ->where('check_in_date', '<=', $today)
                ->where('check_out_date', '>=', $today)
                ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                ->first();
            if ($current) {
                $statusLabel = 'Occupied';
                $guestName = $current->guest_name;
            } else {
                $dueOut = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                    ->where('check_out_date', $today)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
                    ->first();
                if ($dueOut) {
                    $statusLabel = 'Due out';
                    $guestName = $dueOut->guest_name;
                }
            }
        }

        $this->selectedRoomDetails = [
            'floor' => $room->floor ?? '—',
            'room_number' => $room->room_number ?? $room->name,
            'name' => $room->name,
            'type_name' => $room->roomType->name ?? '—',
            'units_count' => $room->roomUnits->count(),
            'rate_display' => $rateDisplay,
            'status_label' => $statusLabel,
            'guest_name' => $guestName,
        ];

        if (empty($unitIds)) {
            $this->roomUpcomingReservations = [];
        } else {
            $this->roomUpcomingReservations = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                ->where('check_out_date', '>=', $today)
                ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
                ->orderBy('check_in_date')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'reservation_number' => $r->reservation_number,
                    'guest_name' => $r->guest_name,
                    'check_in_date' => $r->check_in_date?->format('Y-m-d'),
                    'check_out_date' => $r->check_out_date?->format('Y-m-d'),
                    'status' => $r->status,
                    'business_source' => $r->business_source ?? '—',
                ])
                ->toArray();
        }
        $this->showRoomDetailModal = true;
    }

    public function closeRoomDetail(): void
    {
        $this->showRoomDetailModal = false;
        $this->selectedRoomId = null;
        $this->selectedRoomLabel = '';
        $this->selectedRoomFirstUnitId = null;
        $this->selectedRoomDetails = [];
        $this->roomUpcomingReservations = [];
    }

    /** Return room types with vacant (remaining) room count for the current hotel. */
    public function getRoomTypesWithRemainingCount(): array
    {
        $hotel = Hotel::getHotel();
        $today = now()->format('Y-m-d');
        $types = RoomType::where('hotel_id', $hotel->id)->where('is_active', true)->orderBy('name')->get();
        $result = [];
        foreach ($types as $type) {
            $rooms = Room::where('hotel_id', $hotel->id)->where('room_type_id', $type->id)->where('is_active', true)->with('roomUnits')->get();
            $vacant = 0;
            foreach ($rooms as $room) {
                $unitIds = $room->roomUnits->pluck('id')->all();
                if (empty($unitIds)) {
                    $vacant++;
                    continue;
                }
                $occupied = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                    ->where('check_in_date', '<=', $today)
                    ->where('check_out_date', '>=', $today)
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                    ->exists();
                if (! $occupied) {
                    $vacant++;
                }
            }
            $result[] = [
                'id' => $type->id,
                'name' => $type->name,
                'total' => $rooms->count(),
                'remaining' => $vacant,
            ];
        }
        return $result;
    }

    /** Open the "by type" view showing only rooms of this type (floor + room + New reservation). */
    public function openByType(int $roomTypeId): void
    {
        $this->viewByTypeId = $roomTypeId;
        $this->filterType = (string) $roomTypeId;
        $this->loadRooms();
    }

    /** Return to full rooms list. */
    public function clearViewByType(): void
    {
        $this->viewByTypeId = null;
        $this->filterType = '';
        $this->loadRooms();
    }

    /** First room unit id for a room (for "New reservation" link). */
    public function getFirstUnitIdForRoom(array $room): ?int
    {
        $units = $room['room_units'] ?? [];
        if (empty($units)) {
            return null;
        }
        $first = $units[0] ?? null;
        if ($first === null) {
            return null;
        }
        $id = is_array($first) ? ($first['id'] ?? null) : ($first->id ?? null);
        return $id !== null ? (int) $id : null;
    }

    /** Use multi-column layout when hotel has wings configured. */
    public function useWingColumnLayout(): bool
    {
        $hotel = Hotel::getHotel();
        if (! ($hotel->has_multiple_wings ?? false)) {
            return false;
        }

        return $hotel->wings()->where('is_active', true)->exists();
    }

    /**
     * Rooms grouped by wing for column UI (empty wings still get a column).
     *
     * @return array<int, array{wing_id: int|null, name: string, code: ?string, rooms: array, show_header: bool}>
     */
    public function getWingColumnsForRoomView(): array
    {
        $hotel = Hotel::getHotel();
        $rooms = $this->rooms;
        if (! $this->useWingColumnLayout()) {
            return [['wing_id' => null, 'name' => '', 'code' => null, 'rooms' => $rooms, 'show_header' => false]];
        }

        $wings = $hotel->wings()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $grouped = collect($rooms)->groupBy(function (array $r) {
            $id = $r['wing_id'] ?? null;

            return $id !== null ? (string) $id : 'none';
        });

        $columns = [];
        foreach ($wings as $w) {
            $columns[] = [
                'wing_id' => (int) $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'rooms' => $grouped->get((string) $w->id, collect())->values()->all(),
                'show_header' => true,
            ];
        }
        $unassigned = $grouped->get('none', collect())->values()->all();
        if (count($unassigned) > 0) {
            $columns[] = [
                'wing_id' => null,
                'name' => 'Unassigned',
                'code' => null,
                'rooms' => $unassigned,
                'show_header' => true,
            ];
        }

        // If this room category effectively lives in only one wing, use single-column UX.
        $nonEmptyColumns = array_values(array_filter($columns, fn ($c) => count($c['rooms'] ?? []) > 0));
        if (count($nonEmptyColumns) <= 1) {
            return [['wing_id' => null, 'name' => '', 'code' => null, 'rooms' => $rooms, 'show_header' => false]];
        }

        return $columns;
    }

    /** True only when the selected room set is distributed across multiple wing buckets. */
    public function shouldRenderWingColumns(): bool
    {
        $cols = $this->getWingColumnsForRoomView();
        $nonEmpty = array_values(array_filter($cols, fn ($c) => count($c['rooms'] ?? []) > 0));
        $named = array_values(array_filter($nonEmpty, fn ($c) => (bool) ($c['show_header'] ?? false)));

        return count($named) > 1;
    }

    public function openForm($id = null)
    {
        $this->authorizeManageRooms();
        $this->editingId = $id;
        if ($id) {
            $room = Room::with(['images', 'amenities', 'rates'])->findOrFail($id);
            $this->room_type_id = (string) $room->room_type_id;
            $this->room_number = $room->room_number ?? '';
            $this->name = $room->name;
            $this->floor = $room->floor ?? '';
            $this->is_active = $room->is_active;
            $this->room_amenity_ids = $room->amenities->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
            $this->roomImages = $room->images->toArray();
            $this->rate_amounts = $this->getDefaultRateAmounts();
            if ($this->getChargeLevelIsRoomProperty()) {
                foreach (AddReservation::RATE_TYPES as $idx => $rt) {
                    $this->rate_amounts[$idx] = (string) ($room->getAmountForRateType($rt) ?? '');
                }
            }
        } else {
            $this->resetForm();
        }
        $this->newRoomImage = null;
        $this->newRoomImageCaption = '';
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->room_type_id = $this->roomTypes->isNotEmpty() ? (string) $this->roomTypes->first()->id : '';
        $this->room_number = '';
        $this->name = '';
        $this->floor = '';
        $this->number_of_units = 1;
        $this->is_active = true;
        $this->room_amenity_ids = [];
        $this->roomImages = [];
        $this->rate_amounts = $this->getDefaultRateAmounts();
    }

    /** Whether hotel charges by room (not by room type). */
    public function getChargeLevelIsRoomProperty(): bool
    {
        return (Hotel::getHotel()->charge_level ?? 'room_type') === 'room';
    }

    /** Default empty rate amounts keyed by index for form binding. */
    protected function getDefaultRateAmounts(): array
    {
        return array_fill(0, count(AddReservation::RATE_TYPES), '');
    }

    public function save()
    {
        $this->authorizeManageRooms();
        $this->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'nullable|string|max:50',
            'name' => 'required|string|max:100',
            'floor' => 'nullable|string|max:50',
            'number_of_units' => 'nullable|integer|min:1|max:50',
            'is_active' => 'boolean',
        ]);

        $hotel = Hotel::getHotel();
        $data = [
            'hotel_id' => $hotel->id,
            'room_type_id' => $this->room_type_id,
            'room_number' => $this->room_number ?: null,
            'name' => $this->name,
            'floor' => $this->floor ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $room = Room::findOrFail($this->editingId);
            $room->update($data);
            $room->amenities()->sync(array_map('intval', $this->room_amenity_ids ?: []));
            if ($this->getChargeLevelIsRoomProperty()) {
                $this->syncRoomRates($room);
            }
            session()->flash('message', 'Room updated successfully.');
            $this->loadRooms();
            $this->closeForm();
        } else {
            $room = Room::create($data);
            $room->amenities()->sync(array_map('intval', $this->room_amenity_ids ?: []));
            if ($this->getChargeLevelIsRoomProperty()) {
                $this->syncRoomRates($room);
            }
            $numUnits = (int) ($this->number_of_units ?? 1);
            $numUnits = max(1, min(50, $numUnits));
            for ($i = 0; $i < $numUnits; $i++) {
                RoomUnit::create([
                    'room_id' => $room->id,
                    'label' => $numUnits > 1 ? $this->name . ' (' . ($i + 1) . ')' : $this->name,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
            session()->flash('message', 'Room created. Add gallery images below or close.');
            $this->editingId = $room->id;
            $this->roomImages = [];
            $this->loadRooms();
        }
    }

    protected function syncRoomRates(Room $room): void
    {
        foreach (AddReservation::RATE_TYPES as $idx => $rt) {
            $amount = isset($this->rate_amounts[$idx]) && $this->rate_amounts[$idx] !== '' && is_numeric($this->rate_amounts[$idx])
                ? (float) $this->rate_amounts[$idx]
                : null;
            if ($amount !== null && $amount >= 0) {
                RoomRate::updateOrCreate(
                    ['room_id' => $room->id, 'rate_type' => $rt],
                    ['amount' => $amount]
                );
            } else {
                RoomRate::where('room_id', $room->id)->where('rate_type', $rt)->delete();
            }
        }
    }

    public function deleteRoom($id)
    {
        $this->authorizeManageRooms();
        $room = Room::with('roomUnits')->findOrFail($id);

        // Deletion is only allowed from Ireme by Super Admin. In hotel app we never delete.
        if (! $this->allowDeleteFromIreme || ! $this->canForceDeleteOrResetRoom()) {
            if ($room->hasReservations()) {
                $this->removeFromUse($id);
                return;
            }
            if ($room->pending_deletion) {
                session()->flash('error', 'This room is pending deletion. Only Super Admin from Ireme can confirm the delete.');
                return;
            }
            session()->flash('error', 'Rooms cannot be deleted from the hotel app. Use "Remove from use" to mark for deletion; Super Admin from Ireme can confirm.');
            return;
        }

        if ($room->hasReservations()) {
            session()->flash('error', 'Room has reservations. Use "Reset and delete" to unlink it from all reservations first, then delete.');
            return;
        }

        if ($room->pending_deletion && ! $this->canForceDeleteOrResetRoom()) {
            session()->flash('error', 'This room is pending deletion. Only Super Admin can confirm the delete.');
            return;
        }

        $room->delete();
        session()->flash('message', 'Room deleted.');
        $this->loadRooms();
    }

    /** Manager/Director/GM: remove room from use (deactivate and mark pending deletion). Only Super Admin from Ireme can then confirm delete. */
    public function removeFromUse($id): void
    {
        $this->authorizeManageRooms();
        $room = Room::findOrFail($id);
        $room->update([
            'is_active' => false,
            'pending_deletion' => true,
            'deletion_requested_at' => now(),
        ]);
        session()->flash('message', $room->hasReservations()
            ? 'Room removed from use. Only Super Admin from Ireme can confirm deletion after clearing reservations.'
            : 'Room removed from use. Only Super Admin from Ireme can confirm deletion.');
        $this->loadRooms();
    }

    /** Super Admin only (and only from Ireme context): unlink room from all reservations, then delete the room and its units. */
    public function resetAndDeleteRoom($id): void
    {
        if (! $this->allowDeleteFromIreme || ! $this->canForceDeleteOrResetRoom()) {
            abort(403, 'Only Super Admin from Ireme can reset and delete rooms that have reservations.');
        }
        $room = Room::with('roomUnits')->findOrFail($id);
        $unitIds = $room->roomUnits->pluck('id')->all();
        if (! empty($unitIds)) {
            \Illuminate\Support\Facades\DB::table('reservation_room_unit')
                ->whereIn('room_unit_id', $unitIds)
                ->delete();
        }
        $room->delete();
        session()->flash('message', 'Room unlinked from reservations and deleted.');
        $this->loadRooms();
    }

    public function toggleActive($id)
    {
        $this->authorizeManageRooms();
        $room = Room::findOrFail($id);
        $room->is_active = ! $room->is_active;
        $room->save();
        $this->loadRooms();
    }

    public function openUnitsForm($roomId)
    {
        $this->authorizeManageRooms();
        $room = Room::with('roomUnits')->findOrFail($roomId);
        $this->unitsRoomId = $room->id;
        $this->unitsRoomName = $room->name . ' (' . ($room->roomType->name ?? '') . ')';
        $this->units = $room->roomUnits->toArray();
        $this->unitLabel = '';
        $this->showUnitsForm = true;
    }

    public function closeUnitsForm()
    {
        $this->showUnitsForm = false;
        $this->unitsRoomId = null;
        $this->unitsRoomName = '';
        $this->units = [];
        $this->unitLabel = '';
        $this->loadRooms();
    }

    public function addUnit()
    {
        $this->authorizeManageRooms();
        $this->validate(['unitLabel' => 'required|string|max:255']);
        $room = Room::findOrFail($this->unitsRoomId);
        $maxOrder = $room->roomUnits()->max('sort_order') ?? 0;
        RoomUnit::create([
            'room_id' => $this->unitsRoomId,
            'label' => $this->unitLabel,
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);
        $this->unitLabel = '';
        $room->load('roomUnits');
        $this->units = $room->roomUnits->toArray();
        session()->flash('message', 'Unit added.');
    }

    public function removeUnit($unitId)
    {
        $this->authorizeManageRooms();
        RoomUnit::where('id', $unitId)->where('room_id', $this->unitsRoomId)->delete();
        $room = Room::findOrFail($this->unitsRoomId);
        $room->load('roomUnits');
        $this->units = $room->roomUnits->toArray();
        session()->flash('message', 'Unit removed.');
    }

    public function updatedFilterType()
    {
        $this->loadRooms();
    }

    public function updatedSearch()
    {
        $this->loadRooms();
    }

    public function updatedStayCheckIn(): void
    {
        $this->loadRooms();
    }

    public function updatedStayCheckOut(): void
    {
        $this->loadRooms();
    }

    /** Default landing table: room types with all/vacant/occupied + from rate. */
    public function getRoomTypeSummaryRows(): array
    {
        $hotel = Hotel::getHotel();
        $types = RoomType::where('hotel_id', $hotel->id)->where('is_active', true)->with(['rates'])->orderBy('name')->get();
        $today = now()->format('Y-m-d');
        $currency = $hotel->currency ?? 'RWF';
        $rows = [];

        foreach ($types as $type) {
            $rooms = Room::where('hotel_id', $hotel->id)
                ->where('room_type_id', $type->id)
                ->where('is_active', true)
                ->with('roomUnits')
                ->get();
            $all = $rooms->count();
            $occupied = 0;
            foreach ($rooms as $room) {
                $unitIds = $room->roomUnits->pluck('id')->all();
                if (empty($unitIds)) {
                    continue;
                }
                $isOccupied = Reservation::whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                    ->where('check_in_date', '<=', $today)
                    ->where('check_out_date', '>=', $today)
                    ->exists();
                if ($isOccupied) {
                    $occupied++;
                }
            }
            $vacant = max(0, $all - $occupied);

            $locals = $type->rates->firstWhere('rate_type', 'Locals');
            $fromAmount = $locals ? (float) $locals->amount : null;
            if ($fromAmount === null && $type->rates->isNotEmpty()) {
                $fromAmount = (float) $type->rates->min('amount');
            }

            $rows[] = [
                'id' => $type->id,
                'name' => $type->name,
                'all' => $all,
                'vacant' => $vacant,
                'occupied' => $occupied,
                'rate_display' => $fromAmount !== null ? number_format($fromAmount) . ' ' . $currency : '—',
            ];
        }

        return $rows;
    }

    public function addRoomImage(): void
    {
        $this->authorizeManageRooms();
        $this->validate([
            'newRoomImage' => 'required|image|max:4096',
            'newRoomImageCaption' => 'nullable|string|max:255',
        ]);
        if (! $this->editingId) {
            return;
        }
        $path = $this->newRoomImage->store('room-images', 'public');
        $maxOrder = RoomImage::where('room_id', $this->editingId)->max('sort_order') ?? 0;
        RoomImage::create([
            'room_id' => $this->editingId,
            'path' => $path,
            'caption' => $this->newRoomImageCaption ?: null,
            'sort_order' => $maxOrder + 1,
        ]);
        $this->newRoomImage = null;
        $this->newRoomImageCaption = '';
        $room = Room::with('images')->findOrFail($this->editingId);
        $this->roomImages = $room->images->toArray();
        session()->flash('message', 'Image added.');
    }

    public function removeRoomImage(int $imageId): void
    {
        $this->authorizeManageRooms();
        if (! $this->editingId) {
            return;
        }
        $img = RoomImage::where('room_id', $this->editingId)->findOrFail($imageId);
        Storage::disk('public')->delete($img->path);
        $img->delete();
        $room = Room::with('images')->findOrFail($this->editingId);
        $this->roomImages = $room->images->toArray();
        session()->flash('message', 'Image removed.');
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $roomAmenities = Amenity::where('hotel_id', $hotel->id)
            ->where('type', Amenity::TYPE_ROOM)
            ->orderBy('sort_order')
            ->get();

        $view = view('livewire.front-office.rooms-management', [
            'roomAmenities' => $roomAmenities,
            'roomTypesWithRemaining' => $this->getRoomTypesWithRemainingCount(),
        ]);
        return $this->embed ? $view : $view->layout('livewire.layouts.app-layout');
    }
}
