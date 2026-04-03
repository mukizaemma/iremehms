<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Back office</h1>
        </header>

        <div class="row g-3">
            @if($showSubscriptionTile ?? false)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('subscription'), 'icon' => 'fa-credit-card', 'label' => 'Subscription'])
                </div>
            @endif
            @if($canConfigureHotel)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('hotel-details'), 'icon' => 'fa-hotel', 'label' => 'Hotel details'])
                </div>
            @endif
            @if($canAccessShiftManagement ?? false)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('shift.management'), 'icon' => 'fa-clock', 'label' => 'Shift management'])
                </div>
            @endif
            @if($canConfigureHotel || $canManageHotelUsers)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('departments.index'), 'icon' => 'fa-building', 'label' => 'Departments'])
                </div>
            @endif
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('approvals'), 'icon' => 'fa-check-double', 'label' => 'Approvals'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('permission-requests.index'), 'icon' => 'fa-key', 'label' => 'Permission requests'])
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('recovery.dashboard'), 'icon' => 'fa-hand-holding-usd', 'label' => 'Recovery'])
            </div>
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
