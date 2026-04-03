<?php

namespace App\Livewire\FrontOffice;

use App\Models\Amenity;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AmenitiesManagement extends Component
{
    public $amenities = [];
    public $showForm = false;
    public $editingId = null;

    public $name = '';
    public $type = Amenity::TYPE_ROOM;
    public $icon = '';
    public $sort_order = 0;

    public $search = '';
    public $filterType = '';

    /** @var bool When true, render without layout (for embedding in Rooms tabs) */
    public $embed = false;

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->authorizeRoomManagement();
        $this->loadAmenities();
    }

    protected function authorizeRoomManagement(): void
    {
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isManager()) {
            abort(403, 'Unauthorized. Only Manager and Super Admin can manage amenities.');
        }
    }

    public function loadAmenities()
    {
        $hotel = Hotel::getHotel();
        $query = Amenity::where('hotel_id', $hotel->id);

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }
        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $this->amenities = $query->orderBy('sort_order')->orderBy('name')->get()->toArray();
    }

    public function openForm($id = null)
    {
        $this->editingId = $id;
        if ($id) {
            $a = Amenity::findOrFail($id);
            $this->name = $a->name;
            $this->type = $a->type;
            $this->icon = $a->icon ?? '';
            $this->sort_order = $a->sort_order;
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
        $this->type = Amenity::TYPE_ROOM;
        $this->icon = '';
        $this->sort_order = 0;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:room,hotel',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $hotel = Hotel::getHotel();
        $data = [
            'hotel_id' => $hotel->id,
            'name' => $this->name,
            'type' => $this->type,
            'icon' => $this->icon ?: null,
            'sort_order' => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            Amenity::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Amenity updated successfully.');
        } else {
            Amenity::create($data);
            session()->flash('message', 'Amenity created successfully.');
        }

        $this->loadAmenities();
        $this->closeForm();
    }

    public function deleteAmenity($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete amenities. You can deactivate by editing.');
            return;
        }
        Amenity::findOrFail($id)->delete();
        session()->flash('message', 'Amenity deleted.');
        $this->loadAmenities();
    }

    public function updatedFilterType()
    {
        $this->loadAmenities();
    }

    public function updatedSearch()
    {
        $this->loadAmenities();
    }

    public function render()
    {
        $view = view('livewire.front-office.amenities-management');
        return $this->embed ? $view : $view->layout('livewire.layouts.app-layout');
    }
}
