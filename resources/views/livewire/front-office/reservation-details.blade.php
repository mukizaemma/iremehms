<div class="container py-4" style="max-width:1140px">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="bg-light rounded-3 p-3 p-md-4 mb-4 d-flex flex-column flex-lg-row flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <a href="{{ route('front-office.reservations') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-arrow-left me-1"></i>Back to reservations</a>
            <h5 class="mb-1">Reservation details</h5>
            <p class="text-muted small mb-0">
                {{ $reservation->reservation_number }}
                · <span class="text-capitalize">{{ str_replace('_', ' ', $reservation->status) }}</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ $folioPrintUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa fa-file-alt me-1"></i>Folio</a>
            <a href="{{ $folioPrintUrl }}?auto=1" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fa fa-print me-1"></i>Print folio</a>
            @if($canMoveRoom)
                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openMoveRoomModal">
                    <i class="fa fa-exchange-alt me-1"></i>Move room
                </button>
            @endif
            @if($canCollectPayment)
                <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($reservation->reservation_number) }}&action=payment" class="btn btn-primary btn-sm">
                    <i class="fa fa-money-bill-wave me-1"></i>Payment
                </a>
            @endif
        </div>
    </div>

    @if($canEdit)
        <form wire:submit="saveReservation">
            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold py-3">Guest &amp; stay</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small">Guest name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('editGuestName') is-invalid @enderror" wire:model.blur="editGuestName">
                                    @error('editGuestName') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Phone</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="editGuestPhone">
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label small">Email</label>
                                    <input type="email" class="form-control form-control-sm" wire:model.blur="editGuestEmail">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small">Check-in <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm @error('editCheckInDate') is-invalid @enderror" wire:model.blur="editCheckInDate">
                                    @error('editCheckInDate') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small">Check-out <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm @error('editCheckOutDate') is-invalid @enderror" wire:model.blur="editCheckOutDate">
                                    @error('editCheckOutDate') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Room type</label>
                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $reservation->roomType->name ?? '—' }}" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Current room(s)</label>
                                    <input type="text" class="form-control form-control-sm bg-light" value="{{ $reservation->roomUnits->isNotEmpty() ? $reservation->roomUnits->pluck('label')->join(', ') : 'Unassigned' }}" readonly disabled>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>Board basis &amp; rate</span>
                            <span class="badge bg-light text-dark border fw-normal">Accommodation receipt</span>
                        </div>
                        <div class="card-body">
                            @if(!empty($accommodationInvoice['lines']))
                                <div class="mb-4">
                                    @include('livewire.front-office.partials.accommodation-invoice-table', [
                                        'invoice' => $accommodationInvoice,
                                        'caption' => 'Line items mirror your entries below — total matches “Total to pay”. Qty counts room-nights.',
                                    ])
                                </div>
                                <hr class="text-muted opacity-25 my-4">
                            @endif
                            <p class="small fw-semibold text-muted text-uppercase mb-3">Pricing</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Meal plan</label>
                                    <select class="form-select form-select-sm" wire:model.live="editMealPlan">
                                        @foreach(\App\Enums\MealPlan::selectableCases() as $plan)
                                            <option value="{{ $plan->value }}">{{ $plan->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">BB room rate reference ({{ $currency }})</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editRoomRateAmount">
                                </div>
                                <div class="col-12">
                                    <div class="border rounded-3 p-3 bg-light">
                                        <p class="small fw-semibold mb-2">Complimentary services</p>
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="edRoomComp" wire:model.live="editIsRoomComplimentary">
                                                    <label class="form-check-label small" for="edRoomComp">Complimentary room</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="edMealComp" wire:model.live="editIsMealComplimentary">
                                                    <label class="form-check-label small" for="edMealComp">Complimentary meals (all meals)</label>
                                                </div>
                                            </div>
                                        </div>
                                        @if($editIsRoomComplimentary || $editIsMealComplimentary)
                                            <label class="form-label small mt-2">Reason <span class="text-danger">*</span></label>
                                            <textarea class="form-control form-control-sm @error('editComplimentaryReason') is-invalid @enderror" rows="2" wire:model.blur="editComplimentaryReason"></textarea>
                                            @error('editComplimentaryReason') <div class="text-danger small">{{ $message }}</div> @enderror
                                            @if($reservation->complimentaryServicesLabel() !== '—')
                                                <p class="small text-muted mb-0 mt-1">Currently: {{ $reservation->complimentaryServicesLabel() }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                @if(in_array($editMealPlan, ['hb', 'fb'], true) && ! $editIsMealComplimentary)
                                    <div class="col-md-6">
                                        <label class="form-label small">Board supplement ({{ $currency }})</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editMealPlanSupplement">
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label small">Total to pay ({{ $currency }})</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-semibold" wire:model.live="editTotalAmount">
                                </div>
                                @if($folio)
                                    <div class="col-12">
                                        <div class="small border rounded-3 px-3 py-2 bg-light">
                                            Folio payments — Paid: <strong>{{ $folio['currency'] }} {{ number_format($folio['folio']['paid'], 2) }}</strong>
                                            · Balance (stay):
                                            <strong class="{{ ($folio['folio']['balance'] ?? 0) > 0 ? 'text-danger' : '' }}">
                                                {{ $folio['currency'] }} {{ number_format($folio['folio']['balance'], 2) }}
                                            </strong>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold py-3">Meal service preferences</div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">Preferred times and whether meals are served in the room or in the restaurant.</p>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Breakfast</label>
                                    <input type="time" class="form-control form-control-sm" wire:model.blur="editBreakfastTime">
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="bfInRoom" wire:model.live="editBreakfastInRoom">
                                        <label class="form-check-label small" for="bfInRoom">In room</label>
                                    </div>
                                </div>
                                @if($editIsMealComplimentary || $editMealPlan === 'fb')
                                    <div class="col-md-4">
                                        <label class="form-label small">Lunch</label>
                                        <input type="time" class="form-control form-control-sm" wire:model.blur="editLunchTime">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="lnInRoom" wire:model.live="editLunchInRoom">
                                            <label class="form-check-label small" for="lnInRoom">In room</label>
                                        </div>
                                    </div>
                                @endif
                                @if($editIsMealComplimentary || in_array($editMealPlan, ['hb', 'fb'], true))
                                    <div class="col-md-4">
                                        <label class="form-label small">Dinner</label>
                                        <input type="time" class="form-control form-control-sm" wire:model.blur="editDinnerTime">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="dnInRoom" wire:model.live="editDinnerInRoom">
                                            <label class="form-check-label small" for="dnInRoom">In room</label>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label small">Service notes</label>
                                    <textarea class="form-control form-control-sm" rows="2" wire:model.blur="editMealServiceNotes" placeholder="Allergies, special requests, etc."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(count($editGuestRows) > 0)
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white fw-semibold py-3">Guests on reservation</div>
                            <div class="card-body py-0 px-0">
                                <p class="small text-muted px-3 pt-3 mb-3">Per-guest meal preferences (optional overrides).</p>
                                <div class="table-responsive rounded-bottom">
                                    <table class="table table-sm align-middle mb-0 reservation-guest-rows">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="guest-col">Guest</th>
                                                <th class="stay-col">Stay</th>
                                                <th>Breakfast</th>
                                                <th>Dinner</th>
                                                <th class="notes-col">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($editGuestRows as $gi => $grow)
                                                <tr wire:key="edit-guest-{{ $grow['id'] }}">
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.blur="editGuestRows.{{ $gi }}.full_name">
                                                    </td>
                                                    <td class="small">
                                                        <label class="form-label visually-hidden">Guest check-in</label>
                                                        <input type="date" class="form-control form-control-sm mb-1" wire:model.blur="editGuestRows.{{ $gi }}.check_in_date">
                                                        <label class="form-label visually-hidden">Guest check-out</label>
                                                        <input type="date" class="form-control form-control-sm" wire:model.blur="editGuestRows.{{ $gi }}.check_out_date">
                                                    </td>
                                                    <td>
                                                        <input type="time" class="form-control form-control-sm mb-1" wire:model.blur="editGuestRows.{{ $gi }}.breakfast_preferred_time">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" wire:model.live="editGuestRows.{{ $gi }}.breakfast_in_room">
                                                            <label class="form-check-label small">Room</label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="time" class="form-control form-control-sm mb-1" wire:model.blur="editGuestRows.{{ $gi }}.dinner_preferred_time">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" wire:model.live="editGuestRows.{{ $gi }}.dinner_in_room">
                                                            <label class="form-check-label small">Room</label>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.blur="editGuestRows.{{ $gi }}.meal_service_notes" placeholder="Notes">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="position-sticky bottom-0 bg-white border rounded-3 shadow-sm p-3 mb-4 d-flex flex-wrap gap-2" style="z-index: 10">
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="saveReservation"><i class="fa fa-save me-1"></i>Save changes</span>
                            <span wire:loading wire:target="saveReservation">Saving…</span>
                        </button>
                        <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($reservation->reservation_number) }}&action=charges" class="btn btn-outline-warning btn-sm">
                            <i class="fa fa-plus-circle me-1"></i>Add extra charges
                        </a>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="sticky-top pt-0" style="top: 1rem; z-index: 5">
                        @if($folio)
                            @include('livewire.front-office.partials.reservation-folio-panel', ['folio' => $folio, 'printUrl' => $folioPrintUrl])
                        @endif
                    </div>
                </div>
            </div>
        </form>
    @else
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <div class="alert alert-secondary mb-3">This reservation cannot be edited ({{ str_replace('_', ' ', $reservation->status) }}).</div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold py-3">Guest &amp; stay</div>
                    <div class="card-body">
                        <dl class="row small mb-0">
                            <dt class="col-sm-4 text-muted">Guest</dt>
                            <dd class="col-sm-8">{{ $reservation->guest_name }}</dd>
                            <dt class="col-sm-4 text-muted">Contact</dt>
                            <dd class="col-sm-8">{{ $reservation->guest_phone ?: '—' }} @if($reservation->guest_email)<br>{{ $reservation->guest_email }}@endif</dd>
                            <dt class="col-sm-4 text-muted">Stay</dt>
                            <dd class="col-sm-8">{{ $reservation->check_in_date->format('d M Y') }} – {{ $reservation->check_out_date->format('d M Y') }}</dd>
                            <dt class="col-sm-4 text-muted">Room type</dt>
                            <dd class="col-sm-8">{{ $reservation->roomType->name ?? '—' }}</dd>
                            <dt class="col-sm-4 text-muted">Room(s)</dt>
                            <dd class="col-sm-8">{{ $reservation->roomUnits->isNotEmpty() ? $reservation->roomUnits->pluck('label')->join(', ') : 'Unassigned' }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-semibold py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Board &amp; rate (as booked)</span>
                        <span class="badge bg-light text-dark border fw-normal">Accommodation receipt</span>
                    </div>
                    <div class="card-body small">
                        <p class="mb-3"><strong>{{ $reservation->mealPlanEnum()->label() }}</strong><br><span class="text-muted">{{ $reservation->complimentaryServicesLabel() }}</span></p>
                        @if(!empty($accommodationInvoice['lines']))
                            @include('livewire.front-office.partials.accommodation-invoice-table', ['invoice' => $accommodationInvoice])
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="sticky-top pt-0" style="top: 1rem; z-index: 5">
                    @if($folio)
                        @include('livewire.front-office.partials.reservation-folio-panel', ['folio' => $folio, 'printUrl' => $folioPrintUrl])
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($showMoveRoomModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Move guest to another room</h5>
                        <button type="button" class="btn-close" wire:click="closeMoveRoomModal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Current: <strong>{{ $reservation->roomUnits->pluck('label')->join(', ') ?: 'Unassigned' }}</strong></p>
                        <div class="mb-3">
                            <label class="form-label small">New room / unit <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm @error('moveRoomUnitId') is-invalid @enderror" wire:model="moveRoomUnitId">
                                <option value="">Select room</option>
                                @foreach($this->moveRoomUnits as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->label }}</option>
                                @endforeach
                            </select>
                            @error('moveRoomUnitId') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label small">Reason (optional)</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="moveRoomReason" placeholder="e.g. technical issue, guest request">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeMoveRoomModal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="confirmMoveRoom">Confirm move</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
<style>
    @media (min-width: 768px) {
        .reservation-guest-rows { table-layout: fixed; }
        .reservation-guest-rows .guest-col { width: 22%; }
        .reservation-guest-rows .stay-col { width: 18%; }
        .reservation-guest-rows .notes-col { width: 22%; }
    }
</style>
@endpush
