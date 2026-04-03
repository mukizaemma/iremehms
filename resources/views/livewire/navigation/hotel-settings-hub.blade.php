<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Hotel settings</h1>
        </header>

        <div class="row g-3">
            @if($hasRestaurant)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('pos.tables'), 'icon' => 'fa-utensils', 'label' => 'Restaurant tables'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('menu.items'), 'icon' => 'fa-hamburger', 'label' => 'Menu items'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('menu.categories'), 'icon' => 'fa-folder-open', 'label' => 'Menu categories'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('menu.item-types'), 'icon' => 'fa-tags', 'label' => 'Menu item types'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('menu.bill-of-menu'), 'icon' => 'fa-book', 'label' => 'Bill of menu'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('restaurant.preparation-stations'), 'icon' => 'fa-fire', 'label' => 'Preparation stations'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('restaurant.posting-stations'), 'icon' => 'fa-share-square', 'label' => 'Posting stations'])
                </div>
            @endif

            @if($hasFrontOffice)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('room-types.index'), 'icon' => 'fa-door-open', 'label' => 'Room types & units'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('additional-charges.index'), 'icon' => 'fa-plus-circle', 'label' => 'Additional charges'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('amenities.index'), 'icon' => 'fa-star', 'label' => 'Amenities'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office-hotel-settings'), 'icon' => 'fa-sliders-h', 'label' => 'FO hotel settings'])
                </div>
            @endif

            @if($hasStore)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.management'), 'icon' => 'fa-boxes', 'label' => 'Stock management'])
                </div>
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('stock.pending-deductions'), 'icon' => 'fa-hourglass-half', 'label' => 'Pending stock deductions'])
                </div>
            @endif

            @if($canConfigureHotel || $canManageHotelUsers)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('users.index'), 'icon' => 'fa-users-cog', 'label' => 'Users'])
                </div>
            @endif
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
