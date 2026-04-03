<div>
    <div class="mb-3">
        <a href="{{ route('ireme.hotels.edit', $hotel) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back to hotel</a>
    </div>
    <p class="text-muted small mb-3">Managing additional charges for <strong>{{ $hotel->name }}</strong>.</p>
    @livewire('front-office.additional-charges-management', key('ireme-charges-' . $hotel->id))
</div>
