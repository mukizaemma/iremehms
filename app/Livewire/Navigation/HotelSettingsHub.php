<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HotelSettingsHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();
    }

    public function render()
    {
        $user = Auth::user();
        $modules = $user->getAccessibleModules();

        return view('livewire.navigation.hotel-settings-hub', [
            'hasRestaurant' => $modules->contains('slug', 'restaurant'),
            'hasStore' => $modules->contains('slug', 'store'),
            'hasFrontOffice' => $modules->contains('slug', 'front-office'),
            'canConfigureHotel' => $user->hasPermission('hotel_configure_details'),
            'canManageHotelUsers' => $user->hasPermission('hotel_manage_users'),
        ])->layout('livewire.layouts.app-layout');
    }
}
