<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4 mb-3 d-flex justify-content-between align-items-center">
                <div>
                    <a href="{{ route('front-office.reservations') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-arrow-left me-1"></i>Back to reservations</a>
                    <h5 class="mb-1">Reservation details</h5>
                    <p class="text-muted small mb-0">Review guest and stay details, confirm payments, or open the reservation form to add another person.</p>
                </div>
                <div class="text-end small text-muted">
                    <div>Hotel: {{ $hotel->name }}</div>
                    <div>Reservation: <strong>{{ $reservation->reservation_number }}</strong></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="mb-2">Guest</h6>
                            <p class="mb-1 fw-semibold">{{ $reservation->guest_name }}</p>
                            <p class="mb-1 small text-muted">
                                @if($reservation->guest_phone) {{ $reservation->guest_phone }} @endif
                                @if($reservation->guest_phone && $reservation->guest_email) · @endif
                                @if($reservation->guest_email) {{ $reservation->guest_email }} @endif
                            </p>
                            <p class="mb-1 small text-muted">
                                @if($reservation->guest_country) {{ $reservation->guest_country }} @endif
                                @if($reservation->guest_address) · {{ $reservation->guest_address }} @endif
                            </p>
                            <p class="mb-0 small text-muted">
                                Adults: {{ $reservation->adult_count }} · Children: {{ $reservation->child_count }}
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Stay</h6>
                            <p class="mb-1 small">Check-in: <strong>{{ $reservation->check_in_date?->format('d/m/Y') }} {{ $reservation->check_in_time }}</strong></p>
                            <p class="mb-1 small">Check-out: <strong>{{ $reservation->check_out_date?->format('d/m/Y') }} {{ $reservation->check_out_time }}</strong></p>
                            <p class="mb-1 small">
                                Room type: {{ $reservation->roomType->name ?? '—' }}<br>
                                Room(s):
                                @if($reservation->roomUnits->isNotEmpty())
                                    {{ $reservation->roomUnits->pluck('label')->join(', ') }}
                                @else
                                    <span class="text-muted">Unassigned</span>
                                @endif
                            </p>
                            <p class="mb-0 small">Status: <strong class="text-capitalize">{{ str_replace('_', ' ', $reservation->status) }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h6 class="mb-2">Financial summary</h6>
                        @php
                            $currency = $reservation->currency ?? ($hotel->currency ?? 'RWF');
                        @endphp
                        <div class="small">
                            <div>Total: <strong>{{ $currency }} {{ number_format((float)($reservation->total_amount ?? 0), 2) }}</strong></div>
                            <div>Paid: <strong>{{ $currency }} {{ number_format((float)($reservation->paid_amount ?? 0), 2) }}</strong></div>
                            <div>Balance: <strong class="{{ (float)($reservation->total_amount - $reservation->paid_amount) > 0 ? 'text-danger' : '' }}">{{ $currency }} {{ number_format(max(0, (float)($reservation->total_amount - $reservation->paid_amount)), 2) }}</strong></div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($canCollectPayment)
                            <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($reservation->reservation_number) }}&action=payment"
                               class="btn btn-primary btn-sm">
                                <i class="fa fa-money-bill-wave me-1"></i>Confirm / add payment
                            </a>
                        @endif
                        <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($reservation->reservation_number) }}&action=charges"
                           class="btn btn-outline-warning btn-sm">
                            <i class="fa fa-plus-circle me-1"></i>Add extra charges
                        </a>
                        <a href="{{ route('front-office.add-reservation', ['pre_registration' => $reservation->id]) }}"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-user-plus me-1"></i>Add / edit guests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

