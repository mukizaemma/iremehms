<div>
{{-- Hero slideshow with left-aligned text overlay --}}
<div id="heroSlideshow" class="carousel slide carousel-fade position-relative" data-bs-ride="carousel" data-bs-interval="5000" style="min-height: 480px;">
    <div class="carousel-inner h-100">
        <div class="carousel-item active position-relative" style="min-height: 480px;">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a0d9c8373bd?w=1200'); background-size: cover; background-position: center;"></div>
            <div class="position-absolute top-0 start-0 w-100 h-100 hero-overlay"></div>
        </div>
        <div class="carousel-item position-relative" style="min-height: 480px;">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200'); background-size: cover; background-position: center;"></div>
            <div class="position-absolute top-0 start-0 w-100 h-100 hero-overlay"></div>
        </div>
        <div class="carousel-item position-relative" style="min-height: 480px;">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=1200'); background-size: cover; background-position: center;"></div>
            <div class="position-absolute top-0 start-0 w-100 h-100 hero-overlay"></div>
        </div>
    </div>
    <button class="carousel-control-prev position-absolute" type="button" data-bs-target="#heroSlideshow" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next position-absolute" type="button" data-bs-target="#heroSlideshow" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>

    {{-- Left-aligned hero text --}}
    <div class="position-absolute bottom-0 start-0 end-0 p-4 p-md-5 pb-5" style="pointer-events: none;">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h1 class="text-white fw-bold display-5 mb-2">Your Comfortable Stay</h1>
                    <p class="text-white mb-1 fs-5">Comfort, convenience and city life in the heart of</p>
                    <p class="text-white fw-bold fs-4 mb-0">{{ $hotel->address ? explode(',', $hotel->address)[0] : 'Kigali' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Booking widget bar (below hero) --}}
<div class="bg-white border-bottom shadow-sm">
    <div class="container py-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md">
                <label class="form-label small mb-1 text-muted">Check In</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="check_in" min="{{ date('Y-m-d') }}">
            </div>
            <div class="col-6 col-md">
                <label class="form-label small mb-1 text-muted">Check Out</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="check_out">
            </div>
            <div class="col-4 col-md">
                <label class="form-label small mb-1 text-muted">Adult</label>
                <select class="form-select form-select-sm" wire:model.live="adults">
                    @for($i = 1; $i <= 6; $i++) <option value="{{ $i }}">{{ $i }}</option> @endfor
                </select>
            </div>
            <div class="col-4 col-md">
                <button type="button" class="btn btn-public w-100">Check Availability</button>
            </div>
            <div class="col-4 col-md">
                <a href="#rooms" class="small text-muted d-block mt-2"><i class="fas fa-tag me-1"></i>Promotional Code</a>
            </div>
        </div>
    </div>
</div>

{{-- Filter / options bar --}}
<div class="bg-light border-bottom py-2">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-4 small">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter me-1"></i>Filter Your Search
            </button>
            <a href="#rooms" class="text-dark text-decoration-none">Compare Rooms</a>
            <span class="text-muted">Rates are in {{ $this->currency }} ({{ $this->currencySymbol }})</span>
            <span class="ms-auto d-flex align-items-center gap-2">
                <span class="text-muted">Show Price:</span>
                <button type="button" class="btn btn-sm {{ $showPriceWholeStay ? 'btn-public' : 'btn-outline-secondary' }}" wire:click="$set('showPriceWholeStay', true)">Price For Whole Stay</button>
                <button type="button" class="btn btn-sm {{ !$showPriceWholeStay ? 'btn-public' : 'btn-outline-secondary' }}" wire:click="$set('showPriceWholeStay', false)">Price Per Night</button>
            </span>
        </div>
        <div class="collapse mt-2" id="filterCollapse">
            <p class="small text-muted mb-0">Use the room list below to compare room types and add rooms to your booking.</p>
        </div>
    </div>
</div>

<div class="container py-4 py-lg-5">
    <div class="row">
        <div class="col-lg-8" id="rooms">
            @forelse($this->roomTypes as $roomType)
                @php
                    $slug = $roomType->slug ?: \Illuminate\Support\Str::slug($roomType->name);
                    $img = $roomType->images->first();
                    $price = $this->getPricePerNight($roomType);
                    $displayPrice = $this->getDisplayPrice($roomType);
                    $roomsLeft = $roomType->rooms_count ?? $roomType->rooms()->count();
                @endphp
                <div class="card card-room border-0 shadow-sm mb-4 rounded-0 overflow-hidden bg-light">
                    <div class="row g-0">
                        <div class="col-md-4 position-relative">
                            @if($img)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($img->path) }}" class="w-100" style="height: 240px; object-fit: cover;" alt="{{ $roomType->name }}">
                            @else
                                <div class="bg-secondary d-flex align-items-center justify-content-center text-white" style="height: 240px;"><i class="fas fa-bed fa-3x"></i></div>
                            @endif
                            <div class="position-absolute bottom-0 start-0 end-0 room-card-image-caption">{{ strtoupper($roomType->name) }}</div>
                        </div>
                        <div class="col-md-8">
                            <div class="card-body d-flex flex-column h-100 py-3">
                                <div class="d-flex justify-content-between align-items-start flex-grow-1 gap-3">
                                    <div>
                                        <h3 class="h5 fw-bold text-dark mb-2">{{ $roomType->name }}</h3>
                                        <p class="text-muted small mb-1">Room Capacity : {{ $adults }} <i class="fas fa-user ms-1 me-2 text-muted"></i> {{ $children }} <i class="fas fa-child text-muted"></i></p>
                                        <p class="small text-muted mb-2">Room Rates Inclusive of Tax</p>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        @if($displayPrice !== null)
                                        <p class="mb-0 fw-bold text-dark fs-5">{{ $this->currencySymbol }} {{ number_format($displayPrice, 2) }}</p>
                                        <i class="fas fa-info-circle text-muted small" title="Inclusive of tax"></i>
                                        <p class="small text-muted mb-1">Price for {{ $showPriceWholeStay ? $this->nights . ' night(s)' : '1 Night' }}</p>
                                        <p class="small text-muted mb-1">{{ $adults }} Adults, {{ $children }} Child, 1 Room</p>
                                        @else
                                        <p class="small text-muted mb-0">Contact for price</p>
                                        @endif
                                        <div class="form-check form-check-inline small mb-0 mt-1">
                                            <input type="checkbox" class="form-check-input" id="compare-{{ $roomType->id }}" disabled>
                                            <label class="form-check-label text-muted" for="compare-{{ $roomType->id }}">Add To Compare</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 pt-2 border-top">
                                    <span>
                                        <a href="{{ route('public.room', ['slug' => $this->slug, 'roomSlug' => $slug]) }}" class="btn btn-link btn-sm p-0 text-dark text-decoration-none">Room Info</a>
                                        <span class="text-muted mx-1">•</span>
                                        <a href="{{ route('public.room', ['slug' => $this->slug, 'roomSlug' => $slug]) }}#enquiry" class="btn btn-link btn-sm p-0 text-dark text-decoration-none">Enquire</a>
                                    </span>
                                    <span class="d-flex align-items-center gap-2">
                                        @if($roomsLeft > 0)
                                            @if($roomsLeft <= 1)
                                                <span class="availability-last small">Only 1 Room Left</span>
                                            @else
                                                <span class="availability-hurry small">Hurry! {{ $roomsLeft }} Rooms Left</span>
                                            @endif
                                        @endif
                                        @if($price !== null)
                                            <button type="button" class="btn btn-public btn-sm" wire:click="addRoom({{ $roomType->id }})">Add Room</button>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card border shadow-sm rounded-3">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fas fa-bed fa-3x mb-3 opacity-50"></i>
                        <p class="mb-2">No rooms are currently displayed.</p>
                        <p class="small mb-0">Room types and rates are set in <strong>Front Office → Room Types</strong>. Ensure room types are active and have at least one room. Contact details are shown below.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-0 sticky-top bg-light" style="top: 1rem;">
                <div class="card-body py-4">
                    <h3 class="h6 mb-3 fw-bold">Booking Summary</h3>
                    <p class="small text-muted mb-1">Dates: {{ $check_in }} – {{ $check_out }}</p>
                    <p class="small text-muted mb-3">{{ $this->nights }} night(s)</p>

                    @if(count($selectedRooms) > 0)
                        <ul class="list-unstyled small mb-3">
                            @foreach($selectedRooms as $index => $room)
                                <li class="d-flex justify-content-between align-items-start mb-2">
                                    <span>
                                        <strong>{{ $room['name'] }}</strong><br>
                                        <span class="text-muted">{{ $room['qty'] }} room(s) · {{ $room['adults'] }} adults, {{ $room['children'] }} child(ren)</span>
                                    </span>
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0" wire:click="removeRoom({{ $index }})" title="Remove">×</button>
                                </li>
                            @endforeach
                        </ul>
                        <hr>
                        <p class="d-flex justify-content-between mb-3">
                            <strong>Total ({{ $this->nights }} night(s))</strong>
                            <strong>{{ $this->currencySymbol }}{{ number_format($this->totalPrice, 2) }}</strong>
                        </p>
                        <a href="{{ route('public.reservation', ['slug' => $this->slug]) }}?check_in={{ urlencode($check_in) }}&check_out={{ urlencode($check_out) }}&rooms={{ urlencode(json_encode($selectedRooms)) }}" class="btn btn-public w-100">Book</a>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-bed fa-4x mb-3 opacity-25" style="color: #999;"></i>
                            <p class="mb-0 fw-medium text-secondary">No Room(s) Selected</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Hotel details (from hotel settings) --}}
    <section class="py-5 border-top" id="hotel-info">
        <h2 class="h5 mb-3">Hotel information</h2>
        <div class="card border-0 bg-light rounded-3">
            <div class="card-body">
                <div class="row align-items-start">
                    @if($hotel->logo)
                        <div class="col-auto mb-3 mb-md-0">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($hotel->logo) }}" alt="{{ $hotel->name }}" class="rounded" style="max-height: 80px; width: auto;">
                        </div>
                    @endif
                    <div class="col">
                        <h3 class="h6 fw-bold mb-2">{{ $hotel->name }}</h3>
                        @if($hotel->address)
                            <p class="small text-muted mb-1"><i class="fas fa-map-marker-alt me-2"></i>{{ $hotel->address }}</p>
                        @endif
                        @if($hotel->contact)
                            <p class="small text-muted mb-1"><i class="fas fa-phone me-2"></i>{{ $hotel->contact }}</p>
                        @endif
                        @if(!empty($hotel->fax))
                            <p class="small text-muted mb-1"><i class="fas fa-fax me-2"></i>{{ $hotel->fax }}</p>
                        @endif
                        @if(!empty($hotel->reservation_phone))
                            <p class="small text-muted mb-1">Reservation: {{ $hotel->reservation_phone }}</p>
                        @endif
                        @if($hotel->email)
                            <p class="small text-muted mb-1"><i class="fas fa-envelope me-2"></i><a href="mailto:{{ $hotel->email }}" class="text-muted">{{ $hotel->email }}</a></p>
                        @endif
                        @if(!empty($hotel->hotel_type))
                            <p class="small text-muted mb-1">Hotel type: {{ $hotel->hotel_type }}</p>
                        @endif
                        @if(!empty($hotel->check_in_time) || !empty($hotel->check_out_time))
                            <p class="small text-muted mb-1">Check-in: {{ $hotel->check_in_time ?? '—' }} · Check-out: {{ $hotel->check_out_time ?? '—' }}</p>
                        @endif
                        <p class="small text-muted mb-0">Rates in {{ $hotel->getCurrency() }} ({{ $hotel->getCurrencySymbol() }})</p>
                    </div>
                </div>
                @if($hotel->reservation_contacts)
                    <hr class="my-3">
                    <h4 class="h6 fw-bold mb-2">Reservation contacts</h4>
                    <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->reservation_contacts }}</div>
                @endif
            </div>
        </div>

        @if(!empty($hotel->hotel_information))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">About us</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->hotel_information }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->landmarks_nearby))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Important landmarks nearby</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->landmarks_nearby }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->facilities))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Facilities</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->facilities }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->check_in_policy))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Check-in policy</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->check_in_policy }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->children_extra_guest_details))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Children & extra guests</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->children_extra_guest_details }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->parking_policy))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Parking</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->parking_policy }}</div>
            </div>
        </div>
        @endif
        @if(!empty($hotel->things_to_do))
        <div class="card border-0 bg-white rounded-3 mt-3">
            <div class="card-body">
                <h4 class="h6 fw-bold mb-2">Things to do</h4>
                <div class="small text-muted" style="white-space: pre-line;">{{ $hotel->things_to_do }}</div>
            </div>
        </div>
        @endif
    </section>

    {{-- Gallery --}}
    @if($hotel->galleryImages->isNotEmpty())
    <section class="py-5 border-top" id="gallery">
        <h2 class="h5 mb-3">Gallery</h2>
        <div class="row g-3">
            @foreach($hotel->galleryImages as $img)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ $img->url }}" target="_blank" rel="noopener" class="d-block rounded-3 overflow-hidden border shadow-sm">
                        <img src="{{ $img->url }}" alt="{{ $img->caption ?? 'Gallery' }}" class="img-fluid w-100" style="height: 200px; object-fit: cover;">
                        @if($img->caption)
                            <div class="p-2 small text-muted text-center bg-light">{{ $img->caption }}</div>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Video tour --}}
    @if($hotel->videoTours->isNotEmpty())
    <section class="py-5 border-top" id="video-tour">
        <h2 class="h5 mb-3">Video tour</h2>
        <div class="row g-4">
            @foreach($hotel->videoTours as $video)
                <div class="col-12">
                    @if($video->embed_code)
                        <div class="ratio ratio-16x9 rounded-3 overflow-hidden border shadow-sm">
                            {!! $video->embed_code !!}
                        </div>
                    @elseif($video->url)
                        <p class="small mb-1">{{ $video->title }}</p>
                        <a href="{{ $video->url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">Watch video</a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Reviews --}}
    <section class="py-5 border-top" id="reviews">
        <h2 class="h5 mb-3">Guest reviews</h2>
        @if($hotel->approvedReviews->isNotEmpty())
            <div class="mb-4">
                @foreach($hotel->approvedReviews as $review)
                    <div class="card border-0 bg-light rounded-3 mb-2">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong>{{ $review->guest_name }}</strong>
                                <span class="small text-warning">@for($i = 1; $i <= 5; $i++)<i class="fas fa-star{{ $i <= $review->rating ? '' : '-o' }}"></i>@endfor</span>
                            </div>
                            @if($review->comment)
                                <p class="small text-muted mb-0 mt-1">{{ $review->comment }}</p>
                            @endif
                            <p class="small text-muted mb-0 mt-1">{{ $review->created_at->format('M j, Y') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="card border shadow-sm rounded-3">
            <div class="card-body">
                <h4 class="h6 mb-3">Share your experience</h4>
                @if($reviewSubmitted)
                    <p class="text-success small mb-0">{{ session('review_message', 'Thank you for your review. It will appear after approval.') }}</p>
                @else
                    <form wire:submit="submitReview">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Your name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" wire:model="reviewGuestName" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Email</label>
                                <input type="email" class="form-control form-control-sm" wire:model="reviewGuestEmail">
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Rating</label>
                                <select class="form-select form-select-sm w-auto" wire:model="reviewRating">
                                    @for($i = 5; $i >= 1; $i--) <option value="{{ $i }}">{{ $i }} star{{ $i > 1 ? 's' : '' }}</option> @endfor
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Your review</label>
                                <textarea class="form-control form-control-sm" wire:model="reviewComment" rows="3" placeholder="Tell others about your stay..."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-public btn-sm">Submit review</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </section>

    {{-- Contact --}}
    <section class="py-5 border-top" id="contact">
        <h2 class="h5 mb-3">Contact us</h2>
        <div class="row">
            <div class="col-md-6">
                @if($hotel->reservation_contacts || $hotel->email || $hotel->contact || $hotel->address)
                    <ul class="list-unstyled mb-0">
                        @if($hotel->reservation_contacts)
                            <li class="small text-muted mb-2" style="white-space: pre-line;">{{ $hotel->reservation_contacts }}</li>
                        @endif
                        @if($hotel->email)
                            <li class="small mb-1"><i class="fas fa-envelope me-2 text-muted"></i><a href="mailto:{{ $hotel->email }}">{{ $hotel->email }}</a></li>
                        @endif
                        @if($hotel->contact)
                            <li class="small mb-1"><i class="fas fa-phone me-2 text-muted"></i>{{ $hotel->contact }}</li>
                        @endif
                        @if($hotel->address)
                            <li class="small mb-1"><i class="fas fa-map-marker-alt me-2 text-muted"></i>{{ $hotel->address }}</li>
                        @endif
                    </ul>
                @else
                    <p class="small text-muted mb-0">Contact details can be set in Hotel settings (Front Office).</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Map --}}
    @if($hotel->map_embed_code)
    <section class="py-5 border-top" id="map">
        <h2 class="h5 mb-3">Find us</h2>
        <div class="ratio ratio-16x9 rounded-3 overflow-hidden border shadow-sm">
            {!! $hotel->map_embed_code !!}
        </div>
    </section>
    @endif
</div>
</div>
