<div>
    <div class="mb-3">
        <a href="{{ route('ireme.hotels.edit', $hotel) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back to hotel</a>
    </div>
    <p class="text-muted small mb-3">Managing menu items for <strong>{{ $hotel->name }}</strong>.</p>
    @livewire('menu-management', key('ireme-menu-' . $hotel->id))
</div>
