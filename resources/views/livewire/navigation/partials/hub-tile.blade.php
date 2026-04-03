{{-- Expects: $href, $icon (Font Awesome 5 class suffix e.g. fa-bed), $label --}}
<a href="{{ $href }}" class="hub-card card h-100 text-decoration-none text-dark">
    <div class="card-body hub-tile-body d-flex flex-column align-items-center justify-content-center text-center px-3 py-4">
        <span class="hub-tile-icon-wrap d-flex align-items-center justify-content-center flex-shrink-0 mb-3" aria-hidden="true">
            <i class="fa {{ $icon }} hub-tile-icon"></i>
        </span>
        <span class="hub-tile-title text-dark">{{ $label }}</span>
    </div>
</a>
