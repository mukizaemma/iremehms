<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use App\Models\Hotel;
use App\Services\OperationalShiftService;
use Livewire\Component;

class BackOfficeHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();
    }

    public function render()
    {
        $user = auth()->user();
        $hotel = Hotel::getHotel();

        return view('livewire.navigation.back-office-hub', [
            'canConfigureHotel' => $user->hasPermission('hotel_configure_details'),
            'canManageHotelUsers' => $user->hasPermission('hotel_manage_users'),
            'canAccessShiftManagement' => OperationalShiftService::userCanAccessShiftManagementPage($user),
            'showSubscriptionTile' => $user->isEffectiveSuperAdmin() && $hotel && $hotel->shouldShowSubscriptionHubTile(),
        ])->layout('livewire.layouts.app-layout');
    }
}
