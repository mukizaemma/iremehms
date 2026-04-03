<div class="manager-hub container-fluid px-3 px-md-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">
        <header class="mb-4 text-center text-md-start">
            <h1 class="h4 fw-bold text-dark mb-0">Front Office</h1>
            <p class="text-muted small mb-0">Shortcuts respect your role and permissions.</p>
        </header>

        @include('livewire.navigation.partials.front-office-hub-tiles', ['tiles' => $tiles])
    </div>
</div>

@include('livewire.navigation.partials.manager-hub-styles')
