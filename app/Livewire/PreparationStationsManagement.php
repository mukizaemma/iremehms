<?php

namespace App\Livewire;

use App\Models\PreparationStation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class PreparationStationsManagement extends Component
{
    public $stations = [];
    public $showForm = false;
    public $editingId = null;
    public $name = '';
    public $display_order = 0;
    public $is_active = true;
    public $has_printer = false;
    public $printer_name = '';
    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $restaurantModule = \App\Models\Module::where('slug', 'restaurant')->first();
        $canAccess = $user->isSuperAdmin() || $user->isManager()
            || ($user->getEffectiveRole() && $user->getEffectiveRole()->slug === 'restaurant-manager')
            || ($restaurantModule && $user->hasModuleAccess($restaurantModule->id) && $user->hasPermission('back_office_stations'));
        if (!$canAccess) {
            abort(403, 'Unauthorized. Preparation and posting stations require restaurant management access.');
        }
        $this->loadStations();
    }

    public function loadStations()
    {
        $query = PreparationStation::query();
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('slug', 'like', '%' . $this->search . '%')
                    ->orWhere('printer_name', 'like', '%' . $this->search . '%');
            });
        }
        $this->stations = $query->orderBy('display_order')->orderBy('name')->get();
    }

    public function updatedSearch()
    {
        $this->loadStations();
    }

    public function openForm($id = null)
    {
        $this->editingId = $id;
        if ($id) {
            $s = PreparationStation::findOrFail($id);
            $this->name = $s->name;
            $this->display_order = $s->display_order;
            $this->is_active = $s->is_active;
            $this->has_printer = $s->has_printer ?? false;
            $this->printer_name = $s->printer_name ?? '';
        } else {
            $this->name = '';
            $this->display_order = PreparationStation::max('display_order') + 1;
            $this->is_active = true;
            $this->has_printer = false;
            $this->printer_name = '';
        }
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset(['name', 'display_order', 'is_active', 'has_printer', 'printer_name']);
        $this->loadStations();
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'has_printer' => 'boolean',
            'printer_name' => 'nullable|string|max:100',
        ]);

        if ($this->editingId) {
            $station = PreparationStation::findOrFail($this->editingId);
            $station->update([
                'name' => $this->name,
                'display_order' => (int) $this->display_order,
                'is_active' => $this->is_active,
                'has_printer' => $this->has_printer,
                'printer_name' => $this->has_printer ? ($this->printer_name ?: null) : null,
            ]);
            session()->flash('message', 'Station updated.');
        } else {
            $slug = preg_replace('/[^a-z0-9_]/', '_', Str::slug($this->name)) ?: 'station';
            $baseSlug = $slug;
            $attempt = 0;
            while (PreparationStation::where('slug', $slug)->exists()) {
                $attempt++;
                $slug = $baseSlug . '_' . $attempt;
            }
            PreparationStation::create([
                'name' => $this->name,
                'slug' => $slug,
                'display_order' => (int) $this->display_order,
                'is_active' => $this->is_active,
                'has_printer' => $this->has_printer,
                'printer_name' => $this->has_printer ? ($this->printer_name ?: null) : null,
            ]);
            session()->flash('message', 'Station created.');
        }
        $this->closeForm();
    }

    public function deleteStation($id)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete stations. You can deactivate instead.');
            return;
        }
        $station = PreparationStation::findOrFail($id);
        if ($station->menuItems()->count() > 0) {
            session()->flash('error', 'Cannot delete: menu items are linked to this station.');
            return;
        }
        $station->delete();
        session()->flash('message', 'Station deleted.');
        $this->loadStations();
    }

    public function render()
    {
        return view('livewire.preparation-stations-management')
            ->layout('livewire.layouts.app-layout');
    }
}
