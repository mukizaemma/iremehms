<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Requisitions</h1>
        </header>

        <div class="row g-3">
            @if($canViewStockRequisitions)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('stock.requisitions'),
                        'icon' => 'fa-clipboard-list',
                        'label' => 'Stock requisitions',
                    ])
                </div>
            @endif

            @if($canViewStockRequests)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('stock.requests'),
                        'icon' => 'fa-paper-plane',
                        'label' => 'Stock requests',
                    ])
                </div>
            @endif
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')

