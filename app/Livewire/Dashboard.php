<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    /** Default route per module slug for "Open module" links on dashboard */
    public static function getModuleDefaultRoute(string $slug): ?string
    {
        $routes = [
            'dashboard' => 'dashboard',
            'restaurant' => 'pos.home',
            'front-office' => 'front-office.dashboard',
            'store' => 'stock.dashboard',
            'recovery' => 'recovery.dashboard',
            'settings' => 'system.configuration',
            'reports' => 'stock.reports',
            'back-office' => 'menu.items',
            'housekeeping' => 'front-office.rooms',
        ];
        return $routes[$slug] ?? null;
    }

    /**
     * Menu items for waiter dashboard grid (mirrors sidebar POS + general).
     */
    protected function getWaiterMenuItems(): array
    {
        $items = [
            ['label' => 'POS', 'route' => 'pos.products', 'icon' => 'fa-cash-register', 'subtitle' => 'Register & products'],
            ['label' => 'Orders', 'route' => 'pos.orders', 'icon' => 'fa-shopping-cart', 'subtitle' => 'View & manage orders'],
            ['label' => 'New order', 'route' => 'pos.orders', 'routeParams' => ['action' => 'new'], 'icon' => 'fa-plus-circle', 'subtitle' => 'Create new order'],
            ['label' => 'Tables', 'route' => 'pos.tables', 'icon' => 'fa-th-large', 'subtitle' => 'Restaurant tables'],
            ['label' => 'Invoices', 'route' => 'pos.order-history', 'icon' => 'fa-file-invoice', 'subtitle' => 'Order & invoice history'],
            ['label' => 'Orders stations', 'route' => 'pos.orders-stations-overview', 'icon' => 'fa-tv', 'subtitle' => 'Stations overview'],
            ['label' => 'Void requests', 'route' => 'pos.void-requests', 'icon' => 'fa-ban', 'subtitle' => 'Item void requests'],
            ['label' => 'My sales', 'route' => 'pos.my-sales', 'icon' => 'fa-chart-line', 'subtitle' => 'Your sales summary'],
            ['label' => 'My reports', 'route' => 'pos.reports', 'icon' => 'fa-file-invoice', 'subtitle' => 'Your reports'],
            ['label' => 'My account', 'route' => 'profile', 'icon' => 'fa-user', 'subtitle' => 'Profile & settings'],
        ];
        return $items;
    }

    /**
     * Menu items for cashier dashboard grid (consistent design with waiter tiles).
     */
    protected function getCashierMenuItems(): array
    {
        $expensesTypeId = \App\Models\ItemType::where('code', 'EXPENSES')->value('id');
        $items = [
            ['label' => 'POS', 'route' => 'pos.products', 'icon' => 'fa-cash-register', 'subtitle' => 'Register & products'],
            ['label' => 'Orders', 'route' => 'pos.orders', 'icon' => 'fa-shopping-cart', 'subtitle' => 'View & manage orders'],
            ['label' => 'Invoices', 'route' => 'pos.order-history', 'icon' => 'fa-file-invoice', 'subtitle' => 'Order & invoice history'],
            ['label' => 'Menu Items', 'route' => 'menu.items', 'icon' => 'fa-utensils', 'subtitle' => 'Sellable menu setup'],
            ['label' => 'Tables', 'route' => 'pos.tables', 'icon' => 'fa-th-large', 'subtitle' => 'Restaurant tables'],
            ['label' => 'Preparation Stations', 'route' => 'restaurant.preparation-stations', 'icon' => 'fa-concierge-bell', 'subtitle' => 'Stations & printers'],
            ['label' => 'Sales report', 'route' => 'pos.reports', 'icon' => 'fa-chart-bar', 'subtitle' => 'All / individual / payment methods'],
            [
                'label' => 'Expenses',
                'route' => 'stock.management',
                'icon' => 'fa-money-bill-wave',
                'subtitle' => 'Manage expense items',
                'routeParams' => $expensesTypeId ? ['filter_item_type' => $expensesTypeId] : [],
            ],
            ['label' => 'Restaurant Requisitions', 'route' => 'purchase.requisitions', 'icon' => 'fa-clipboard-list', 'subtitle' => 'Bar & restaurant requests'],
            ['label' => 'Void requests', 'route' => 'pos.void-requests', 'icon' => 'fa-ban', 'subtitle' => 'Item void requests'],
            ['label' => 'My account', 'route' => 'profile', 'icon' => 'fa-user', 'subtitle' => 'Profile & settings'],
        ];

        return $items;
    }

    public function render()
    {
        $user = Auth::user();
        $modules = $user ? $user->getAccessibleModules() : collect();

        $effectiveSlug = $user?->getEffectiveRole()?->slug;

        // Waiter dashboard: grid of sidebar menu buttons for quick access (no sidebar needed, especially on phones)
        if ($user && $user->isEffectiveWaiter()) {
            return view('livewire.dashboards.waiter', [
                'user' => $user,
                'menuItems' => $this->getWaiterMenuItems(),
            ])->layout('livewire.layouts.app-layout');
        }

        // Cashier dashboard: same grid design consistency as waiter.
        if ($user && $user->getEffectiveRole()?->slug === 'cashier') {
            return view('livewire.dashboards.cashier', [
                'user' => $user,
                'menuItems' => $this->getCashierMenuItems(),
            ])->layout('livewire.layouts.app-layout');
        }

        $hasRestaurant = $modules->contains('slug', 'restaurant');
        $hasFrontOffice = $modules->contains('slug', 'front-office');
        $hasStore = $modules->contains('slug', 'store');

        // Manager-like dashboard: department cards with daily/monthly/date-range report shortcuts.
        if ($user && in_array($effectiveSlug, ['manager', 'director', 'general-manager', 'hotel-admin'], true)) {
            return view('livewire.dashboards.manager', [
                'user' => $user,
                'modules' => $modules,
                'hasRestaurant' => $hasRestaurant,
                'hasFrontOffice' => $hasFrontOffice,
                'hasStore' => $hasStore,
                'canViewGeneralReport' => true,
            ])->layout('livewire.layouts.app-layout');
        }

        // Accountant dashboard: same menu tiles style + general report summary pivot.
        if ($user && $effectiveSlug === 'accountant') {
            return view('livewire.dashboards.accountant', [
                'user' => $user,
                'modules' => $modules,
                'hasRestaurant' => $hasRestaurant,
                'hasFrontOffice' => $hasFrontOffice,
                'hasStore' => $hasStore,
                'canViewGeneralReport' => true,
            ])->layout('livewire.layouts.app-layout');
        }

        // Authorized reports: only link to reports the user has permission to view
        $canViewPosReports = $hasRestaurant && (
            $user->hasPermission('pos_audit') ||
            $user->hasPermission('pos_full_oversight') ||
            $user->hasPermission('reports_view_all') ||
            $user->canNavigateModules() ||
            $user->isWaiter() ||
            $user->isCashier() ||
            $effectiveSlug === 'cashier' ||
            $user->isRestaurantManager() ||
            $user->isSuperAdmin()
        );
        $canViewFrontOfficeReports = $hasFrontOffice && (
            $user->hasPermission('fo_availability') ||
            $user->hasPermission('fo_view_guest_bills') ||
            $user->hasPermission('reports_view_all') ||
            $user->canNavigateModules() ||
            $user->isReceptionist() ||
            $user->isSuperAdmin()
        );
        $canViewStockReports = $hasStore && (
            $user->hasPermission('stock_audit') ||
            $user->hasPermission('stock_logistics') ||
            $user->hasPermission('reports_view_all') ||
            $user->canNavigateModules() ||
            $user->isEffectiveStoreKeeper() ||
            $user->isSuperAdmin()
        );

        return view('livewire.dashboard', [
            'user' => $user,
            'modules' => $modules,
            'hasRestaurant' => $hasRestaurant,
            'hasFrontOffice' => $hasFrontOffice,
            'hasStore' => $hasStore,
            'canViewPosReports' => $canViewPosReports,
            'canViewFrontOfficeReports' => $canViewFrontOfficeReports,
            'canViewStockReports' => $canViewStockReports,
        ])->layout('livewire.layouts.app-layout');
    }
}
