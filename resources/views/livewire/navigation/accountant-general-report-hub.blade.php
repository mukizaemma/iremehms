<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">General report</h1>
        </header>

        <div class="row g-3">
            @if($canViewDaily)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('general.daily-sales-summary'),
                        'icon' => 'fa-file-invoice',
                        'label' => 'Daily sales summary',
                    ])
                </div>
            @endif

            @if($canViewMonthly)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('general.monthly-sales-summary'),
                        'icon' => 'fa-file-invoice',
                        'label' => 'Monthly sales summary',
                    ])
                </div>
            @endif

            @if($canConfigureColumns ?? false)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', [
                        'href' => route('front-office.general-report-settings'),
                        'icon' => 'fa-columns',
                        'label' => 'Report columns',
                    ])
                </div>
            @endif
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')

