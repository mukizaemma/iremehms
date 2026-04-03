<div class="container py-4">
    {{-- Room header bar (like ref: room name, availability, price) --}}
    @php $roomsAvailable = $roomType->rooms()->where('is_active', true)->count(); @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 px-3 rounded-3 mb-4" style="background: var(--public-accent);">
        <h1 class="h5 mb-0 fw-bold">{{ $roomType->name }}</h1>
        <div class="d-flex align-items-center gap-3">
            <span class="small">{{ $roomsAvailable }} room(s) available</span>
            @if($this->pricePerNight !== null)
                <span class="fw-bold">{{ $this->currencySymbol }}{{ number_format($this->pricePerNight, 2) }}</span>
                <span class="small">Price for whole stay (1 night)</span>
            @endif
            <a href="{{ route('public.booking', ['slug' => $slug]) }}" class="btn btn-dark btn-sm">← Back to rooms</a>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-pills gap-2 mb-4">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'details' ? 'active' : '' }}" wire:click="setTab('details')">Room details</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'gallery' ? 'active' : '' }}" wire:click="setTab('gallery')">Photo gallery</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'enquiry' ? 'active' : '' }}" wire:click="setTab('enquiry')" id="enquiry">Make an enquiry</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'map' ? 'active' : '' }}" wire:click="setTab('map')">Hotel map</button>
        </li>
    </ul>

    @if(session('enquiry_message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('enquiry_message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($tab === 'details')
        <div class="row">
            <div class="col-lg-6 mb-4">
                @php $mainImage = $roomType->images->first(); @endphp
                @if($mainImage)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($mainImage->path) }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $roomType->name }}" style="max-height: 400px; object-fit: cover;">
                @else
                    <div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted" style="height: 300px;"><i class="fas fa-bed fa-4x"></i></div>
                @endif
            </div>
            <div class="col-lg-6">
                <h2 class="h5">Room description</h2>
                <p class="text-muted">{{ $roomType->description ?: 'Comfortable room with modern amenities.' }}</p>
                <h3 class="h6 mt-3">Room capacity</h3>
                <p class="text-muted">2 adult(s), 0 child(ren) per room</p>
                <h3 class="h6 mt-3">Room amenities</h3>
                <ul class="list-unstyled row g-2 small">
                    @forelse($roomType->amenities as $amenity)
                        <li class="col-6 col-md-4"><i class="fas fa-check text-success me-2"></i>{{ $amenity->name }}</li>
                    @empty
                        <li class="col-12 text-muted">No amenities listed.</li>
                    @endforelse
                </ul>
                <h3 class="h6 mt-3">Hotel amenities</h3>
                <ul class="list-unstyled row g-2 small">
                    @forelse($hotel->amenities()->where('type', \App\Models\Amenity::TYPE_HOTEL)->get() as $amenity)
                        <li class="col-6 col-md-4"><i class="fas fa-check text-success me-2"></i>{{ $amenity->name }}</li>
                    @empty
                        <li class="col-12 text-muted">—</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif

    @if($tab === 'gallery')
        <div class="row g-3">
            @forelse($roomType->images as $img)
                <div class="col-6 col-md-4">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($img->path) }}" class="img-fluid rounded-3 shadow-sm w-100" alt="{{ $img->caption ?? $roomType->name }}" style="height: 200px; object-fit: cover;">
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">
                    <i class="fas fa-images fa-3x mb-3"></i>
                    <p>No photos in gallery yet.</p>
                </div>
            @endforelse
        </div>
    @endif

    @if($tab === 'enquiry')
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if($enquiry_sent)
                    <div class="card border-0 bg-light rounded-3">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h3 class="h5">Enquiry sent</h3>
                            <p class="text-muted mb-0">We will get back to you shortly.</p>
                        </div>
                    </div>
                @else
                    <div class="card border shadow-sm rounded-3">
                        <div class="card-body p-4">
                            <h3 class="h6 mb-4">Make an enquiry – {{ $roomType->name }}</h3>
                            <form wire:submit="submitEnquiry">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="enquiry_name" placeholder="Name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" wire:model="enquiry_phone" placeholder="Phone">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" wire:model="enquiry_email" placeholder="Email" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Check-in <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" wire:model="enquiry_check_in" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Check-out <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" wire:model="enquiry_check_out" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Adult(s)</label>
                                        <select class="form-select" wire:model="enquiry_adults">
                                            @for($i = 1; $i <= 10; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Child(ren)</label>
                                        <select class="form-select" wire:model="enquiry_children">
                                            @for($i = 0; $i <= 6; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Room(s)</label>
                                        <select class="form-select" wire:model="enquiry_rooms">
                                            @for($i = 1; $i <= 5; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" wire:model="enquiry_message" rows="4" placeholder="Message"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-public btn-lg w-100">Enquire</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($tab === 'map')
        @if($hotel->map_embed_code)
            <div class="ratio ratio-16x9 rounded-3 overflow-hidden border shadow-sm">
                {!! $hotel->map_embed_code !!}
            </div>
        @else
            <div class="card border-0 bg-light rounded-3">
                <div class="card-body text-center py-5 text-muted">Map not available.</div>
            </div>
        @endif
    @endif

    {{-- Hotel settings details (displayed on every tab) --}}
    <div class="card border-0 bg-light rounded-3 mt-5">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-3">Hotel information</h3>
            <div class="row small">
                <div class="col-md-6">
                    <p class="mb-1 fw-semibold">{{ $hotel->name }}</p>
                    @if($hotel->address)<p class="text-muted mb-1"><i class="fas fa-map-marker-alt me-2"></i>{{ $hotel->address }}</p>@endif
                    @if($hotel->contact)<p class="text-muted mb-1"><i class="fas fa-phone me-2"></i>{{ $hotel->contact }}</p>@endif
                    @if($hotel->email)<p class="text-muted mb-1"><i class="fas fa-envelope me-2"></i><a href="mailto:{{ $hotel->email }}">{{ $hotel->email }}</a></p>@endif
                </div>
                @if($hotel->reservation_contacts)
                <div class="col-md-6 mt-2 mt-md-0">
                    <p class="text-muted mb-0 small" style="white-space: pre-line;">{{ $hotel->reservation_contacts }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
