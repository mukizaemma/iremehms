@if($showCheckInModal && $checkInReservationId)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);" wire:keydown.escape="closeCheckInModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Check-in — {{ $checkInReservationNumber }}</h5>
                    <button type="button" class="btn-close" wire:click="closeCheckInModal"></button>
                </div>
                <div class="modal-body">
                    @php
                        $checkInGuestErrorKeys = collect($errors->keys())->filter(
                            fn (string $key) => str_starts_with($key, 'checkInGuests.') || $key === 'checkInRoomUnitId'
                        );
                    @endphp
                    @if($checkInGuestErrorKeys->isNotEmpty())
                        <div class="alert alert-danger py-2 mb-3" role="alert">
                            <div class="fw-semibold small mb-1">Please complete the required fields:</div>
                            <ul class="small mb-0 ps-3">
                                @foreach($checkInGuestErrorKeys as $errorKey)
                                    <li>{{ $errors->first($errorKey) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="alert alert-light border small mb-3">
                        <strong>Booking contact:</strong> {{ $checkInBookerName }}
                        @if($checkInBookerPhone) · {{ $checkInBookerPhone }} @endif
                        @if($checkInBookerEmail) · {{ $checkInBookerEmail }} @endif
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="contactIsStaying" wire:model.live="contactIsStayingGuest">
                        <label class="form-check-label" for="contactIsStaying">
                            The contact person above will stay in the room
                        </label>
                    </div>

                    @if(! $contactIsStayingGuest)
                        <p class="small text-muted mb-3">Enter the name(s) of the guest(s) who will stay in the room. Contact details on the booking remain for the booker.</p>
                    @endif

                    @if($checkInNeedsRoom)
                        <div class="card border border-primary mb-3">
                            <div class="card-body py-2">
                                <p class="small fw-semibold mb-2">Assign room</p>
                                <p class="small text-muted mb-2">Room type on booking: <strong>{{ $checkInRoomTypeName }}</strong></p>
                                <label class="form-label small mb-0">Room / unit <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm @error('checkInRoomUnitId') is-invalid @enderror" wire:model="checkInRoomUnitId">
                                    <option value="">Select room or unit</option>
                                    @foreach($this->checkInAvailableRoomUnits as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                                    @endforeach
                                </select>
                                @error('checkInRoomUnitId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="card border mb-3">
                        <div class="card-body py-2">
                            <p class="small fw-semibold mb-2">Meal plan &amp; rate</p>
                            <p class="small text-muted mb-2">Room rate below is the <strong>bed &amp; breakfast (BB)</strong> package. Add a supplement for half or full board, or adjust the total to pay.</p>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small mb-0">Board basis <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('checkInMealPlan') is-invalid @enderror" wire:model.live="checkInMealPlan">
                                        @foreach(\App\Enums\MealPlan::selectableCases() as $plan)
                                            <option value="{{ $plan->value }}">{{ $plan->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('checkInMealPlan') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-0">BB room rate ({{ $checkInCurrency }}) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm @error('checkInRoomRateAmount') is-invalid @enderror" wire:model.live="checkInRoomRateAmount">
                                    @error('checkInRoomRateAmount') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <div class="border rounded p-2 bg-light">
                                        <p class="small fw-semibold mb-2">Complimentary (optional)</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="ciRoomComp" wire:model.live="checkInIsRoomComplimentary">
                                            <label class="form-check-label small" for="ciRoomComp">Complimentary room</label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="ciMealComp" wire:model.live="checkInIsMealComplimentary">
                                            <label class="form-check-label small" for="ciMealComp">Complimentary meals (all meals)</label>
                                        </div>
                                        @if($checkInIsRoomComplimentary || $checkInIsMealComplimentary)
                                            <label class="form-label small mb-0">Reason <span class="text-danger">*</span></label>
                                            <textarea class="form-control form-control-sm @error('checkInComplimentaryReason') is-invalid @enderror" rows="2" wire:model.blur="checkInComplimentaryReason" placeholder="Why is this complimentary?"></textarea>
                                            @error('checkInComplimentaryReason') <div class="text-danger small">{{ $message }}</div> @enderror
                                        @endif
                                    </div>
                                </div>
                                @if(in_array($checkInMealPlan, ['hb', 'fb'], true) && ! $checkInIsMealComplimentary)
                                    <div class="col-md-6">
                                        <label class="form-label small mb-0">Board supplement ({{ $checkInCurrency }})</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm @error('checkInMealPlanSupplement') is-invalid @enderror" wire:model.live="checkInMealPlanSupplement" placeholder="Extra for HB / FB">
                                        @error('checkInMealPlanSupplement') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label small mb-0">Total to pay ({{ $checkInCurrency }}) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm @error('checkInTotalAmount') is-invalid @enderror" wire:model.live="checkInTotalAmount">
                                    @error('checkInTotalAmount') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <p class="small text-muted mb-0 mt-2">
                                @if($checkInIsMealComplimentary)
                                    All meals complimentary.
                                @elseif($checkInMealPlan === 'bb')
                                    Includes breakfast only.
                                @elseif($checkInMealPlan === 'hb')
                                    Includes breakfast and dinner.
                                @else
                                    Includes breakfast, lunch and dinner.
                                @endif
                                @if($checkInIsRoomComplimentary)
                                    · Room charge waived.
                                @endif
                            </p>
                        </div>
                    </div>

                    <p class="small fw-semibold mb-1">Guests staying ({{ count($checkInGuests) }} / {{ $checkInAdultCount }} adults)</p>
                    <p class="small text-muted mb-2">Fields marked <span class="text-danger">*</span> are required. For each additional guest, enter phone and stay dates if they will not stay the full booking period.</p>

                    @foreach($checkInGuests as $index => $guest)
                        @php
                            $guestNumber = $index + 1;
                            $isAdditional = $index > 0;
                        @endphp
                        <div class="card border mb-2 {{ $isAdditional ? 'border-info' : '' }}" wire:key="check-in-guest-row-{{ $index }}">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-semibold">
                                        @if($index === 0)
                                            Primary guest @if($contactIsStayingGuest) (staying) @endif
                                        @else
                                            Guest {{ $guestNumber }} <span class="text-muted fw-normal">(additional)</span>
                                        @endif
                                    </span>
                                    @if($index > 0)
                                        <button type="button" class="btn btn-link btn-sm text-danger p-0" wire:click="removeCheckInGuestRow({{ $index }})">Remove</button>
                                    @endif
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-0">
                                            Full name
                                            @if($index === 0)<span class="text-danger">*</span>@endif
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm @error('checkInGuests.'.$index.'.full_name') is-invalid @enderror"
                                            wire:model.blur="checkInGuests.{{ $index }}.full_name"
                                        >
                                        @error('checkInGuests.'.$index.'.full_name') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-0">ID / passport</label>
                                        <input type="text" class="form-control form-control-sm" wire:model.blur="checkInGuests.{{ $index }}.id_number">
                                    </div>

                                    @if($index === 0)
                                        <div class="col-md-4">
                                            <label class="form-label small mb-0">Phone</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="checkInGuests.{{ $index }}.phone" placeholder="Mobile number">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-0">Email</label>
                                            <input type="email" class="form-control form-control-sm" wire:model.blur="checkInGuests.{{ $index }}.email">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-0">Country</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="checkInGuests.{{ $index }}.country">
                                        </div>
                                    @else
                                        <div class="col-12">
                                            <div class="rounded bg-light border px-2 py-2">
                                                <p class="small fw-semibold text-info mb-2 mb-md-1">Required for this guest</p>
                                                <div class="row g-2">
                                                    <div class="col-md-4">
                                                        <label class="form-label small mb-0">Phone <span class="text-danger">*</span></label>
                                                        <input
                                                            type="tel"
                                                            class="form-control form-control-sm @error('checkInGuests.'.$index.'.phone') is-invalid @enderror"
                                                            wire:model.blur="checkInGuests.{{ $index }}.phone"
                                                            placeholder="e.g. 0781234567"
                                                            autocomplete="tel"
                                                        >
                                                        @error('checkInGuests.'.$index.'.phone') <div class="text-danger small fw-semibold">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small mb-0">Check-in <span class="text-danger">*</span></label>
                                                        <input
                                                            type="date"
                                                            class="form-control form-control-sm @error('checkInGuests.'.$index.'.check_in_date') is-invalid @enderror"
                                                            wire:model="checkInGuests.{{ $index }}.check_in_date"
                                                            min="{{ $checkInReservationCheckIn }}"
                                                            max="{{ $checkInReservationCheckOut }}"
                                                        >
                                                        @error('checkInGuests.'.$index.'.check_in_date') <div class="text-danger small fw-semibold">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small mb-0">Check-out <span class="text-danger">*</span></label>
                                                        <input
                                                            type="date"
                                                            class="form-control form-control-sm @error('checkInGuests.'.$index.'.check_out_date') is-invalid @enderror"
                                                            wire:model="checkInGuests.{{ $index }}.check_out_date"
                                                            min="{{ $checkInReservationCheckIn }}"
                                                            max="{{ $checkInReservationCheckOut }}"
                                                        >
                                                        @error('checkInGuests.'.$index.'.check_out_date') <div class="text-danger small fw-semibold">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                                <p class="small text-muted mb-0 mt-2">Stay within booking dates: {{ $checkInReservationCheckIn }} to {{ $checkInReservationCheckOut }}.</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if(count($checkInGuests) < $checkInAdultCount)
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="addCheckInGuestRow">
                            <i class="fa fa-user-plus me-1"></i>Add another guest
                        </button>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeCheckInModal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmCheckIn" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="confirmCheckIn"><i class="fa fa-sign-in-alt me-1"></i>Confirm check-in</span>
                        <span wire:loading wire:target="confirmCheckIn">Checking in…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
