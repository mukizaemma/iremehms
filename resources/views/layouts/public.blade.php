<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $hotel->name ?? 'Hotel') – Book your stay</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">

    <link href="{{ asset('admintemplates/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admintemplates/css/style.css') }}" rel="stylesheet">

    <style>
        :root {
            --public-accent: {{ $hotel->primary_color ?? '#d4a012' }};
            --public-accent-dark: {{ $hotel->secondary_color ?? '#b8860b' }};
            --public-font: 'Inter', {{ $hotel->font_family ?? 'Heebo' }}, sans-serif;
        }
        body { font-family: var(--public-font); background: #fff; }
        .public-header { background: #1a1a1a; color: #fff; }
        .public-header a { color: #fff; text-decoration: none; font-weight: 500; }
        .public-header a:hover { color: #e0e0e0; }
        .btn-public { background: var(--public-accent); color: #1a1a1a; border: none; font-weight: 600; }
        .btn-public:hover { background: var(--public-accent-dark); color: #1a1a1a; }
        .btn-book-now { background: #fff; color: #1a1a1a; border: none; font-weight: 600; border-radius: 6px; padding: 0.5rem 1.25rem; }
        .btn-book-now:hover { background: #f0f0f0; color: #1a1a1a; }
        .hero-overlay { background: linear-gradient(to right, rgba(0,0,0,.5) 0%, rgba(0,0,0,.2) 50%, transparent 100%); }
        .card-room { transition: box-shadow .2s; }
        .card-room:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12); }
        .room-card-image-caption { background: rgba(34, 72, 52, 0.9); color: #fff; padding: 0.6rem 1rem; font-weight: 600; font-size: 0.95rem; letter-spacing: 0.02em; }
        .carousel-control-prev, .carousel-control-next { width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,.95) !important; opacity: 1; top: 50%; transform: translateY(-50%); margin: 0 1rem; z-index: 10; border: 0; }
        .carousel-control-prev { left: 0; }
        .carousel-control-next { right: 0; }
        .carousel-control-prev-icon, .carousel-control-next-icon { filter: invert(1); width: 1.5rem; height: 1.5rem; }
        .carousel-control-prev:hover, .carousel-control-next:hover { background: #fff !important; opacity: 1; }
        .public-footer { background: #1a1a1a; color: #fff; padding: 1rem 0; }
        .public-footer a { color: #fff; text-decoration: none; }
        .public-footer a:hover { color: #e0e0e0; }
        .availability-hurry { color: #0d6b0d; font-weight: 600; }
        .availability-last { color: #c00; font-weight: 600; }
    </style>
    @stack('styles')
    @livewireStyles
</head>
<body>
    @if(isset($hotel))
    <header class="public-header py-3">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}" class="d-flex align-items-center gap-3 text-decoration-none">
                    @if($hotel->logo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($hotel->logo) }}" alt="{{ $hotel->name }}" class="d-inline-block" style="max-height: 48px; width: auto;">
                    @endif
                    <span class="d-flex flex-column">
                        <span class="fw-bold fs-4 text-white">{{ $hotel->name }}</span>
                        <span class="small text-white-50">Your destination for comfort and convenience</span>
                    </span>
                </a>
                <nav class="d-none d-md-flex align-items-center gap-4">
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#hotel-info">About</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#rooms">Rooms</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#gallery">Gallery</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#video-tour">Videos</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#reviews">Reviews</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#map">Map</a>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#contact">Contact</a>
                </nav>
                <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#rooms" class="btn btn-book-now">Book Now</a>
            </div>
        </div>
    </header>
    @endif

    <main>
        @yield('content')
    </main>

    @if(isset($hotel))
    <footer class="public-footer mt-5">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="small">Powered by {{ $hotel->name }}</span>
                <span>
                    <a href="{{ route('public.booking', ['slug' => $hotel->public_slug]) }}#contact" class="small me-2">Share this hotel &gt;</a>
                    <a href="#" class="text-white-50" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </span>
            </div>
        </div>
    </footer>
    @endif

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
