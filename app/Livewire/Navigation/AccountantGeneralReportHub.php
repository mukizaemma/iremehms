<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantGeneralReportHub extends Component
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

        $canViewDaily = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('reports_view_all')
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->isIremeAccountant();

        // Same authorization logic as the daily component uses.
        $canViewMonthly = $canViewDaily;

        $canConfigureColumns = $user->isSuperAdmin()
            || $user->isManager()
            || $user->isEffectiveGeneralManager()
            || $user->hasPermission('hotel_manage_users')
            || $user->hasPermission('reports_view_all');

        return view('livewire.navigation.accountant-general-report-hub', [
            'canViewDaily' => $canViewDaily,
            'canViewMonthly' => $canViewMonthly,
            'canConfigureColumns' => $canConfigureColumns,
        ])->layout('livewire.layouts.app-layout');
    }
}

