<?php

namespace App\Livewire;

use App\Models\Stock;
use App\Models\StockLocation;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockLocationManagement extends Component
{
    use ChecksModuleStatus;

    public $locations = [];

    /** @var array<int, array{items: int, low: int, value: float}> */
    public array $locationStats = [];

    public $showLocationForm = false;

    public $editingLocationId = null;

    // Form fields
    public $name = '';

    public $code = '';

    public $description = '';

    public $parent_location_id = null;

    public $is_main_location = true;

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');

        $user = Auth::user();
        if (! $user || ! $user->canManageStockLocations()) {
            abort(403, 'You do not have permission to manage stock locations.');
        }

        $this->loadLocations();
    }

    public function loadLocations()
    {
        $locations = StockLocation::with(['parentLocation', 'subLocations'])
            ->orderBy('is_main_location', 'desc')
            ->orderBy('name')
            ->get();

        $ids = $locations->pluck('id');
        $byLocation = Stock::query()
            ->whereIn('stock_location_id', $ids)
            ->get()
            ->groupBy('stock_location_id');

        $this->locationStats = [];
        foreach ($locations as $loc) {
            $stocks = $byLocation->get($loc->id, collect());
            $this->locationStats[$loc->id] = [
                'items' => $stocks->count(),
                'low' => $stocks->filter(fn (Stock $s) => $s->isLowStock())->count(),
                'value' => round($stocks->sum(fn (Stock $s) => $s->purchaseLineValue()), 2),
            ];
        }

        $this->locations = $locations->toArray();
    }

    public function openLocationForm($locationId = null)
    {
        $this->editingLocationId = $locationId;

        if ($locationId) {
            $location = StockLocation::find($locationId);
            $this->name = $location->name;
            $this->code = $location->code;
            $this->description = $location->description;
            $this->parent_location_id = $location->parent_location_id;
            $this->is_main_location = $location->is_main_location;
        } else {
            $this->resetLocationForm();
        }

        $this->showLocationForm = true;
    }

    public function openSubLocationForm($parentLocationId)
    {
        $this->parent_location_id = $parentLocationId;
        $this->is_main_location = false;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->editingLocationId = null;
        $this->showLocationForm = true;
    }

    public function closeLocationForm()
    {
        $this->showLocationForm = false;
        $this->resetLocationForm();
    }

    public function resetLocationForm()
    {
        $this->editingLocationId = null;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->parent_location_id = null;
        $this->is_main_location = true;
    }

    public function saveLocation()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255|unique:stock_locations,code,'.($this->editingLocationId ?? ''),
            'description' => 'nullable|string',
            'parent_location_id' => 'nullable|exists:stock_locations,id',
            'is_main_location' => 'boolean',
        ]);

        // If parent_location_id is set, it cannot be a main location
        if ($this->parent_location_id) {
            $this->is_main_location = false;
        }

        if ($this->editingLocationId) {
            $location = StockLocation::find($this->editingLocationId);
            $location->update([
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
                'parent_location_id' => $this->parent_location_id,
                'is_main_location' => $this->is_main_location,
            ]);
            session()->flash('message', 'Stock location updated successfully!');
        } else {
            StockLocation::create([
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
                'parent_location_id' => $this->parent_location_id,
                'is_main_location' => $this->is_main_location,
            ]);
            session()->flash('message', 'Stock location created successfully!');
        }

        $this->closeLocationForm();
        $this->loadLocations();
    }

    public function deleteLocation($locationId)
    {
        $location = StockLocation::find($locationId);

        // Check if location has stocks
        if ($location->stocks()->count() > 0) {
            session()->flash('error', 'Cannot delete location: It has stock items assigned.');

            return;
        }

        // Check if location has sub-locations
        if ($location->subLocations()->count() > 0) {
            session()->flash('error', 'Cannot delete location: It has sub-locations. Delete sub-locations first.');

            return;
        }

        $location->delete();
        session()->flash('message', 'Stock location deleted successfully!');
        $this->loadLocations();
    }

    public function toggleActive($locationId)
    {
        $location = StockLocation::find($locationId);
        $location->is_active = ! $location->is_active;
        $location->save();
        $this->loadLocations();
    }

    public function render()
    {
        $mainLocations = StockLocation::mainLocations()->get();

        return view('livewire.stock-location-management', [
            'mainLocations' => $mainLocations,
        ])->layout('livewire.layouts.app-layout');
    }
}
