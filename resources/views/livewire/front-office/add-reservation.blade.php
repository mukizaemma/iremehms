@push('styles')
    <link href="{{ asset('css/add-reservation.css') }}" rel="stylesheet">
@endpush

@php
    $nights = $this->computeNights();
    $rate = (float) preg_replace('/[^0-9.]/', '', $rate_rs ?? '');
    $rooms = max(1, (int) ($rooms_count ?? 1));
    $paid = 0;
    if ($payment_mode_enabled) {
        $paid = \App\Support\ForeignCurrencyPaymentSupport::resolvedPaidAmount(
            \App\Models\Hotel::getHotel(),
            (bool) $use_international_currency,
            $foreign_currency,
            $exchange_rate,
            $amount_in_foreign,
            $amount_in_local,
            $payment_amount,
        );
    }
    $balanceDue = max(0, $this->getDueAmount() - $paid);
@endphp

<div class="add-reservation">
    @livewire('shift-acknowledgment-banner', ['targetScope' => \App\Models\OperationalShift::SCOPE_FRONT_OFFICE, 'onlyWhenMissing' => true])

    <div class="ar-page-header">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i></a>
            <h5 class="mb-0">New reservation</h5>
        </div>
    </div>

    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
            <ul class="mb-0 ps-3 small">
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
                    <strong>Reservation created.</strong> No. <strong>{{ $reservation_number }}</strong>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('front-office.add-reservation') }}" class="btn btn-outline-success btn-sm"><i class="fa fa-plus me-1"></i>New</a>
                <a href="{{ route('front-office.rooms') }}" class="btn btn-outline-success btn-sm"><i class="fa fa-bed me-1"></i>Rooms</a>
                <a href="{{ route('front-office.reservations') }}?search={{ urlencode($reservation_number) }}" class="btn btn-success btn-sm"><i class="fa fa-folder-open me-1"></i>Open</a>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Stay --}}
            <div class="ar-section">
                <div class="ar-section__head"><i class="fa fa-calendar-alt"></i> Stay</div>
                <div class="ar-section__body ar-field-grid">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="ar-label">Booking</div>
                            <div class="ar-segment">
                                <button type="button" class="ar-segment__btn {{ ! $is_group_booking ? 'is-active' : '' }}" wire:click="$set('is_group_booking', false)">Single</button>
                                <button type="button" class="ar-segment__btn {{ $is_group_booking ? 'is-active' : '' }}" wire:click="$set('is_group_booking', true)">Group</button>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="ar-label">Check-in</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="check_in_date" min="{{ now()->toDateString() }}">
                            <input type="time" class="form-control form-control-sm mt-1" wire:model="check_in_time">
                        </div>
                        <div class="col-6 col-md-2 d-flex align-items-end pb-1">
                            <span class="ar-nights-badge w-100">{{ $nights }} {{ Str::plural('night', $nights) }}</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ar-label">Check-out</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="check_out_date" min="{{ now()->toDateString() }}">
                            <input type="time" class="form-control form-control-sm mt-1" wire:model="check_out_time">
                        </div>
                        <div class="col-3 col-md-2">
                            <label class="ar-label">Adults</label>
                            <input type="number" class="form-control form-control-sm" wire:model.live="adult" min="1">
                        </div>
                        <div class="col-3 col-md-2">
                            <label class="ar-label">Children</label>
                            <input type="number" class="form-control form-control-sm" wire:model.live="child" min="0">
                        </div>

                        @if(! $is_group_booking)
                            <div class="col-12">
                                <div class="ar-label">Room</div>
                                <div class="ar-segment">
                                    <button type="button" class="ar-segment__btn {{ $room_assignment === 'later' ? 'is-active' : '' }}" wire:click="$set('room_assignment', 'later')">Assign later</button>
                                    <button type="button" class="ar-segment__btn {{ $room_assignment === 'now' ? 'is-active' : '' }}" wire:click="$set('room_assignment', 'now')">Assign now</button>
                                </div>
                                @error('room_assignment') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        @if($is_group_booking || $room_assignment === 'now')
                            <div class="col-4 col-md-2">
                                <label class="ar-label">Rooms</label>
                                <input type="number" class="form-control form-control-sm" wire:model.live="rooms_count" min="1" @if(! $is_group_booking) readonly @endif>
                            </div>
                        @endif

                        @if($occupancy_warning || $rooms_availability_warning || $missing_occupancy_room_type)
                            <div class="col-12">
                                @if($missing_occupancy_room_type)
                                    <div class="alert alert-warning py-2 small mb-2">
                                        Occupancy not set for <strong>{{ $missing_occupancy_room_type }}</strong>.
                                        <button type="button" class="btn btn-outline-dark btn-sm ms-1" wire:click="sendOccupancySetupRequest">Request setup</button>
                                    </div>
                                @endif
                                @if($occupancy_warning)
                                    <div class="alert alert-warning py-2 small mb-2 d-flex justify-content-between align-items-center gap-2">
                                        <span>{{ $occupancy_warning }}</span>
                                        @if($suggested_group_rooms)
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="convertToSuggestedGroupBooking">Group ({{ $suggested_group_rooms }})</button>
                                        @endif
                                    </div>
                                @endif
                                @if($rooms_availability_warning)
                                    <div class="alert alert-info py-2 small mb-0">{{ $rooms_availability_warning }}</div>
                                @endif
                            </div>
                        @endif

                        <div class="col-6 col-md-3">
                            <label class="ar-label">Type</label>
                            <select class="form-select form-select-sm" wire:model="reservation_type">
                                @foreach(\App\Livewire\FrontOffice\AddReservation::RESERVATION_TYPES as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($check_in_date && $check_out_date && $check_in_date < $check_out_date && collect($roomTypesWithCount)->sum('available_count') === 0)
                            <div class="col-12">
                                <div class="alert alert-warning py-2 small mb-0">No rooms available for these dates.</div>
                            </div>
                        @endif

                        @if($is_group_booking)
                            <div class="col-12">
                                <label class="ar-label">Room types</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-2 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Room type</th>
                                                <th class="text-center" style="width:90px;">Qty</th>
                                                <th class="text-center" style="width:80px;">Adults</th>
                                                <th class="text-center" style="width:80px;">Children</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group_room_rows as $index => $row)
                                                <tr>
                                                    <td>
                                                        <select class="form-select form-select-sm" wire:model.live="group_room_rows.{{ $index }}.room_type_id">
                                                            <option value="">Select</option>
                                                            @foreach($roomTypesWithCount as $rt)
                                                                <option value="{{ $rt['id'] }}">{{ $rt['name'] }} ({{ $rt['available_count'] }})</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td><input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.quantity" min="1" max="99"></td>
                                                    <td><input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.adults" min="0"></td>
                                                    <td><input type="number" class="form-control form-control-sm text-center" wire:model.live="group_room_rows.{{ $index }}.children" min="0"></td>
                                                    <td>
                                                        @if(count($group_room_rows) > 1)
                                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" wire:click="removeGroupRow({{ $index }})"><i class="fa fa-times"></i></button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addGroupRow"><i class="fa fa-plus me-1"></i>Add row</button>
                                <div class="mt-2" style="max-width:220px;">
                                    <label class="ar-label">Rate</label>
                                    <select class="form-select form-select-sm" wire:model.live="rate_type">
                                        @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
                            <div class="col-6 col-md-3">
                                <label class="ar-label">Room type</label>
                                <select class="form-select form-select-sm" wire:model.live="room_type_id">
                                    <option value="">Select</option>
                                    @foreach($roomTypesWithCount as $rt)
                                        <option value="{{ $rt['id'] }}">{{ $rt['name'] }} ({{ $rt['available_count'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="ar-label">Rate</label>
                                <select class="form-select form-select-sm" wire:model.live="rate_type">
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="ar-label">Nightly rate ({{ $currency }}) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-sm" wire:model.live="rate_rs" step="0.01" min="0" placeholder="0">
                            </div>
                            <div class="col-6 col-md-3 d-flex align-items-end pb-1">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" wire:model.live="rate_tax_inc" id="rateTaxInc">
                                    <label class="form-check-label small" for="rateTaxInc">Tax inclusive</label>
                                </div>
                            </div>
                            @if($room_assignment === 'now')
                                <div class="col-6 col-md-4">
                                    <label class="ar-label">Room / unit <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" wire:model.live="room_unit_id">
                                        <option value="">Select</option>
                                        @foreach($roomUnits as $u)
                                            <option value="{{ $u->id }}">{{ $u->label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-4 d-flex align-items-end pb-1">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" wire:model.live="extra_bed" id="extraBed">
                                        <label class="form-check-label small" for="extraBed">Extra bed</label>
                                    </div>
                                </div>
                                @if($room_unit_id && $show_overlap_modal && count($overlap_reservations) > 0)
                                    <div class="col-12">
                                        <div class="alert alert-warning py-2 small mb-0">
                                            {{ count($overlap_reservations) }} overlapping booking(s) on this unit.
                                        </div>
                                    </div>
                                    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.35);">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header py-2">
                                                    <h6 class="modal-title mb-0">Overlapping bookings</h6>
                                                    <button type="button" class="btn-close" wire:click="closeOverlapModal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="list-group">
                                                        @foreach($overlap_reservations as $or)
                                                            <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                                                                <div>
                                                                    <div class="fw-semibold">{{ $or['guest_name'] }}</div>
                                                                    <div class="small text-muted">{{ $or['reservation_number'] }} · {{ $or['from'] }} → {{ $or['to'] }}</div>
                                                                </div>
                                                                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addGuestToExistingBooking({{ (int) $or['reservation_id'] }})">
                                                                    Add to booking
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <div class="modal-footer py-2">
                                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeOverlapModal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Guest --}}
            <div class="ar-section">
                <div class="ar-section__head"><i class="fa fa-user"></i> Guest</div>
                <div class="ar-section__body ar-field-grid">
                    <div class="row g-3">
                        @if(! $is_group_booking)
                            <div class="col-12 col-md-7">
                                <div class="ar-label">Booking for</div>
                                <div class="ar-segment">
                                    <button type="button" class="ar-segment__btn {{ $booking_for_self ? 'is-active' : '' }}" wire:click="$set('booking_for_self', true)">Self</button>
                                    <button type="button" class="ar-segment__btn {{ ! $booking_for_self ? 'is-active' : '' }}" wire:click="$set('booking_for_self', false)">Someone else</button>
                                </div>
                            </div>
                            @if(! $booking_for_self)
                                <div class="col-12">
                                    <div class="ar-booker-box">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <label class="ar-label">Booker name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm @error('booker_name') is-invalid @enderror" wire:model="booker_name" placeholder="Who is making the booking">
                                                @error('booker_name') <div class="text-danger small">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="ar-label">Booker phone <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-sm @error('booker_phone') is-invalid @enderror" wire:model="booker_phone" placeholder="Contact number">
                                                @error('booker_phone') <div class="text-danger small">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <div class="col-12 {{ ! $is_group_booking ? 'col-md-5' : 'col-md-4' }}">
                            <label class="ar-label">Source</label>
                            <select class="form-select form-select-sm" wire:model.live="business_source">
                                <option value="">Select</option>
                                @foreach(\App\Livewire\FrontOffice\AddReservation::BUSINESS_SOURCE_OPTIONS as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($business_source === 'OTA')
                            <div class="col-6 col-md-4">
                                <label class="ar-label">OTA</label>
                                <select class="form-select form-select-sm" wire:model="selected_ota">
                                    <option value="">Select</option>
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::KNOWN_OTAS as $ota)
                                        <option value="{{ $ota }}">{{ $ota }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if($business_source === 'Social media')
                            <div class="col-6 col-md-4">
                                <label class="ar-label">Account</label>
                                <input type="text" class="form-control form-control-sm" wire:model="social_media_page" placeholder="@hotel">
                            </div>
                        @endif
                        @if($business_source === 'Referral')
                            <div class="col-6 col-md-4">
                                <label class="ar-label">Referral type <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('referral_type') is-invalid @enderror" wire:model="referral_type">
                                    <option value="">Select</option>
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::REFERRAL_TYPES as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                                @error('referral_type') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="ar-label">Referrer name</label>
                                <input type="text" class="form-control form-control-sm" wire:model="referral_name" placeholder="Optional">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="ar-label">Referrer phone</label>
                                <input type="text" class="form-control form-control-sm" wire:model="referral_phone" placeholder="Optional">
                            </div>
                        @endif

                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <label class="ar-label mb-0">
                                    {{ $booking_for_self || $is_group_booking ? 'Guest name' : 'Guest staying' }}
                                    @if(! $is_group_booking)<span class="text-danger">*</span>@endif
                                </label>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" wire:model.live="use_existing_client" id="useExistingClient">
                                    <label class="form-check-label small" for="useExistingClient">Existing client</label>
                                </div>
                            </div>
                            <div class="ar-guest-name-row position-relative">
                                <select class="form-select form-select-sm" wire:model="guest_salutation">
                                    <option>Mr.</option>
                                    <option>Ms.</option>
                                    <option>Mrs.</option>
                                    <option>Dr.</option>
                                </select>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-sm @error('guest_name') is-invalid @enderror" wire:model.live.debounce.300ms="guest_name" placeholder="Full name">
                                    @if($guest_search_open && count($guest_suggestions) > 0)
                                        <div class="list-group position-absolute w-100 shadow ar-suggest-list">
                                            @foreach($guest_suggestions as $g)
                                                <button type="button" class="list-group-item list-group-item-action list-group-item-light small py-2" wire:click="selectGuest({{ $g['id'] }})">
                                                    {{ $g['name'] }} @if(!empty($g['mobile'])) · {{ $g['mobile'] }} @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="ar-guest-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('guest_name', ''); closeGuestSearch" title="Clear"><i class="fa fa-times"></i></button>
                                    @if($use_existing_client)
                                        <button type="button" class="btn btn-success btn-sm" wire:click="confirmPastClient">Confirm</button>
                                    @endif
                                </div>
                            </div>
                            @error('guest_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @if($guest_confirmed)
                                <small class="text-success">Past client confirmed.</small>
                            @endif
                            @if($existing_guest_stay_count)
                                <small class="text-muted d-block">Stays: {{ $existing_guest_stay_count }}@if($existing_guest_referral_count) · Referrals: {{ $existing_guest_referral_count }}@endif</small>
                            @endif
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="ar-label">{{ $booking_for_self || $is_group_booking ? 'Mobile' : 'Guest mobile' }}</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_mobile" placeholder="Phone">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="ar-label">Email</label>
                            <input type="email" class="form-control form-control-sm" wire:model="guest_email" placeholder="Email">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="ar-label">ID / passport</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_id_number">
                        </div>
                        <div class="col-12">
                            <label class="ar-label">Address</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_address" placeholder="Street address">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ar-label">Country</label>
                            <input type="text" class="form-control form-control-sm" list="country-list" wire:model.live.debounce.200ms="guest_country" autocomplete="off">
                            <datalist id="country-list">
                                @foreach($allCountries as $c)
                                    <option value="{{ $c }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ar-label">State</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_state">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ar-label">City</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_city">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="ar-label">Zip</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_zip">
                        </div>
                        <div class="col-6 col-md-6">
                            <label class="ar-label">Profession</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_profession">
                        </div>
                        <div class="col-6 col-md-6">
                            <label class="ar-label">Stay purpose</label>
                            <input type="text" class="form-control form-control-sm" wire:model="guest_stay_purpose" placeholder="Business, leisure…">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Options --}}
            <div class="ar-section">
                <div class="ar-section__head"><i class="fa fa-envelope"></i> Notifications</div>
                <div class="ar-section__body">
                    <div class="ar-confirm-row">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="email_booking_vouchers" id="emailVouchers">
                            <label class="form-check-label" for="emailVouchers">Email vouchers</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="send_email_at_checkout" id="emailCheckout">
                            <label class="form-check-label" for="emailCheckout">Email at checkout</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model="access_to_guest_portal" id="guestPortal">
                            <label class="form-check-label" for="guestPortal">Guest portal</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="col-lg-5">
            <div class="ar-summary">
                <div class="ar-summary__head">
                    <h6>Summary</h6>
                    <div class="ar-summary__meta">
                        <span class="ar-summary__pill"><i class="fa fa-sign-in-alt"></i> {{ $check_in_date ? \Carbon\Carbon::parse($check_in_date)->format('d M Y') : '—' }}</span>
                        <span class="ar-summary__pill"><i class="fa fa-sign-out-alt"></i> {{ $check_out_date ? \Carbon\Carbon::parse($check_out_date)->format('d M Y') : '—' }}</span>
                        <span class="ar-summary__pill"><i class="fa fa-moon"></i> {{ $nights }} {{ Str::plural('night', $nights) }}</span>
                    </div>
                </div>
                <div class="ar-summary__body">
                    @if($reserve_success && $reservation_number)
                        <div class="alert alert-success py-2 small mb-3">No. <strong>{{ $reservation_number }}</strong></div>
                    @endif

                    <div class="ar-summary__total">
                        <span class="ar-summary__total-label">Total due</span>
                        <span class="ar-summary__total-value">{{ $currency }} {{ number_format($this->getDueAmount(), 0) }}</span>
                    </div>

                    <ul class="ar-summary__lines">
                        @if($is_group_booking)
                            <li><span>Group · {{ count(array_filter($group_room_rows, fn($r) => !empty($r['room_type_id']))) }} type(s)</span><span></span></li>
                        @elseif($nights > 0 && $rate >= 0)
                            <li><span>{{ $nights }} × {{ number_format($rate, 0) }} × {{ $rooms }} room(s)</span><span></span></li>
                        @endif
                        <li><span>Room charges</span><span>{{ $currency }} {{ number_format($this->getRoomChargesTotal(), 0) }}</span></li>
                        <li><span>VAT 18% @if($tax_exempt)<span class="text-muted">(exempt)</span>@endif</span><span>{{ $currency }} {{ number_format($this->getTaxesTotal(), 0) }}</span></li>
                        @if($paid > 0)
                            <li class="is-paid"><span>Paid now</span><span>{{ $currency }} {{ number_format($paid, 0) }}</span></li>
                            <li class="is-strong"><span>Balance</span><span>{{ $currency }} {{ number_format($balanceDue, 0) }}</span></li>
                        @endif
                    </ul>

                    <div class="ar-summary__block">
                        <div class="ar-summary__block-title">Billing</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="ar-label">Bill to</label>
                                <select class="form-select form-select-sm" wire:model="bill_to">
                                    <option>Guest</option>
                                    <option>Company</option>
                                </select>
                            </div>
                            <div class="col-6 d-flex align-items-end pb-1">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" wire:model.live="tax_exempt" id="taxExempt">
                                    <label class="form-check-label small" for="taxExempt">Tax exempt</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ar-summary__block">
                        <div class="ar-summary__block-title">Payment</div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" wire:model="payment_mode_enabled" id="paymentMode">
                            <label class="form-check-label small" for="paymentMode">Record payment</label>
                        </div>
                        @if($payment_mode_enabled)
                            <div class="row g-2 mb-2 align-items-end">
                                <div class="col-6">
                                    <label class="ar-label">Method</label>
                                    <select class="form-select form-select-sm" wire:model.live="payment_unified">
                                        @foreach(\App\Support\PaymentCatalog::unifiedAccommodationOptions() as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_unified') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                @if(! $use_international_currency)
                                    <div class="col-6">
                                        <label class="ar-label">Amount ({{ $currency }})</label>
                                        <input type="number" class="form-control form-control-sm" wire:model.live="payment_amount" step="0.01" min="0" placeholder="0">
                                    </div>
                                @endif
                            </div>
                            @if(\App\Support\PaymentCatalog::unifiedChoiceRequiresClientDetails($payment_unified ?? ''))
                                <div class="mb-2">
                                    <label class="ar-label">Account details <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm" wire:model="payment_client_reference" rows="2"></textarea>
                                    @error('payment_client_reference') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            @include('livewire.front-office.partials.foreign-currency-payment-fields', [
                                'baseCurrency' => $currency,
                                'compact' => true,
                                'idPrefix' => 'ar',
                                'inlineAmount' => true,
                            ])
                        @endif
                    </div>

                    <button type="button" class="btn btn-primary w-100 ar-btn-reserve mt-3" wire:click="reserve" wire:loading.attr="disabled" @if($reserve_success) disabled @endif>
                        <span wire:loading.remove wire:target="reserve"><i class="fa fa-check me-1"></i>Confirm booking</span>
                        <span wire:loading wire:target="reserve"><span class="spinner-border spinner-border-sm me-2"></span>Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
