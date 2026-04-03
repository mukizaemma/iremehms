<div class="container py-4 py-lg-5">
    <a href="{{ route('public.booking', ['slug' => $slug]) }}" class="btn btn-outline-secondary btn-sm mb-3">← Back to rooms</a>

    @if($submitted)
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow rounded-3">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h1 class="h4 mb-2">Request received</h1>
                        <p class="text-muted mb-4">{{ session('booking_message') }}</p>
                        <a href="{{ route('public.booking', ['slug' => $slug]) }}" class="btn btn-public">Back to home</a>
                    </div>
                </div>
            </div>
        </div>
    @elseif(count($selectedRooms) === 0)
        <div class="alert alert-info">No rooms selected. <a href="{{ route('public.booking', ['slug' => $slug]) }}">Choose your rooms</a> first.</div>
    @else
        <h1 class="h4 mb-4">Complete your booking</h1>

        <div class="row">
            <div class="col-lg-7">
                <div class="card border shadow-sm rounded-3 mb-4">
                    <div class="card-body">
                        <h2 class="h6 mb-3">Guest information</h2>
                        <form wire:submit="submitBookingRequest">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" wire:model="guest_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" wire:model="guest_email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" wire:model="guest_phone">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" wire:model="guest_address">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" wire:model="guest_country">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Special requests</label>
                                    <textarea class="form-control" wire:model="guest_special_requests" rows="3"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" wire:model="accept_terms" id="acceptTerms" required>
                                        <label class="form-check-label small" for="acceptTerms">I acknowledge and accept the cancellation policy and hotel terms. <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-public btn-lg w-100">Submit booking request</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 bg-light rounded-3">
                    <div class="card-body">
                        <h3 class="h6">Hotel policy & booking conditions</h3>
                        <p class="small text-muted mb-0">Booking requests are subject to availability. The hotel will confirm your reservation and payment options. Direct bookings may have flexible cancellation – please confirm at time of booking.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border shadow-sm rounded-3 sticky-top" style="top: 1rem;">
                    <div class="card-body">
                        <h3 class="h6 mb-3">Your booking summary</h3>
                        <p class="small fw-semibold mb-2">{{ $hotel->name }}</p>
                        @if($hotel->address)<p class="small text-muted mb-1"><i class="fas fa-map-marker-alt me-2"></i>{{ $hotel->address }}</p>@endif
                        @if($hotel->contact)<p class="small text-muted mb-1"><i class="fas fa-phone me-2"></i>{{ $hotel->contact }}</p>@endif
                        @if($hotel->email)<p class="small text-muted mb-1"><i class="fas fa-envelope me-2"></i>{{ $hotel->email }}</p>@endif
                        @if($hotel->reservation_contacts)<p class="small text-muted mb-2 mt-2" style="white-space: pre-line;">{{ $hotel->reservation_contacts }}</p>@endif
                        <hr>
                        <p class="small mb-1">Check-in: <strong>{{ $check_in }}</strong></p>
                        <p class="small mb-1">Check-out: <strong>{{ $check_out }}</strong></p>
                        <p class="small mb-3">{{ $nights }} night(s)</p>
                        <hr>
                        @foreach($selectedRooms as $room)
                            <p class="small mb-1"><strong>{{ $room['name'] ?? 'Room' }}</strong> × {{ $room['qty'] ?? 1 }}</p>
                            <p class="small text-muted mb-2">{{ $room['adults'] ?? 2 }} adults, {{ $room['children'] ?? 0 }} children</p>
                        @endforeach
                        <hr>
                        <p class="d-flex justify-content-between mb-0">
                            <strong>Total</strong>
                            <strong>{{ $this->currencySymbol }}{{ number_format($this->totalPrice, 2) }}</strong>
                        </p>
                        <p class="small text-muted mt-2 mb-0">Pay at hotel or as advised by the property.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
