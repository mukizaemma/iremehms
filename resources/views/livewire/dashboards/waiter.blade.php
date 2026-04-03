@php
    $menuItems = $menuItems ?? [];
@endphp
<style>
    .waiter-grid-card {
        display: block;
        min-height: 100%;
        text-decoration: none;
        color: inherit;
        border-radius: 12px;
        transition: box-shadow 0.2s ease, transform 0.15s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .waiter-grid-card:hover, .waiter-grid-card:focus {
        color: inherit;
        box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,.12);
        transform: translateY(-2px);
    }
    .waiter-grid-card:active {
        transform: translateY(0);
    }
    .waiter-grid-card .card {
        min-height: 100%;
        border-radius: 12px;
    }
    .waiter-grid-card .card-body {
        padding: 1rem 0.75rem;
    }
    @media (min-width: 576px) {
        .waiter-grid-card .card-body { padding: 1.25rem 1rem; }
    }
    @media (min-width: 768px) {
        .waiter-grid-card .card-body { padding: 1.5rem 1.25rem; }
    }
    .waiter-grid-card .fa-icon {
        font-size: 1.75rem;
    }
    @media (min-width: 576px) {
        .waiter-grid-card .fa-icon { font-size: 2rem; }
    }
</style>
<div class="row g-3 g-md-4">
    <div class="col-12">
        <div class="bg-light rounded-3 p-3 p-md-4">
            <h5 class="mb-1">Dashboard</h5>
            <p class="text-muted small mb-0">Welcome, <strong>{{ $user->name }}</strong>. Tap any tile to open — no need to use the sidebar.</p>
        </div>
    </div>
    <div class="col-12">
        <p class="text-muted small mb-2 mb-md-3">Quick access</p>
        <div class="row g-3 g-md-4">
            @foreach($menuItems as $item)
                @php
                    $url = isset($item['routeParams']) ? route($item['route'], $item['routeParams']) : route($item['route']);
                @endphp
                <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                    <a href="{{ $url }}" class="waiter-grid-card">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                                <i class="fa {{ $item['icon'] ?? 'fa-circle' }} fa-icon text-primary mb-2"></i>
                                <h6 class="mb-0 text-dark fw-semibold">{{ $item['label'] }}</h6>
                                @if(!empty($item['subtitle']))
                                    <small class="text-muted mt-1">{{ $item['subtitle'] }}</small>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
