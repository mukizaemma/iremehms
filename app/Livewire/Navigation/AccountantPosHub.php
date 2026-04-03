<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AccountantPosHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();

        $modules = Auth::user()->getAccessibleModules();
        if (! $modules->contains('slug', 'restaurant')) {
            $this->redirect(route('dashboard'));
        }

        session(['selected_module' => 'restaurant']);
    }

    public function render()
    {
        $u = Auth::user();
        $showReceiptModification = $u->hasPermission('pos_approve_receipt_modification') || $u->canNavigateModules();

        $restaurantModule = Module::where('slug', 'restaurant')->first();
        $restaurantModuleId = $restaurantModule?->id;
        $hasRestaurantAccess = $restaurantModuleId ? $u->hasModuleAccess($restaurantModuleId) : false;

        $canViewPOSProducts = $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('pos_take_orders');

        $canViewMenuManagement = $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->isManager()
            || $u->isRestaurantManager()
            || ($hasRestaurantAccess && $u->hasPermission('back_office_menu_items'));

        $canViewOrdersStationsOverview = $u->canNavigateModules()
            || $u->hasPermission('pos_view_station_orders');
        $canViewWaiters = $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->isManager()
            || ($u->getEffectiveRole() && $u->getEffectiveRole()->slug === 'restaurant-manager');

        $canViewAgingOrders = $u->canNavigateModules()
            || $u->hasPermission('pos_confirm_payment');

        return view('livewire.navigation.accountant-pos-hub', [
            'showReceiptModification' => $showReceiptModification,
            'canViewPOSProducts' => $canViewPOSProducts,
            'canViewMenuManagement' => $canViewMenuManagement,
            'canViewOrdersStationsOverview' => $canViewOrdersStationsOverview,
            'canViewWaiters' => $canViewWaiters,
            'canViewAgingOrders' => $canViewAgingOrders,
        ])->layout('livewire.layouts.app-layout');
    }
}

