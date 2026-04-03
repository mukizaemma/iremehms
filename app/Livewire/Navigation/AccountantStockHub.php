<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantStockHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();

        $modules = Auth::user()->getAccessibleModules();
        if (! $modules->contains('slug', 'store')) {
            $this->redirect(route('dashboard'));
        }

        session(['selected_module' => 'store']);
    }

    public function render()
    {
        $u = Auth::user();

        $canViewStockReportsSidebarAccountant = (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('stock_audit')
            || $u->hasPermission('stock_logistics')
            || $u->hasPermission('reports_view_all')
            || $u->isEffectiveStoreKeeper()
        );

        return view('livewire.navigation.accountant-stock-hub', [
            'canViewStockReportsSidebarAccountant' => $canViewStockReportsSidebarAccountant,
        ])->layout('livewire.layouts.app-layout');
    }
}

