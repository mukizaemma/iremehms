<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">My account</h1>
        </header>

        <div class="row g-3">
            @auth
                <div class="col-6 col-md-4 col-lg-3">
                    @include('livewire.navigation.partials.hub-tile', ['href' => route('activity-log'), 'icon' => 'fa-history', 'label' => 'Activity log'])
                </div>
            @endauth
            <div class="col-6 col-md-4 col-lg-3">
                @include('livewire.navigation.partials.hub-tile', ['href' => route('profile'), 'icon' => 'fa-user-circle', 'label' => 'Profile & password'])
            </div>
        </div>
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
