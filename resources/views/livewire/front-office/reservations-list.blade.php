<div class="reservations-list">
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="mb-0">{{ $tabLabel }}</h5>
        <a href="{{ route('front-office.add-reservation') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>New reservation</a>
    </div>

    {{-- Toolbar: view toggle, Print GR, Make Group, Export, Search --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn {{ $viewMode === 'list' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewMode('list')" title="List view"><i class="fa fa-list"></i></button>
            <button type="button" class="btn {{ $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewMode('grid')" title="Grid view"><i class="fa fa-th-large"></i></button>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print me-1"></i>Print GR</button>
        <button type="button" class="btn btn-outline-secondary btn-sm">Make Group</button>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Export</button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#">Export CSV</a></li>
                <li><a class="dropdown-item" href="#">Export PDF</a></li>
            </ul>
        </div>
        <div class="ms-auto" style="min-width: 220px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="fa fa-search"></i></span>
                <input type="text" class="form-control" placeholder="Search name, phone, email, reservation #…" wire:model.live.debounce.300ms="search" autocomplete="off">
            </div>
        </div>
    </div>

    @if($reservations->isEmpty())
        <div class="alert alert-info mb-0">
            <i class="fa fa-info-circle me-2"></i>No reservations match the current filter.
            @if($search !== '')
                <button type="button" class="btn btn-link btn-sm p-0 ms-2" wire:click="clearSearch">Clear search</button>
            @elseif($tab !== 'all')
                <a href="{{ route('front-office.reservations', ['tab' => 'all']) }}" class="btn btn-link btn-sm p-0 ms-2">Show all active</a>
            @endif
        </div>
    @else
        @if($viewMode === 'grid')
            {{-- Grid: cards per reservation room unit --}}
            <div class="row g-3">
                @foreach($reservations as $row)
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100 reservation-card">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                                        @php
                                            $statusColor = $row['status_badge'] ?? 'secondary';
                                        @endphp
                                        <span class="rounded" style="width: 4px; height: 2.2rem; flex-shrink: 0; background-color: var(--bs-{{ $statusColor }});"></span>
                                        <div class="min-w-0">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="fw-semibold text-truncate" title="{{ $row['guest_name'] }}">
                                                    <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none text-dark">
                                                        {{ $row['guest_name'] }}
                                                    </a>
                                                </div>
                                                <span class="badge bg-{{ $statusColor }} small">{{ $row['status_label'] ?? ucfirst(str_replace('_',' ',$row['status'])) }}</span>
                                            </div>
                                            <div class="small text-muted">
                                                <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none text-muted">{{ $row['reservation_number'] }}</a>
                                                @if($row['room_label'] !== '—') · Room {{ $row['room_label'] }} @else · Unassigned @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-link btn-sm text-dark p-0" data-bs-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('front-office.reservation-details', $row['reservation_id']) }}">View details</a></li>
                                            <li><a class="dropdown-item" href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}">Open in booking calendar</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="small text-muted mb-2">
                                    <div>Check-in: {{ $row['check_in'] }}</div>
                                    <div>Check-out: {{ $row['check_out'] }}</div>
                                    <div class="mt-1">
                                        <span class="badge bg-light text-dark">{{ $row['nights'] }} Night(s)</span>
                                        @if($row['is_arrival_today'] ?? false)
                                            <span class="badge bg-info ms-1">Arriving today</span>
                                        @endif
                                        @if($row['is_overstay'] ?? false)
                                            <span class="badge bg-danger ms-1">Overstay</span>
                                        @elseif($row['is_due_today'] ?? false)
                                            <span class="badge bg-warning text-dark ms-1">Departure today</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="small mb-2">
                                    <span class="text-muted">Booking Date:</span> {{ $row['booking_date'] }}
                                    <span class="ms-2"><i class="fa fa-user small"></i> {{ $row['adult_count'] }} <i class="fa fa-child small ms-1"></i> {{ $row['child_count'] }}</span>
                                </div>
                                <div class="small mb-2">
                                    <span class="text-muted">Room / Rate:</span>
                                    @if($row['room_label'] === '—')
                                        <span class="text-muted">Unassigned</span>
                                    @else
                                        <span class="fw-semibold">{{ $row['room_label'] }}</span>
                                    @endif
                                    / {{ $row['rate_plan'] }}
                                </div>
                                <div class="pt-2 border-top small">
                                    <div class="d-flex justify-content-between"><span>Total:</span> <span>{{ $row['currency'] }} {{ number_format($row['total'], 2) }}</span></div>
                                    <div class="d-flex justify-content-between"><span>Paid:</span> <span>{{ $row['currency'] }} {{ number_format($row['paid'], 2) }}</span></div>
                                    <div class="d-flex justify-content-between fw-semibold"><span>Balance:</span> <span>{{ $row['currency'] }} {{ number_format($row['balance'], 2) }}</span></div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        @if($row['can_check_in'] ?? false)
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openCheckInModal({{ $row['reservation_id'] }})">Check-in</button>
                                        @endif
                                        @if($row['can_checkout'] ?? false)
                                            <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=checkout" class="btn btn-sm btn-success">Check out</a>
                                        @endif
                                        @if($row['can_extend_stay'] ?? false)
                                            <button type="button" class="btn btn-sm btn-outline-info" wire:click="openExtendModal({{ $row['reservation_id'] }})">Extend stay</button>
                                        @endif
                                        @if($row['can_add_payment'])
                                            <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=payment" class="btn btn-sm btn-outline-secondary">Add payment</a>
                                        @endif
                                        @if($row['can_add_extras'])
                                            <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=charges" class="btn btn-sm btn-outline-warning">Add extra charges</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- List: table --}}
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Guest / Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Nights</th>
                                <th>Booking date</th>
                                <th>Guests</th>
                                <th>Room / Rate</th>
                                <th>Actions</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reservations as $row)
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="fw-medium">
                                                <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none text-dark">
                                                    {{ $row['guest_name'] }}
                                                </a>
                                            </div>
                                            <div class="small text-muted">
                                                <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none text-muted">{{ $row['reservation_number'] }}</a>
                                                @if($row['room_label'] !== '—') · Room {{ $row['room_label'] }} @else · Unassigned @endif
                                            </div>
                                            <div class="mt-1">
                                                <span class="badge bg-{{ $row['status_badge'] ?? 'secondary' }} small">{{ $row['status_label'] ?? ucfirst(str_replace('_',' ',$row['status'])) }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small">{{ $row['check_in'] }}</td>
                                    <td class="small">{{ $row['check_out'] }}</td>
                                    <td>{{ $row['nights'] }}</td>
                                    <td class="small">{{ $row['booking_date'] }}</td>
                                    <td class="small">{{ $row['adult_count'] }} / {{ $row['child_count'] }}</td>
                                    <td class="small">{{ $row['room_type'] }} / {{ $row['rate_plan'] }}</td>
                                    <td class="text-end small">{{ $row['currency'] }} {{ number_format($row['total'], 2) }}</td>
                                    <td class="text-end small">{{ $row['currency'] }} {{ number_format($row['paid'], 2) }}</td>
                                    <td class="text-end small fw-medium">{{ $row['currency'] }} {{ number_format($row['balance'], 2) }}</td>
                                    <td class="small">
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($row['can_check_in'] ?? false)
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openCheckInModal({{ $row['reservation_id'] }})">Check-in</button>
                                            @endif
                                            @if($row['can_checkout'] ?? false)
                                                <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=checkout" class="btn btn-sm btn-success">Check out</a>
                                            @endif
                                            @if($row['can_extend_stay'] ?? false)
                                                <button type="button" class="btn btn-sm btn-outline-info" wire:click="openExtendModal({{ $row['reservation_id'] }})">Extend stay</button>
                                            @endif
                                            @if($row['can_add_payment'])
                                                <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=payment" class="btn btn-sm btn-outline-secondary">Add payment</a>
                                            @endif
                                            @if($row['can_add_extras'])
                                                <a href="{{ route('module.show', 'front-office') }}?reservation={{ urlencode($row['reservation_number']) }}&action=charges" class="btn btn-sm btn-outline-warning">Add extra charges</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    @include('livewire.front-office.partials.check-in-modal')
    @include('livewire.front-office.partials.extend-stay-modal')

    <style>
.nav-tabs-custom .nav-link { border: 1px solid transparent; border-radius: 0.25rem; margin-right: 0.25rem; }
.nav-tabs-custom .nav-link.active { background: var(--bs-primary); color: #fff; border-color: var(--bs-primary); }
.reservation-card { transition: box-shadow 0.2s; }
.reservation-card:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1) !important; }
    </style>
</div>
