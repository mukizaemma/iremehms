<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Module;
use App\Models\StaffMessage;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class FrontOfficeCommunications extends Component
{
    use ChecksModuleStatus;

    #[Url]
    public string $tab = 'guests';

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        $user = Auth::user();
        $canCommunicate = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();
        if (! $canCommunicate) {
            abort(403, 'You do not have permission to use communications.');
        }

        if ($this->tab !== 'guests' && $this->tab !== 'staff') {
            $this->tab = 'guests';
        }
    }

    public function getUnreadStaffMessagesProperty(): int
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return 0;
        }

        return StaffMessage::query()
            ->where('hotel_id', $hotel->id)
            ->where('recipient_id', Auth::id())
            ->whereNull('read_at')
            ->count();
    }

    public function render()
    {
        return view('livewire.front-office.front-office-communications')
            ->layout('livewire.layouts.app-layout');
    }
}
