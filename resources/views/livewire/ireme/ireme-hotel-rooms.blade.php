<div>
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="{{ route('ireme.hotels.edit', $hotel) }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back to hotel</a>
        <ul class="nav nav-pills mb-0">
            <li class="nav-item">
                <button type="button" class="nav-link {{ $tab === 'rooms' ? 'active' : '' }}" wire:click="setTab('rooms')">
                    <i class="fa fa-door-open me-1"></i>Rooms
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $tab === 'categories' ? 'active' : '' }}" wire:click="setTab('categories')">
                    <i class="fa fa-layer-group me-1"></i>Categories (room types)
                </button>
            </li>
        </ul>
    </div>
    <p class="text-muted small mb-3">
        @if($tab === 'rooms')
            Managing rooms for <strong>{{ $hotel->name }}</strong>. As Super Admin you can confirm deletion of rooms marked "Remove from use" by the hotel.
        @else
            Managing <strong>room types (categories)</strong> for {{ $hotel->name }}. Add or edit categories (e.g. Executive Room, Standard), set rates, and add rooms under each type.
        @endif
    </p>

    @if($tab === 'rooms')
        @livewire('front-office.rooms-management', ['embed' => true, 'allowDeleteFromIreme' => true], key('ireme-rooms-' . $hotel->id))
    @else
        @livewire('front-office.room-types-management', ['embed' => true], key('ireme-categories-' . $hotel->id))
    @endif
</div>
