<div class="container-fluid py-3">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-3">
                <h5 class="mb-1">Rooms</h5>
                <p class="text-muted small mb-0">View rooms and bookable units. Nightly rates are set per room type in the <strong>Categories</strong> tab.</p>
                <div class="mt-3 mb-2">
                    @include('livewire.front-office.partials.front-office-quick-nav', ['hideRoomsLink' => true])
                </div>
                {{-- Rates: set in Categories (room types) or Hotel settings → Pricing --}}
                <div class="d-none alert alert-info py-2 px-3 mb-4 small">
                    <strong>Where to set prices &amp; rates:</strong>
                    Go to <strong>Categories</strong> → Add or edit a room type to set <strong>rates by rate type</strong> (Locals, EAC, International, etc.).
                    If your hotel charges per room instead of per type, set that in <strong>Hotel settings → Pricing</strong>; then when you add or edit a room in the <strong>Rooms</strong> tab, you’ll see rate fields for each rate type.
                </div>

                <ul class="nav nav-tabs mt-2 mb-2">
                    @if($this->canAccessCategories())
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $tab === 'categories' ? 'active' : '' }}" wire:click="setTab('categories')">
                                <i class="fa fa-layer-group me-1"></i>Categories
                            </button>
                        </li>
                    @endif
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $tab === 'rooms' ? 'active' : '' }}" wire:click="setTab('rooms')">
                            <i class="fa fa-door-open me-1"></i>Rooms
                        </button>
                    </li>
                    @if($this->canAccessAmenities())
                        <li class="nav-item">
                            <button type="button" class="nav-link {{ $tab === 'amenities' ? 'active' : '' }}" wire:click="setTab('amenities')">
                                <i class="fa fa-list-ul me-1"></i>Amenities
                            </button>
                        </li>
                    @endif
                </ul>

                @if ($tab === 'categories')
                    @livewire('front-office.room-types-management', ['embed' => true], 'rooms-categories')
                @endif

                @if ($tab === 'rooms')
                    @livewire('front-office.rooms-management', ['embed' => true], 'rooms-list')
                @endif

                @if ($tab === 'amenities')
                    @livewire('front-office.amenities-management', ['embed' => true], 'rooms-amenities')
                @endif
            </div>
        </div>
    </div>
</div>
