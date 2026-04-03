{{-- Expects $tiles array from App\Support\FrontOfficeHubPermissions::tileVisibility() --}}
<div class="row g-3">
    @if($tiles['rooms'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.rooms'), 'icon' => 'fa-bed', 'label' => 'Rooms'])
        </div>
    @endif

    @if($tiles['booking_calendar'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('module.show', 'front-office'), 'icon' => 'fa-calendar-alt', 'label' => 'Booking calendar'])
        </div>
    @endif

    @if($tiles['new_reservation'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.add-reservation'), 'icon' => 'fa-calendar-plus', 'label' => 'New reservation'])
        </div>
    @endif

    @if($tiles['all_reservations'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.reservations'), 'icon' => 'fa-list', 'label' => 'All reservations'])
        </div>
    @endif

    @if($tiles['group_reservation'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.quick-group-booking'), 'icon' => 'fa-users', 'label' => 'Group reservation'])
        </div>
    @endif

    @if($tiles['pre_arrival'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.self-registered'), 'icon' => 'fa-clipboard-list', 'label' => 'Pre-arrival'])
        </div>
    @endif

    @if($tiles['guests_report'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.guests-report'), 'icon' => 'fa-list-alt', 'label' => 'Guests report'])
        </div>
    @endif

    @if($tiles['rooms_daily_report'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.daily-accommodation-report'), 'icon' => 'fa-file-invoice-dollar', 'label' => 'Rooms daily report'])
        </div>
    @endif

    @if($tiles['other_sales_report'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.reports'), 'icon' => 'fa-chart-bar', 'label' => 'Other sales report'])
        </div>
    @endif

    @if($tiles['communication'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.communications'), 'icon' => 'fa-envelope-open-text', 'label' => 'Communication'])
        </div>
    @endif

    @if($tiles['proforma'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.proforma-invoices'), 'icon' => 'fa-file-signature', 'label' => 'Proforma invoices'])
        </div>
    @endif

    @if($tiles['wellness'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('front-office.wellness'), 'icon' => 'fa-spa', 'label' => 'Wellness'])
        </div>
    @endif

    @if($tiles['shift_management'] ?? false)
        <div class="col-6 col-md-4 col-lg-3">
            @include('livewire.navigation.partials.hub-tile', ['href' => route('shift.management'), 'icon' => 'fa-clock', 'label' => 'Shift management'])
        </div>
    @endif
</div>

@php
    $anyTile = collect($tiles ?? [])->contains(fn ($v) => (bool) $v);
@endphp
@if(! $anyTile)
    <div class="alert alert-info mb-0">
        No Front Office shortcuts are available for your account. Ask an administrator to assign the Front Office module and relevant permissions.
    </div>
@endif
