<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    @php
        $u = Auth::user();
        $effectiveSlug = $u?->getEffectiveRole()?->slug;
        $canViewPosReportsHub = $u && (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('pos_audit')
            || $u->hasPermission('pos_full_oversight')
            || $u->hasPermission('reports_view_all')
            || in_array($effectiveSlug, ['waiter', 'cashier'], true)
            || $u->isRestaurantManager()
        );
    @endphp
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">POS</h1>
        </header>

        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.products'), 'icon' => 'fa-cash-register', 'label' => 'POS'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.orders'), 'icon' => 'fa-shopping-cart', 'label' => 'Orders'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.order-history'), 'icon' => 'fa-file-invoice', 'label' => 'Invoices'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('menu.items'), 'icon' => 'fa-utensils', 'label' => 'Menu Management'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.requisitions'), 'icon' => 'fa-clipboard-list', 'label' => 'Stock Requisitions'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.void-requests'), 'icon' => 'fa-ban', 'label' => 'Void requests'])
            </div>
            @if($showReceiptModification)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.receipt-modification-requests'), 'icon' => 'fa-edit', 'label' => 'Receipt modification'])
                </div>
            @endif
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.my-sales'), 'icon' => 'fa-chart-line', 'label' => 'My sales'])
            </div>
            @if($canViewPosReportsHub)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.reports'), 'icon' => 'fa-chart-pie', 'label' => 'POS reports'])
                </div>
            @endif
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.aging-orders'), 'icon' => 'fa-clock', 'label' => 'Aging orders'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.orders-stations-overview'), 'icon' => 'fa-th-large', 'label' => 'Orders & stations'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('restaurant.waiters'), 'icon' => 'fa-user-tie', 'label' => 'Waiters'])
            </div>
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
