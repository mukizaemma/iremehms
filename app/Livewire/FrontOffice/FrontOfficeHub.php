<?php

namespace App\Livewire\FrontOffice;

use App\Models\Module;
use App\Support\FrontOfficeHubPermissions;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tablet-friendly launch screen for Front Office (managers, hotel admins, super admin).
 * Receptionists keep the flat sidebar links and are redirected if they open this URL.
 */
class FrontOfficeHub extends Component
{
    use ChecksModuleStatus;

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');

        $user = Auth::user();
        $module = Module::where('slug', 'front-office')->first();
        if ($module && ! $user->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        $effective = $user->getEffectiveRole();
        $mayUseHub = $user->canNavigateModules()
            || ($effective && $effective->slug === 'super-admin');

        if (! $mayUseHub) {
            $this->redirect(route('front-office.rooms'));
        }
    }

    public function render()
    {
        $u = Auth::user();
        $frontOfficeModule = Module::where('slug', 'front-office')->first();
        $tiles = FrontOfficeHubPermissions::tileVisibility($u, $frontOfficeModule?->id);

        return view('livewire.front-office.front-office-hub', [
            'tiles' => $tiles,
        ])->layout('livewire.layouts.app-layout');
    }
}
