<?php

namespace App\Livewire\FrontOffice;

use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FrontOfficeRooms extends Component
{
    use ChecksModuleStatus;

    public $tab = 'rooms';

    public function canAccessCategories(): bool
    {
        $user = Auth::user();
        return $user
            && ($user->isSuperAdmin() || $user->isManager() || $user->isEffectiveGeneralManager() || $user->hasPermission('back_office_rooms'));
    }

    public function canAccessAmenities(): bool
    {
        $user = Auth::user();
        return $user && ($user->isSuperAdmin() || $user->isManager());
    }

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }
        $this->tab = request()->get('tab', 'rooms');
        if ($this->tab === 'categories' && ! $this->canAccessCategories()) {
            $this->tab = 'rooms';
        }
        if ($this->tab === 'amenities' && ! $this->canAccessAmenities()) {
            $this->tab = 'rooms';
        }
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'categories' && ! $this->canAccessCategories()) {
            $tab = 'rooms';
        }
        if ($tab === 'amenities' && ! $this->canAccessAmenities()) {
            $tab = 'rooms';
        }
        $this->tab = $tab;
    }

    public function render()
    {
        return view('livewire.front-office.front-office-rooms')->layout('livewire.layouts.app-layout');
    }
}
