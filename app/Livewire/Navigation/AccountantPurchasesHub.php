<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantPurchasesHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();

        $user = Auth::user();
        $modules = $user?->getAccessibleModules() ?? collect();
        if (! $modules->contains('slug', 'store')) {
            $this->redirect(route('dashboard'));
        }

        session(['selected_module' => 'store']);
    }

    public function render()
    {
        $user = Auth::user();
        $storeModule = Module::where('slug', 'store')->first();

        $storeModuleId = $storeModule?->id;
        $hasStoreAccess = $storeModuleId ? $user->hasModuleAccess($storeModuleId) : false;

        // Goods receipts and purchase requisitions are allowed for store-access users.
        // Manager-like roles usually have module navigation enabled, so include canNavigateModules as well.
        $canViewGoodsReceipts = $user->isSuperAdmin()
            || $user->isManager()
            || $user->canNavigateModules()
            || $hasStoreAccess;

        $canViewPurchaseRequisitions = $user->isSuperAdmin()
            || $user->isManager()
            || $user->canNavigateModules()
            || $hasStoreAccess;

        return view('livewire.navigation.accountant-purchases-hub', [
            'canViewGoodsReceipts' => $canViewGoodsReceipts,
            'canViewPurchaseRequisitions' => $canViewPurchaseRequisitions,
        ])->layout('livewire.layouts.app-layout');
    }
}

