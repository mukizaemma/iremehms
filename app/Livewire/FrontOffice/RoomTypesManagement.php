<?php

namespace App\Livewire\FrontOffice;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\HotelWing;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use App\Models\RoomTypeRate;
use App\Models\RoomUnit;
use App\Models\RoomRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoomTypesManagement extends Component
{
    use WithFileUploads;

    public $types = [];
    public $showForm = false;
    public $editingId = null;

    public $name = '';
    public $slug = '';
    public $description = '';
    public $is_active = true;
    public $max_adults = 2;
    public $max_children = 0;
    /** @var array<int, int> Room amenity IDs selected for this room type */
    public $room_amenity_ids = [];
    /** @var array<int, string> Index (per RATE_TYPES) => amount for form binding */
    public $rate_amounts = [];

    public $search = '';

    /** @var bool When true, render without layout (for embedding in Rooms tabs) */
    public $embed = false;

    /** @var bool */
    public $showImagesModal = false;
    /** @var int|null */
    public $imagesForTypeId = null;
    /** @var string */
    public $imagesForTypeName = '';
    /** @var array */
    public $typeImages = [];
    public $newImage;
    public $newImageCaption = '';

    /** Manage rooms for a room type */
    public $showRoomsModal = false;
    public $roomsForTypeId = null;
    public $roomsForTypeName = '';
    public $roomsList = [];
    public $room_number = '';
    public $room_description = '';
    /** @var array<int, string> Optional price per rate type when charge_level is 'room' */
    public $room_rate_amounts = [];


    /** Hotel physical layout config */
    public $has_multiple_wings = false;
    public $total_floors = 1;
    public $wings = [];
    public $new_wing_name = '';
    public $new_wing_code = '';

    /** Room placement for add/bulk */
    public $room_wing_id = null;
    public $room_floor = 1;
    public $bulk_count = 1;
    public $bulk_prefix = '';
    public $bulk_start_number = 1;
    public $bulk_digits = 3;
    public $assign_wing_id = [];
    public $assign_floor = [];

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->authorizeRoomManagement();
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    protected function authorizeRoomManagement(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isSuperAdmin()
            || $user->isEffectiveGeneralManager()
            || $user->canNavigateModules()
            || $user->hasPermission('back_office_rooms')) {
            return;
        }
        abort(403, 'Unauthorized. Only Manager, Director, GM or Super Admin can manage room types.');
    }


    protected function loadLayoutConfig(): void
    {
        $hotel = Hotel::getHotel();
        $this->has_multiple_wings = (bool) ($hotel->has_multiple_wings ?? false);
        $this->total_floors = max(1, (int) ($hotel->total_floors ?? 1));
        $this->wings = $hotel->wings()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->toArray();
    }

    public function saveLayoutConfig(): void
    {
        $hotel = Hotel::getHotel();
        $this->validate([
            'has_multiple_wings' => 'boolean',
            'total_floors' => 'required|integer|min:1|max:120',
        ]);
        $hotel->has_multiple_wings = (bool) $this->has_multiple_wings;
        $hotel->total_floors = (int) $this->total_floors;
        $hotel->save();
        $this->loadLayoutConfig();
        session()->flash('message', 'Layout configuration saved.');
    }

    public function addWing(): void
    {
        $hotel = Hotel::getHotel();
        $this->validate([
            'new_wing_name' => 'required|string|max:120|unique:hotel_wings,name,NULL,id,hotel_id,' . $hotel->id,
            'new_wing_code' => 'nullable|string|max:20',
        ]);
        HotelWing::create([
            'hotel_id' => $hotel->id,
            'name' => trim((string) $this->new_wing_name),
            'code' => $this->new_wing_code !== '' ? trim((string) $this->new_wing_code) : null,
            'sort_order' => (int) ($hotel->wings()->max('sort_order') ?? 0) + 1,
            'is_active' => true,
        ]);
        $this->new_wing_name = '';
        $this->new_wing_code = '';
        $this->loadLayoutConfig();
        session()->flash('message', 'Wing added.');
    }

    public function removeWing(int $wingId): void
    {
        $hotel = Hotel::getHotel();
        $wing = HotelWing::where('hotel_id', $hotel->id)->findOrFail($wingId);
        if ($wing->rooms()->exists()) {
            session()->flash('error', 'Cannot remove wing with rooms assigned.');
            return;
        }
        $wing->delete();
        $this->loadLayoutConfig();
        session()->flash('message', 'Wing removed.');
    }

    public function loadTypes()
    {
        $hotel = Hotel::getHotel();

        $query = RoomType::where('hotel_id', $hotel->id)->withCount('rooms');
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%');
            });
        }
        $this->types = $query->with(['images', 'rates'])->orderBy('name')->get()->toArray();
    }

    public function openForm($id = null)
    {
        $this->editingId = $id;
        if ($id) {
            $type = RoomType::with(['amenities', 'rates'])->findOrFail($id);
            $this->name = $type->name;
            $this->slug = $type->slug ?? '';
            $this->description = $type->description ?? '';
            $this->is_active = $type->is_active;
            $this->max_adults = (int) ($type->max_adults ?? 2);
            $this->max_children = (int) ($type->max_children ?? 0);
            $this->room_amenity_ids = $type->amenities->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
            $this->rate_amounts = [];
            foreach (AddReservation::RATE_TYPES as $idx => $rt) {
                $this->rate_amounts[$idx] = (string) ($type->getAmountForRateType($rt) ?? '');
            }
        } else {
            $this->resetForm();
        }
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
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->is_active = true;
        $this->max_adults = 2;
        $this->max_children = 0;
        $this->room_amenity_ids = [];
        $this->rate_amounts = array_fill(0, count(AddReservation::RATE_TYPES), '');
    }

    public function save()
    {
        $hotel = Hotel::getHotel();
        $uniqueSlug = Rule::unique('room_types', 'slug')
            ->where('hotel_id', $hotel->id)
            ->ignore($this->editingId ?? 0);

        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', $uniqueSlug],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = [
            'hotel_id' => $hotel->id,
            'name' => $this->name,
            'slug' => $this->slug ?: \Illuminate\Support\Str::slug($this->name),
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
            'max_adults' => max(1, min(20, (int) $this->max_adults)),
            'max_children' => max(0, min(20, (int) $this->max_children)),
        ];

        if ($this->editingId) {
            $type = RoomType::findOrFail($this->editingId);
            $type->update($data);
            $type->amenities()->sync(array_map('intval', $this->room_amenity_ids ?: []));
            $this->syncRoomTypeRates($type);
            session()->flash('message', 'Room type updated successfully.');
        } else {
            $type = RoomType::create($data);
            $type->amenities()->sync(array_map('intval', $this->room_amenity_ids ?: []));
            $this->syncRoomTypeRates($type);
            session()->flash('message', 'Room type created successfully.');
        }

        $this->loadTypes();
        $this->loadLayoutConfig();
        $this->closeForm();
    }

    public function deleteType($id)
    {
        $user = Auth::user();
        if (! $user || ! $user->canDeleteRoomTypeWhenUnused()) {
            session()->flash('error', 'You do not have permission to delete room types. You can deactivate instead.');
            return;
        }

        $hotel = Hotel::getHotel();
        $type = RoomType::where('hotel_id', $hotel->id)->findOrFail($id);

        if ($type->rooms()->exists()) {
            session()->flash('error', 'Cannot delete room type. It has rooms assigned. Remove or reassign rooms first.');
            return;
        }

        if ($type->reservations()->exists()) {
            session()->flash('error', 'Cannot delete room type. It has reservations linked. Cancel or reassign those reservations first.');
            return;
        }

        $type->delete();
        session()->flash('message', 'Room type deleted.');
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function toggleActive($id)
    {
        $type = RoomType::findOrFail($id);
        $type->is_active = ! $type->is_active;
        $type->save();
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function updatedSearch()
    {
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function openImagesModal(int $typeId): void
    {
        $type = RoomType::with('images')->findOrFail($typeId);
        $this->imagesForTypeId = $typeId;
        $this->imagesForTypeName = $type->name;
        $this->typeImages = $type->images->toArray();
        $this->newImage = null;
        $this->newImageCaption = '';
        $this->showImagesModal = true;
    }

    protected function syncRoomTypeRates(RoomType $type): void
    {
        foreach (AddReservation::RATE_TYPES as $idx => $rt) {
            $amount = isset($this->rate_amounts[$idx]) && $this->rate_amounts[$idx] !== '' && is_numeric($this->rate_amounts[$idx])
                ? (float) $this->rate_amounts[$idx]
                : null;
            if ($amount !== null && $amount >= 0) {
                RoomTypeRate::updateOrCreate(
                    ['room_type_id' => $type->id, 'rate_type' => $rt],
                    ['amount' => $amount]
                );
            } else {
                RoomTypeRate::where('room_type_id', $type->id)->where('rate_type', $rt)->delete();
            }
        }
    }

    public function closeImagesModal(): void
    {
        $this->showImagesModal = false;
        $this->imagesForTypeId = null;
        $this->imagesForTypeName = '';
        $this->typeImages = [];
        $this->newImage = null;
        $this->newImageCaption = '';
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function addRoomTypeImage(): void
    {
        $this->validate([
            'newImage' => 'required|image|max:4096',
            'newImageCaption' => 'nullable|string|max:255',
        ]);
        $path = $this->newImage->store('room-type-images', 'public');
        $maxOrder = RoomTypeImage::where('room_type_id', $this->imagesForTypeId)->max('sort_order') ?? 0;
        RoomTypeImage::create([
            'room_type_id' => $this->imagesForTypeId,
            'path' => $path,
            'caption' => $this->newImageCaption ?: null,
            'sort_order' => $maxOrder + 1,
        ]);
        $this->newImage = null;
        $this->newImageCaption = '';
        $type = RoomType::with('images')->findOrFail($this->imagesForTypeId);
        $this->typeImages = $type->images->toArray();
        session()->flash('message', 'Image added.');
    }

    public function removeRoomTypeImage(int $imageId): void
    {
        $img = RoomTypeImage::where('room_type_id', $this->imagesForTypeId)->findOrFail($imageId);
        Storage::disk('public')->delete($img->path);
        $img->delete();
        $type = RoomType::with('images')->findOrFail($this->imagesForTypeId);
        $this->typeImages = $type->images->toArray();
        session()->flash('message', 'Image removed.');
    }

    public function openRoomsModal(int $typeId): void
    {
        $type = RoomType::with(['rooms.roomUnits', 'rooms.rates', 'rooms.wing'])->findOrFail($typeId);
        $this->roomsForTypeId = $typeId;
        $this->roomsForTypeName = $type->name;
        $this->roomsList = $type->rooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => $r->room_number,
            'name' => $r->name,
            'description' => $r->description ?? null,
            'units_count' => $r->roomUnits->count(),
            'floor' => $r->floor,
            'wing' => $r->wing?->name,
        ])->toArray();
        $this->assign_wing_id = [];
        $this->assign_floor = [];
        foreach ($type->rooms as $r) {
            $this->assign_wing_id[$r->id] = $r->wing_id ? (string) $r->wing_id : '';
            $this->assign_floor[$r->id] = $r->floor !== null ? (string) $r->floor : '';
        }
        $this->room_number = '';
        $this->room_description = '';
        $this->room_rate_amounts = array_fill(0, count(AddReservation::RATE_TYPES), '');
        $this->room_floor = 1;
        $this->bulk_count = 1;
        $this->bulk_prefix = '';
        $this->bulk_start_number = 1;
        $this->bulk_digits = 3;
        $this->loadLayoutConfig();
        $this->room_wing_id = $this->has_multiple_wings && count($this->wings) > 0 ? (int) ($this->wings[0]['id'] ?? 0) : null;
        $this->showRoomsModal = true;
    }

    public function closeRoomsModal(): void
    {
        $this->showRoomsModal = false;
        $this->roomsForTypeId = null;
        $this->roomsForTypeName = '';
        $this->roomsList = [];
        $this->room_number = '';
        $this->room_description = '';
        $this->room_rate_amounts = [];
        $this->assign_wing_id = [];
        $this->assign_floor = [];
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function getChargeLevelIsRoomProperty(): bool
    {
        return (Hotel::getHotel()->charge_level ?? 'room_type') === 'room';
    }

    public function addRoomToType(): void
    {
        $this->validate([
            'room_number' => 'required|string|max:50',
            'room_description' => 'nullable|string|max:500',
            'room_floor' => 'required|integer|min:1|max:120',
        ]);

        if ($this->has_multiple_wings && empty($this->room_wing_id)) {
            $this->addError('room_wing_id', 'Select wing.');
            return;
        }
        $type = RoomType::findOrFail($this->roomsForTypeId);
        $hotel = Hotel::getHotel();
        $room = Room::create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $type->id,
            'wing_id' => $this->has_multiple_wings ? (int) $this->room_wing_id : null,
            'room_number' => $this->room_number,
            'name' => $this->room_number,
            'floor' => (string) $this->room_floor,
            'description' => $this->room_description ?: null,
            'is_active' => true,
        ]);
        RoomUnit::create([
            'room_id' => $room->id,
            'label' => $this->room_number,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        // Save room-specific rates when provided (override parent room type when different)
        foreach (AddReservation::RATE_TYPES as $idx => $rt) {
            $amount = isset($this->room_rate_amounts[$idx]) && $this->room_rate_amounts[$idx] !== '' && is_numeric($this->room_rate_amounts[$idx])
                ? (float) $this->room_rate_amounts[$idx]
                : null;
            if ($amount !== null && $amount >= 0) {
                RoomRate::updateOrCreate(
                    ['room_id' => $room->id, 'rate_type' => $rt],
                    ['amount' => $amount]
                );
            }
        }
        $this->room_number = '';
        $this->room_description = '';
        $this->room_rate_amounts = array_fill(0, count(AddReservation::RATE_TYPES), '');
        $type->load(['rooms.roomUnits', 'rooms.rates', 'rooms.wing']);
        $this->roomsList = $type->rooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => $r->room_number,
            'name' => $r->name,
            'description' => $r->description ?? null,
            'units_count' => $r->roomUnits->count(),
            'floor' => $r->floor,
            'wing' => $r->wing?->name,
        ])->toArray();
        session()->flash('message', 'Room added.');
    }


    public function bulkCreateRooms(): void
    {
        $type = RoomType::findOrFail($this->roomsForTypeId);
        $hotel = Hotel::getHotel();

        $this->validate([
            'bulk_count' => 'required|integer|min:1|max:300',
            'bulk_start_number' => 'required|integer|min:1|max:999999',
            'bulk_digits' => 'required|integer|min:1|max:6',
            'bulk_prefix' => 'nullable|string|max:20',
            'room_floor' => 'required|integer|min:1|max:120',
        ]);

        if ($this->has_multiple_wings && empty($this->room_wing_id)) {
            $this->addError('room_wing_id', 'Select wing.');
            return;
        }

        $created = 0;
        $prefix = trim((string) $this->bulk_prefix);
        for ($i = 0; $i < (int) $this->bulk_count; $i++) {
            $n = (int) $this->bulk_start_number + $i;
            $roomNumber = $prefix . str_pad((string) $n, (int) $this->bulk_digits, '0', STR_PAD_LEFT);
            if (Room::where('hotel_id', $hotel->id)->where('room_number', $roomNumber)->exists()) {
                continue;
            }
            $room = Room::create([
                'hotel_id' => $hotel->id,
                'room_type_id' => $type->id,
                'wing_id' => $this->has_multiple_wings ? (int) $this->room_wing_id : null,
                'room_number' => $roomNumber,
                'name' => $roomNumber,
                'floor' => (string) $this->room_floor,
                'description' => null,
                'is_active' => true,
            ]);
            RoomUnit::create([
                'room_id' => $room->id,
                'label' => $roomNumber,
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $created++;
        }

        $type->load(['rooms.roomUnits', 'rooms.rates', 'rooms.wing']);
        $this->roomsList = $type->rooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => $r->room_number,
            'name' => $r->name,
            'description' => $r->description ?? null,
            'units_count' => $r->roomUnits->count(),
            'floor' => $r->floor,
            'wing' => $r->wing?->name,
        ])->toArray();

        session()->flash('message', "Bulk created {$created} room(s). Existing numbers were skipped.");
    }

    public function assignRoomPlacement(int $roomId): void
    {
        $hotel = Hotel::getHotel();
        $room = Room::where('hotel_id', $hotel->id)
            ->where('room_type_id', $this->roomsForTypeId)
            ->findOrFail($roomId);

        $floorValue = isset($this->assign_floor[$roomId]) ? (int) $this->assign_floor[$roomId] : 0;
        if ($floorValue < 1 || $floorValue > 120) {
            $this->addError("assign_floor.$roomId", 'Floor must be between 1 and 120.');
            return;
        }

        $wingId = null;
        if ($this->has_multiple_wings) {
            $candidate = $this->assign_wing_id[$roomId] ?? null;
            if (empty($candidate)) {
                $this->addError("assign_wing_id.$roomId", 'Select wing.');
                return;
            }
            $wingId = HotelWing::where('hotel_id', $hotel->id)->where('is_active', true)->whereKey((int) $candidate)->value('id');
            if (! $wingId) {
                $this->addError("assign_wing_id.$roomId", 'Selected wing is invalid.');
                return;
            }
        }

        $room->wing_id = $wingId;
        $room->floor = (string) $floorValue;
        $room->save();

        $type = RoomType::with(['rooms.roomUnits', 'rooms.rates', 'rooms.wing'])->findOrFail($this->roomsForTypeId);
        $this->roomsList = $type->rooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => $r->room_number,
            'name' => $r->name,
            'description' => $r->description ?? null,
            'units_count' => $r->roomUnits->count(),
            'floor' => $r->floor,
            'wing' => $r->wing?->name,
        ])->toArray();
        session()->flash('message', 'Room placement updated.');
    }

    public function deleteRoomFromType(int $roomId): void
    {
        $room = Room::with('roomUnits')->where('room_type_id', $this->roomsForTypeId)->findOrFail($roomId);

        if ($room->hasReservations()) {
            if (! Auth::user()->isSuperAdmin()) {
                session()->flash('error', 'Room has reservations. Only Super Admin can remove it. Use Rooms tab to "Remove from use" first.');
                return;
            }
            $unitIds = $room->roomUnits->pluck('id')->all();
            if (! empty($unitIds)) {
                \Illuminate\Support\Facades\DB::table('reservation_room_unit')
                    ->whereIn('room_unit_id', $unitIds)
                    ->delete();
            }
        } elseif ($room->pending_deletion && ! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Room is pending deletion. Only Super Admin can confirm delete.');
            return;
        } else {
            $this->authorizeRoomManagement();
        }

        $room->delete();
        $type = RoomType::with(['rooms.roomUnits', 'rooms.wing'])->findOrFail($this->roomsForTypeId);
        $this->roomsList = $type->rooms->map(fn ($r) => [
            'id' => $r->id,
            'room_number' => $r->room_number,
            'name' => $r->name,
            'description' => $r->description ?? null,
            'units_count' => $r->roomUnits->count(),
            'floor' => $r->floor,
            'wing' => $r->wing?->name,
        ])->toArray();
        session()->flash('message', 'Room removed.');
        $this->loadTypes();
        $this->loadLayoutConfig();
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $roomAmenities = Amenity::where('hotel_id', $hotel->id)
            ->where('type', Amenity::TYPE_ROOM)
            ->orderBy('sort_order')
            ->get();

        $view = view('livewire.front-office.room-types-management', [
            'roomAmenities' => $roomAmenities,
        ]);
        return $this->embed ? $view : $view->layout('livewire.layouts.app-layout');
    }
}
