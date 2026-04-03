<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use App\Models\Module;
use App\Support\FrontOfficeHubPermissions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantFrontOfficeHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();

        $modules = Auth::user()->getAccessibleModules();
        if (! $modules->contains('slug', 'front-office')) {
            $this->redirect(route('dashboard'));
        }

        session(['selected_module' => 'front-office']);
    }

    public function render()
    {
        $u = Auth::user();
        $frontOfficeModule = Module::where('slug', 'front-office')->first();
        $frontOfficeModuleId = $frontOfficeModule?->id;

        $tiles = FrontOfficeHubPermissions::tileVisibility($u, $frontOfficeModuleId);

        return view('livewire.navigation.accountant-front-office-hub', [
            'tiles' => $tiles,
        ])->layout('livewire.layouts.app-layout');
    }
}
