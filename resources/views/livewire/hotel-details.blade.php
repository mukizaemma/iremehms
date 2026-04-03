<div class="bg-light rounded p-4">
    <h5 class="mb-4">Hotel details</h5>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $configTab === 'general' ? 'active' : '' }}" wire:click="setConfigTab('general')">General</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $configTab === 'about' ? 'active' : '' }}" wire:click="setConfigTab('about')">About the hotel</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $configTab === 'gallery' ? 'active' : '' }}" wire:click="setConfigTab('gallery')">Gallery</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $configTab === 'videos' ? 'active' : '' }}" wire:click="setConfigTab('videos')">Video tour</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $configTab === 'reviews' ? 'active' : '' }}" wire:click="setConfigTab('reviews')">Reviews</button>
        </li>
    </ul>

    @if($configTab === 'general')
    <form wire:submit.prevent="save">
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Hotel information</h6>
            </div>
            <div class="col-md-6">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="hotelName" wire:model="hotelName" placeholder="Hotel Name">
                    <label for="hotelName">Hotel name</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="hotelContact" wire:model="hotelContact" placeholder="Contact">
                    <label for="hotelContact">Contact</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="hotelEmail" wire:model="hotelEmail" placeholder="Email">
                    <label for="hotelEmail">Email</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="logo" class="form-label">Logo</label>
                    <input type="file" class="form-control" id="logo" wire:model="logo" accept="image/*">
                    @if($logoPreview)
                        <img src="{{ $logoPreview }}" alt="Logo Preview" class="mt-2" style="max-height: 100px;">
                    @endif
                </div>
            </div>
            <div class="col-12">
                <div class="form-floating mb-3">
                    <textarea class="form-control" id="hotelAddress" wire:model="hotelAddress" placeholder="Address" style="height: 100px"></textarea>
                    <label for="hotelAddress">Address</label>
                </div>
            </div>
            <div class="col-12">
                <label for="mapEmbedCode" class="form-label">Map (embedded code)</label>
                <textarea class="form-control font-monospace small" id="mapEmbedCode" wire:model="mapEmbedCode" placeholder="Paste iframe or embed code from Google Maps" rows="4"></textarea>
                <small class="text-muted">Paste the full iframe or embed HTML from Google Maps (Share → Embed a map) to show the hotel location on your public page.</small>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Branding</h6>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="primaryColor" class="form-label">Primary color</label>
                    <input type="color" class="form-control form-control-color" id="primaryColor" wire:model="primaryColor">
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="secondaryColor" class="form-label">Secondary color</label>
                    <input type="color" class="form-control form-control-color" id="secondaryColor" wire:model="secondaryColor">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-floating mb-3">
                    <select class="form-select" id="fontFamily" wire:model="fontFamily">
                        <option value="Heebo">Heebo</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Open Sans">Open Sans</option>
                        <option value="Lato">Lato</option>
                    </select>
                    <label for="fontFamily">Font family</label>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Login page background</h6>
                <p class="text-muted small mb-2">This image will appear on the login screen for all users.</p>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="loginBackground" class="form-label">Background image</label>
                    <input type="file" class="form-control" id="loginBackground" wire:model="loginBackground" accept="image/*">
                    <small class="text-muted d-block mt-1">Recommended size: at least 1200×800px.</small>
                </div>
            </div>
            <div class="col-md-6">
                @if($loginBackgroundPreview)
                    <div class="border rounded overflow-hidden" style="max-height: 180px;">
                        <img src="{{ $loginBackgroundPreview }}" alt="Login Background Preview" class="img-fluid w-100" style="object-fit: cover;">
                    </div>
                @else
                    <p class="text-muted fst-italic mt-2">No background image set yet.</p>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save hotel details</button>
        </div>
    </form>
    @endif

    @if($configTab === 'about')
    <div class="bg-white rounded p-4">
        <h6 class="mb-3">About the hotel – details for public page</h6>
        <form wire:submit.prevent="saveAbout">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Fax</label>
                    <input type="text" class="form-control" wire:model="fax" placeholder="e.g. 2500252584380">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reservation phone</label>
                    <input type="text" class="form-control" wire:model="reservation_phone" placeholder="e.g. 250788385300">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hotel type</label>
                    <input type="text" class="form-control" wire:model="hotel_type" placeholder="e.g. Hotels">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-in time</label>
                    <input type="text" class="form-control" wire:model="check_in_time" placeholder="e.g. 01:00 PM or 13:00">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-out time</label>
                    <input type="text" class="form-control" wire:model="check_out_time" placeholder="e.g. 11:00 AM or 11:00">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Hotel information (main description)</label>
                <textarea class="form-control summernote" data-field="hotel_information" rows="6" placeholder="Full description for About page">{!! $hotel_information ?? '' !!}</textarea>
            </div>
            <hr class="my-4">
            <h6 class="mb-3">Receipts &amp; reports (VAT display)</h6>
            <p class="text-muted small">VAT is always calculated in the background; these options only control whether guests and staff see VAT lines on print outputs.</p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="receiptShowVatHotelDetails" wire:model="receiptShowVatSetting">
                <label class="form-check-label" for="receiptShowVatHotelDetails">Show VAT breakdown on POS / guest receipts</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="reportsShowVatHotelDetails" wire:model="reportsShowVatSetting">
                <label class="form-check-label" for="reportsShowVatHotelDetails">Show VAT column and VAT totals on Front Office daily accommodation report</label>
            </div>
            <button type="submit" class="btn btn-primary">Save about the hotel</button>
        </form>
    </div>
    @endif

    @if($configTab === 'gallery')
    <div class="bg-white rounded p-4">
        <h6 class="mb-3">Hotel gallery</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Add image</label>
                <input type="file" class="form-control" wire:model="galleryImage" accept="image/*">
            </div>
            <div class="col-md-4">
                <label class="form-label">Caption</label>
                <input type="text" class="form-control" wire:model="galleryCaption" placeholder="Optional caption">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="button" class="btn btn-primary" wire:click="addGalleryImage" wire:loading.attr="disabled">Add to gallery</button>
            </div>
        </div>
        <div class="row g-3">
            @foreach(\App\Models\Hotel::getHotel()->galleryImages as $img)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card">
                        <img src="{{ $img->url }}" class="card-img-top" alt="{{ $img->caption }}" style="height: 140px; object-fit: cover;">
                        <div class="card-body py-2">
                            <p class="small mb-1 text-truncate">{{ $img->caption ?: '—' }}</p>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteGalleryImage({{ $img->id }})" wire:confirm="Remove this image?">Remove</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if(\App\Models\Hotel::getHotel()->galleryImages->isEmpty())
            <p class="text-muted small">No gallery images yet. Add images above.</p>
        @endif
    </div>
    @endif

    @if($configTab === 'videos')
    <div class="bg-white rounded p-4">
        <h6 class="mb-3">Video tour</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" wire:model="videoTitle" placeholder="e.g. Hotel tour">
            </div>
            <div class="col-md-4">
                <label class="form-label">Video URL (YouTube, Vimeo, etc.)</label>
                <input type="url" class="form-control" wire:model="videoUrl" placeholder="https://...">
            </div>
            <div class="col-12">
                <label class="form-label">Or paste embed code (iframe)</label>
                <textarea class="form-control font-monospace small" wire:model="videoEmbedCode" rows="3" placeholder="<iframe ...></iframe>"></textarea>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-primary" wire:click="addVideoTour" wire:loading.attr="disabled">Add video</button>
            </div>
        </div>
        <ul class="list-group">
            @foreach(\App\Models\Hotel::getHotel()->videoTours as $video)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $video->title ?: ($video->url ?: 'Video') }}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteVideoTour({{ $video->id }})" wire:confirm="Remove this video?">Remove</button>
                </li>
            @endforeach
        </ul>
        @if(\App\Models\Hotel::getHotel()->videoTours->isEmpty())
            <p class="text-muted small mt-2">No videos yet.</p>
        @endif
    </div>
    @endif

    @if($configTab === 'reviews')
    <div class="bg-white rounded p-4">
        <h6 class="mb-3">Guest reviews</h6>
        <p class="text-muted small mb-3">Reviews are submitted on the public page. Approve them here to show on the site.</p>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Guest</th><th>Rating</th><th>Comment</th><th>Date</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach(\App\Models\Hotel::getHotel()->reviews as $review)
                        <tr>
                            <td>{{ $review->guest_name }}@if($review->guest_email)<br><small class="text-muted">{{ $review->guest_email }}</small>@endif</td>
                            <td>{{ $review->rating }}/5</td>
                            <td class="small text-truncate" style="max-width: 200px;">{{ \Illuminate\Support\Str::limit($review->comment, 60) }}</td>
                            <td class="small">{{ $review->created_at->format('d/m/Y') }}</td>
                            <td>
                                @if($review->is_approved)
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-secondary">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if(!$review->is_approved)
                                    <button type="button" class="btn btn-sm btn-success" wire:click="approveReview({{ $review->id }})">Approve</button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="rejectReview({{ $review->id }})">Hide</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteReview({{ $review->id }})" wire:confirm="Delete this review?">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(\App\Models\Hotel::getHotel()->reviews->isEmpty())
            <p class="text-muted small">No reviews yet. Guests can submit reviews on the public booking page.</p>
        @endif
    </div>
    @endif
</div>
