<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Stock</h1>
        </header>

        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('goods.receipts'), 'icon' => 'fa-truck-loading', 'label' => 'Stock-in'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.out'), 'icon' => 'fa-dolly', 'label' => 'Stock-out'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.requisitions'), 'icon' => 'fa-clipboard-list', 'label' => 'Stock requisitions'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.requests'), 'icon' => 'fa-paper-plane', 'label' => 'Stock requests'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.movements'), 'icon' => 'fa-exchange-alt', 'label' => 'Stock movements'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.reports'), 'icon' => 'fa-chart-bar', 'label' => 'Stock reports'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('general.monthly-sales-summary'), 'icon' => 'fa-file-invoice', 'label' => 'General report'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.dashboard'), 'icon' => 'fa-th-large', 'label' => 'Stock dashboard'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.locations'), 'icon' => 'fa-map-marker-alt', 'label' => 'Stock locations'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('suppliers.index'), 'icon' => 'fa-industry', 'label' => 'Suppliers'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('purchase.requisitions'), 'icon' => 'fa-file-invoice', 'label' => 'Purchase requisitions'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.opening-closing-report'), 'icon' => 'fa-box-open', 'label' => 'Opening / closing report'])
            </div>
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
