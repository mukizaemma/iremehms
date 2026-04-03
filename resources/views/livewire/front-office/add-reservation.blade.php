<div class="add-reservation">
    @livewire('shift-acknowledgment-banner', ['targetScope' => \App\Models\OperationalShift::SCOPE_FRONT_OFFICE, 'onlyWhenMissing' => true])
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
            <div class="d-flex align-items-center">
                <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm me-3"><i class="fa fa-arrow-left"></i></a>
                <h5 class="mb-0">Add reservation</h5>
            </div>
        </div>
        @include('livewire.front-office.partials.front-office-quick-nav')
    </div>

    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($reserve_success && $reservation_number)
        <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center">
                <i class="fa fa-check-circle fa-2x me-3"></i>
                <div>
                    <strong>Reservation created.</strong> Reservation number: <strong>{{ $reservation_number }}</strong>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('front-office.add-reservation') }}" class="btn btn-outline-light btn-sm text-success border-success">
                    <i class="fa fa-plus me-1"></i>New reservation
                </a>
                <a href="{{ route('front-office.rooms') }}" class="btn btn-outline-light btn-sm text-success border-success">
                    <i class="fa fa-bed me-1"></i>Go to rooms
                </a>
                <a href="{{ route('front-office.reservations') }}?search={{ urlencode($reservation_number) }}" class="btn btn-outline-light btn-sm text-success border-success">
                    <i class="fa fa-list me-1"></i>All reservations
                </a>
                <a href="{{ route('front-office.reservations') }}?search={{ urlencode($reservation_number) }}" class="btn btn-success btn-sm">
                    <i class="fa fa-folder-open me-1"></i>Open this reservation
                </a>
            </div>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left column: Booking details + Guest + Other confirmations --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light"><strong>Booking details</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small d-block">Booking type</label>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="booking_type" id="bookingSingle" value="single" @if(!$is_group_booking) checked @endif wire:click="$set('is_group_booking', false)">
                                    <label class="form-check-label" for="bookingSingle">Single booking</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="booking_type" id="bookingGroup" value="group" @if($is_group_booking) checked @endif wire:click="$set('is_group_booking', true)">
                                    <label class="form-check-label" for="bookingGroup">Group booking</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Check-in</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="check_in_date" min="{{ now()->toDateString() }}">
                            <input type="time" class="form-control form-control-sm mt-1" wire:model="check_in_time">
                        </div>
                        <div class="col-6 col-md-2 d-flex align-items-end">
                            <span class="badge bg-primary py-2 px-3">{{ $this->computeNights() }} Nights</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Check-out</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="check_out_date" min="{{ now()->toDateString() }}">
                            <input type="time" class="form-control form-control-sm mt-1" wire:model="check_out_time">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label small">Adults</label>
                            <input type="number" class="form-control form-control-sm" wire:model.live="adult" min="1" placeholder="1" title="Adults">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label small">Children</label>
                            <input type="number" class="form-control form-control-sm" wire:model.live="child" min="0" placeholder="0">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label small">Room(s)</label>
                            <input type="number" class="form-control form-control-sm" wire:model.live="rooms_count" min="1">
                        </div>
                        @if($occupancy_warning || $rooms_availability_warning || $missing_occupancy_room_type)
                            <div class="col-12">
                                @if($missing_occupancy_room_type)
                                    <div class="alert alert-warning py-2 small mb-2">
                                        Occupancy (max adults / children) is not configured for room type <strong>{{ $missing_occupancy_room_type }}</strong>.
                                        <button type="button" class="btn btn-outline-dark btn-sm ms-2" wire:click="sendOccupancySetupRequest">
                                            Request manager/admin to set occupancy
                                        </button>
                                    </div>
                                @endif
                                @if($occupancy_warning)
                                    <div class="alert alert-warning py-2 small mb-2 d-flex justify-content-between align-items-center">
                                        <span>{{ $occupancy_warning }}</span>
                                        @if($suggested_group_rooms)
                                            <button type="button" class="btn btn-outline-primary btn-sm ms-2" wire:click="convertToSuggestedGroupBooking">
                                                Convert to group booking ({{ $suggested_group_rooms }} room(s))
                                            </button>
                                        @endif
                                    </div>
                                @endif
                                @if($rooms_availability_warning)
                                    <div class="alert alert-info py-2 small mb-0">
                                        {{ $rooms_availability_warning }} Consider adding another room category to accommodate the extra guests.
                                    </div>
                                @endif
                            </div>
                        @endif
                        <div class="col-12 col-md-4">
                            <label class="form-label small">Reservation Type</label>
                            <select class="form-select form-select-sm" wire:model="reservation_type">
                                @foreach(\App\Livewire\FrontOffice\AddReservation::RESERVATION_TYPES as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Room type section --}}
                        @if($check_in_date && $check_out_date && $check_in_date < $check_out_date && collect($roomTypesWithCount)->sum('available_count') === 0)
                            <div class="col-12">
                                <div class="alert alert-warning py-2 small mb-0">No rooms available for these dates. Try different check-in or check-out dates.</div>
                            </div>
                        @endif
                        @if($is_group_booking)
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Room types & occupancy</label>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Room type</th>
                                                <th class="text-center" style="width: 100px;">No. of rooms</th>
                                                <th class="text-center" style="width: 90px;">Adults</th>
                                                <th class="text-center" style="width: 90px;">Children</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group_room_rows as $index => $row)
                                                <tr>
                                                    <td>
                                                        <select class="form-select form-select-sm" wire:model.live="group_room_rows.{{ $index }}.room_type_id">
                                                            <option value="">-Select-</option>
                                                            @foreach($roomTypesWithCount as $rt)
                                                                <option value="{{ $rt['id'] }}">{{ $rt['name'] }} ({{ $rt['available_count'] }} available)</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.quantity" min="1" max="99">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.adults" min="0" placeholder="0">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.children" min="0" placeholder="0">
                                                    </td>
                                                    <td>
                                                        @if(count($group_room_rows) > 1)
                                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" wire:click="removeGroupRow({{ $index }})" title="Remove row"><i class="fa fa-times"></i></button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mb-2" wire:click="addGroupRow"><i class="fa fa-plus me-1"></i>Add room type row</button>
                                <div>
                                    <label class="form-label small">Rate type</label>
                                    <select class="form-select form-select-sm" wire:model.live="rate_type" style="max-width: 200px;">
                                        @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Room type</label>
                                <select class="form-select form-select-sm" wire:model.live="room_type_id">
                                    <option value="">-Select-</option>
                                    @foreach($roomTypesWithCount as $rt)
                                        <option value="{{ $rt['id'] }}">{{ $rt['name'] }} ({{ $rt['available_count'] }} available)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Rate type</label>
                                <select class="form-select form-select-sm" wire:model.live="rate_type">
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Room / unit</label>
                                <select class="form-select form-select-sm" wire:model.live="room_unit_id">
                                    <option value="">Select room or unit</option>
                                    @foreach($roomUnits as $u)
                                        <option value="{{ $u->id }}">{{ $u->label }}</option>
                                    @endforeach
                                </select>
                                @if($room_unit_id && $show_overlap_modal && count($overlap_reservations) > 0)
                                    <div class="alert alert-warning py-2 small mt-2 mb-0">
                                        Selected dates overlap with existing booking(s).
                                        <strong>{{ count($overlap_reservations) }} booking(s)</strong> found. Review the list.
                                    </div>
                                @endif
                                @if($room_unit_id && $show_overlap_modal && count($overlap_reservations) > 0)
                                    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.35);">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Overlapping reservations detected</h5>
                                                    <button type="button" class="btn-close" wire:click="closeOverlapModal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted small mb-3">
                                                        Your selected dates overlap with existing bookings for this room/unit. Choose an existing booking to add this guest.
                                                    </p>
                                                    <div class="list-group">
                                                        @foreach($overlap_reservations as $or)
                                                            <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                                                                <div>
                                                                    <div class="fw-semibold">{{ $or['guest_name'] }}</div>
                                                                    <div class="small text-muted">
                                                                        {{ $or['reservation_number'] }} · {{ $or['from'] }} → {{ $or['to'] }} · {{ $or['status'] }}
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                                                            wire:click="addGuestToExistingBooking({{ (int) $or['reservation_id'] }})">
                                                                        Add guest to this booking
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeOverlapModal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        <div class="col-12 col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="extra_bed" id="extraBed">
                                <label class="form-check-label small" for="extraBed">Extra bed requested (no additional room)</label>
                            </div>
                        </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small">Price per night ({{ $currency }}) <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="number" class="form-control form-control-sm" style="max-width: 140px;" wire:model.live="rate_rs" step="0.01" min="0" placeholder="e.g. 50000">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" wire:model.live="rate_tax_inc" id="rateTaxInc">
                                    <label class="form-check-label small" for="rateTaxInc">Tax inclusive</label>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light"><strong>Guest information</strong></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small">Business Source</label>
                            <select class="form-select form-select-sm" wire:model.live="business_source">
                                <option value="">-Select-</option>
                                @foreach(\App\Livewire\FrontOffice\AddReservation::BUSINESS_SOURCE_OPTIONS as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small">Type</label>
                            <select class="form-select form-select-sm" wire:model="guest_company_type">
                                <option value="">-Select-</option>
                                @foreach(\App\Livewire\FrontOffice\AddReservation::COMPANY_TYPES as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($business_source === 'OTA')
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Select OTA</label>
                                <select class="form-select form-select-sm" wire:model="selected_ota">
                                    <option value="">-Select OTA-</option>
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::KNOWN_OTAS as $ota)
                                        <option value="{{ $ota }}">{{ $ota }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if($business_source === 'Social media')
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Social media page / account</label>
                                <input type="text" class="form-control form-control-sm" wire:model="social_media_page" placeholder="e.g. Instagram @hotel">
                            </div>
                        @endif
                        @if($business_source === 'Referral')
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Referral name</label>
                                <input type="text" class="form-control form-control-sm" wire:model="referral_name" placeholder="Person who referred">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Referral phone</label>
                                <input type="text" class="form-control form-control-sm" wire:model="referral_phone" placeholder="Phone">
                            </div>
                        @endif
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small">Company / group name</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_company_name" placeholder="Company or group name">
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-end">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="checkbox" wire:model.live="use_existing_client" id="useExistingClient">
                                <label class="form-check-label small" for="useExistingClient">Existing client</label>
                            </div>
                            @if($use_existing_client)
                                <div class="flex-grow-1 position-relative">
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="guest_name" placeholder="Type name or phone to search existing guests">
                                    @if($guest_search_open && count($guest_suggestions) > 0)
                                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; max-height: 220px; overflow-y: auto;">
                                            @foreach($guest_suggestions as $g)
                                                <button type="button" class="list-group-item list-group-item-action list-group-item-light small py-2" wire:click="selectGuest({{ $g['id'] }})">
                                                    {{ $g['name'] ?? 'Guest' }} @if(!empty($g['mobile'])) — {{ $g['mobile'] }} @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Guest name @if(!$is_group_booking)<span class="text-danger">*</span>@endif</label>
                            <div class="d-flex gap-1 position-relative">
                                <select class="form-select form-select-sm" style="max-width: 80px;" wire:model="guest_salutation">
                                    <option>Mr.</option>
                                    <option>Ms.</option>
                                    <option>Mrs.</option>
                                    <option>Dr.</option>
                                </select>
                                <div class="flex-grow-1 position-relative">
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="guest_name" placeholder="Full name (type to search)">
                                    @if($guest_search_open && count($guest_suggestions) > 0)
                                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 10; max-height: 200px; overflow-y: auto;">
                                            @foreach($guest_suggestions as $g)
                                                <button type="button" class="list-group-item list-group-item-action list-group-item-light small py-2" wire:click="selectGuest({{ $g['id'] }})">
                                                    {{ $g['name'] }} — {{ $g['mobile'] ?? '' }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" title="Clear" wire:click="$set('guest_name', ''); closeGuestSearch"><i class="fa fa-times"></i></button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" title="New guest"><i class="fa fa-user-plus"></i></button>
                                <button type="button" class="btn btn-success btn-sm" wire:click="confirmPastClient" title="Confirm past client added to form">Confirm</button>
                            </div>
                            @if($guest_confirmed)
                                <small class="text-success d-block">Past client confirmed.</small>
                            @endif
                            @if($existing_guest_stay_count)
                                <small class="text-muted d-block">Stays at this hotel: {{ $existing_guest_stay_count }} @if($existing_guest_referral_count) · Referrals: {{ $existing_guest_referral_count }} @endif</small>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Mobile</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_mobile" placeholder="Mobile">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" class="form-control form-control-sm" wire:model="guest_email" placeholder="Email">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Address</label>
                            <textarea class="form-control form-control-sm" wire:model="guest_address" rows="2" placeholder="Address"></textarea>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Country</label>
                            <input type="text" class="form-control form-control-sm" list="country-list" wire:model.live.debounce.200ms="guest_country" placeholder="Type to search country" autocomplete="off">
                            <datalist id="country-list">
                                @foreach($allCountries as $c)
                                    <option value="{{ $c }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">State</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_state" placeholder="State">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">City</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_city" placeholder="City">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small">Zip</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_zip" placeholder="Zip">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">ID / Passport number</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_id_number" placeholder="ID or Passport">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Profession</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_profession" placeholder="Profession">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Stay purpose</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_stay_purpose" placeholder="e.g. Business, Leisure">
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" wire:click="reserve" wire:loading.attr="disabled" @if($reserve_success) disabled @endif>
                                <span wire:loading.remove wire:target="reserve"><i class="fa fa-save me-1"></i>Save Booking</span>
                                <span wire:loading wire:target="reserve"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light"><strong>Other confirmations</strong></div>
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="email_booking_vouchers" id="emailVouchers">
                        <label class="form-check-label" for="emailVouchers">Email Booking Vouchers</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="send_email_at_checkout" id="emailCheckout">
                        <label class="form-check-label" for="emailCheckout">Send email at Check-out</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" wire:model="access_to_guest_portal" id="guestPortal">
                        <label class="form-check-label" for="guestPortal">Access To Guest Portal</label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column: Billing summary + Payment + Reserve --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Billing summary</strong>
                    @if(!$reserve_success)
                        <button type="button" class="btn btn-success btn-sm" wire:click="reserve" wire:loading.attr="disabled">Confirm Booking</button>
                    @endif
                </div>
                <div class="card-body">
                    @if($reserve_success && $reservation_number)
                        <p class="text-success mb-3"><strong>Reservation number: {{ $reservation_number }}</strong></p>
                        <a href="{{ route('module.show', 'front-office') }}" class="btn btn-outline-primary btn-sm">View on calendar</a>
                    @endif
                    <div class="mb-3">
                        <span class="text-muted small">Check-in</span> {{ $check_in_date ? \Carbon\Carbon::parse($check_in_date)->format('d/m/Y') : '—' }}<br>
                        <span class="text-muted small">Check-out</span> {{ $check_out_date ? \Carbon\Carbon::parse($check_out_date)->format('d/m/Y') : '—' }}
                    </div>
                    <div class="small text-muted mb-2">
                        @if($is_group_booking)
                            Group: {{ count(array_filter($group_room_rows, fn($r) => !empty($r['room_type_id']))) }} room type(s), {{ $this->computeNights() }} night(s)
                        @else
                            @php
                                $nights = $this->computeNights();
                                $rate = (float) preg_replace('/[^0-9.]/', '', $rate_rs ?? '');
                                $rooms = max(1, (int) ($rooms_count ?? 1));
                            @endphp
                            @if($nights > 0 && $rate >= 0)
                                {{ $nights }} night(s) × {{ number_format($rate, 0) }} × {{ $rooms }} room(s)
                            @else
                                Set check-in, check-out and nightly rate to see breakdown.
                            @endif
                        @endif
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td>Room charges</td>
                            <td class="text-end">{{ $currency }} {{ number_format($this->getRoomChargesTotal(), 2) }}</td>
                        </tr>
                        <tr>
                            <td>VAT (18%, to remit to RRA) @if($tax_exempt)<span class="text-muted">(exempt)</span>@endif</td>
                            <td class="text-end">{{ $currency }} {{ number_format($this->getTaxesTotal(), 2) }}</td>
                        </tr>
                        <tr class="border-top">
                            <td><strong>Total due</strong></td>
                            <td class="text-end text-primary fw-bold">{{ $currency }} {{ number_format($this->getDueAmount(), 2) }}</td>
                        </tr>
                        @php
                            $paid = 0;
                            if ($use_international_currency && $amount_in_local !== '' && is_numeric($amount_in_local)) {
                                $paid = (float) $amount_in_local;
                            } elseif ($payment_amount !== '' && is_numeric($payment_amount)) {
                                $paid = (float) $payment_amount;
                            }
                            $balanceDue = max(0, $this->getDueAmount() - $paid);
                        @endphp
                        @if($paid > 0)
                            <tr>
                                <td>Amount paid</td>
                                <td class="text-end text-success">{{ $currency }} {{ number_format($paid, 2) }}</td>
                            </tr>
                            <tr class="border-top">
                                <td><strong>Balance due</strong></td>
                                <td class="text-end fw-bold">{{ $currency }} {{ number_format($balanceDue, 2) }}</td>
                            </tr>
                        @endif
                    </table>
                    <div class="mt-3">
                        <label class="form-label small">Bill To</label>
                        <select class="form-select form-select-sm" wire:model="bill_to">
                            <option>Guest</option>
                            <option>Company</option>
                        </select>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" wire:model.live="tax_exempt" id="taxExempt">
                        <label class="form-check-label small" for="taxExempt">Tax exclusive (remove VAT from this sale)</label>
                    </div>

                    <hr>
                    <strong class="small">Payment</strong>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" wire:model="payment_mode_enabled" id="paymentMode">
                        <label class="form-check-label small" for="paymentMode">Payment Mode</label>
                    </div>
                    @if($payment_mode_enabled)
                            <div class="mt-2">
                                <label class="form-label small">Payment type</label>
                                <select class="form-select form-select-sm" wire:model.live="payment_unified">
                                @foreach(\App\Support\PaymentCatalog::unifiedAccommodationOptions() as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                                </select>
                                @error('payment_unified') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            @if(\App\Support\PaymentCatalog::unifiedChoiceRequiresClientDetails($payment_unified ?? ''))
                            <div class="mt-2">
                                <label class="form-label small">Client / account details <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-sm" wire:model="payment_client_reference" rows="2" placeholder="Required for pending or on-account payments"></textarea>
                                @error('payment_client_reference') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            @endif
                        @if(!$use_international_currency)
                            <div class="mt-2">
                                <label class="form-label small">Amount paid now ({{ $currency }})</label>
                                <input type="number" class="form-control form-control-sm" wire:model.live="payment_amount" step="0.01" min="0" placeholder="0">
                                <small class="text-muted">Optional. Summary above updates as you type.</small>
                            </div>
                        @endif
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" wire:model="use_international_currency" id="intlCurrency">
                            <label class="form-check-label small" for="intlCurrency">Pay in international currency</label>
                        </div>
                        @if($use_international_currency)
                            <div class="row g-2 mt-2 small">
                                <div class="col-6">
                                    <label class="form-label small">Currency</label>
                                    <select class="form-select form-select-sm" wire:model="foreign_currency">
                                        @foreach(\App\Livewire\FrontOffice\AddReservation::FOREIGN_CURRENCIES as $c)
                                            <option value="{{ $c }}">{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Exchange rate (1 {{ $foreign_currency }} = ? Rs)</label>
                                    <input type="number" class="form-control form-control-sm" wire:model.live="exchange_rate" step="0.0001" min="0" placeholder="Rate">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Amount ({{ $foreign_currency }})</label>
                                    <input type="number" class="form-control form-control-sm" wire:model.live="amount_in_foreign" step="0.01" placeholder="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Amount (Rs)</label>
                                    <input type="number" class="form-control form-control-sm" wire:model.live="amount_in_local" step="0.01" placeholder="0">
                                </div>
                                @if($exchange_rate && ($amount_in_foreign !== '' || $amount_in_local !== ''))
                                    @php $conv = $this->getConvertedAmount(); @endphp
                                    @if($conv)
                                        <div class="col-12 text-muted small">
                                            {{ $conv['foreign'] }} {{ $conv['currency'] }} = Rs. {{ number_format($conv['local'], 2) }}
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    @endif
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary w-100" wire:click="reserve" wire:loading.attr="disabled" @if($reserve_success) disabled @endif>
                            <span wire:loading.remove wire:target="reserve">Reserve</span>
                            <span wire:loading wire:target="reserve"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                        </button>
                        <p class="small text-muted mt-2 mb-0">Reservation number will be generated after you click Reserve.</p>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100">Add Payment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- No component-specific JavaScript needed; guidance is rendered inline above using Livewire properties. --}}
