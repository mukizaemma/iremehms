<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantCommunicationsHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();

        $user = Auth::user();
        $modules = $user?->getAccessibleModules() ?? collect();
        if (! $modules->contains('slug', 'front-office')) {
            $this->redirect(route('dashboard'));
        }

        session(['selected_module' => 'front-office']);
    }

    public function render()
    {
        $user = Auth::user();

        $canCommunicate = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();

        return view('livewire.navigation.accountant-communications-hub', [
            'canCommunicate' => $canCommunicate,
        ])->layout('livewire.layouts.app-layout');
    }
}

