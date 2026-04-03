<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Purchasses</h1>
        </header>

        <div class="row g-3">
            @if($canViewGoodsReceipts)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('goods.receipts'),
                        'icon' => 'fa-truck-loading',
                        'label' => 'Goods receipts',
                    ])
                </div>
            @endif

            @if($canViewPurchaseRequisitions)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('purchase.requisitions'),
                        'icon' => 'fa-clipboard-list',
                        'label' => 'Purchase requisitions',
                    ])
                </div>
            @endif
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')

