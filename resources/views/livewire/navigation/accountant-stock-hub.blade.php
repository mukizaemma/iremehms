<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Stock</h1>
        </header>

        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.dashboard'), 'icon' => 'fa-th-large', 'label' => 'Stock dashboard'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.management'), 'icon' => 'fa-boxes', 'label' => 'Stock management'])
            </div>

            @if($canViewStockReportsSidebarAccountant)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.reports'), 'icon' => 'fa-chart-bar', 'label' => 'Stock reports'])
                </div>
            @endif

            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.daily-by-category'), 'icon' => 'fa-layer-group', 'label' => 'Daily stock by category'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.daily-selling-location'), 'icon' => 'fa-store', 'label' => 'Selling location daily'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.opening-closing-report'), 'icon' => 'fa-box-open', 'label' => 'Opening / closing report'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.location-activity-report'), 'icon' => 'fa-clipboard-list', 'label' => 'Stock by location report'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.movements'), 'icon' => 'fa-exchange-alt', 'label' => 'Stock movements'])
            </div>
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')

