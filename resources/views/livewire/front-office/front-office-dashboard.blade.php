<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <h5 class="mb-1">Front Office Dashboard</h5>
                <p class="text-muted small mb-4">Summary by room status. Click a box to see the list.</p>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Total rooms</div>
                                <div class="h4 mb-0">{{ $totalRooms }}</div>
                                <div class="small text-muted">({{ $totalUnits }} units)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'vacant' ? 'border-primary border-2' : '' }}" wire:click="showDetail('vacant')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Vacant</div>
                                <div class="h4 mb-0 text-success">{{ $vacant }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'occupied' ? 'border-primary border-2' : 'border-primary' }}" wire:click="showDetail('occupied')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Occupied</div>
                                <div class="h4 mb-0 text-primary">{{ $occupied }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'reserved' ? 'border-primary border-2' : '' }}" wire:click="showDetail('reserved')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Reserved</div>
                                <div class="h4 mb-0 text-info">{{ $reserved }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'due_out' ? 'border-primary border-2' : '' }}" wire:click="showDetail('due_out')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Due out</div>
                                <div class="h4 mb-0 text-warning">{{ $dueOut }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'due_in' ? 'border-primary border-2' : '' }}" wire:click="showDetail('due_in')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Due in</div>
                                <div class="h4 mb-0">{{ $dueIn }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'dirty' ? 'border-primary border-2' : '' }}" wire:click="showDetail('dirty')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Dirty</div>
                                <div class="h4 mb-0 text-secondary">{{ $dirty }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'no_show' ? 'border-primary border-2' : '' }}" wire:click="showDetail('no_show')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">No show</div>
                                <div class="h4 mb-0 text-danger">{{ $noShow }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-decoration-none text-dark text-start {{ $detailFilter === 'open' ? 'border-primary border-2' : 'border-success' }}" wire:click="showDetail('open')" style="cursor: pointer;">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small text-uppercase mb-1">Open</div>
                                <div class="h4 mb-0 text-success">{{ $open }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                @if($detailFilter)
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">
                                @switch($detailFilter)
                                    @case('vacant') Vacant rooms (available) @break
                                    @case('occupied') Occupied rooms @break
                                    @case('reserved') Reserved (with booking) @break
                                    @case('due_out') Due out today @break
                                    @case('due_in') Due in today @break
                                    @case('dirty') Dirty rooms @break
                                    @case('no_show') No show (follow up) @break
                                    @case('open') Open (available) @break
                                    @default {{ $detailFilter }}
                                @endswitch
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearDetail">Close</button>
                        </div>
                        <div class="card-body">
                            @if(count($detailItems) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Room / Unit</th>
                                                @if(in_array($detailFilter, ['occupied', 'reserved', 'due_out', 'due_in', 'no_show']))
                                                    <th>Guest</th>
                                                    <th>Check-in</th>
                                                    <th>Check-out</th>
                                                    <th>Reservation</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($detailItems as $row)
                                                <tr>
                                                    <td>
                                                        <span class="fw-semibold">{{ $row['room_label'] }}</span>
                                                        @if(!empty($row['room_name']))
                                                            <span class="text-muted small">({{ $row['room_name'] }})</span>
                                                        @endif
                                                    </td>
                                                    @if(in_array($detailFilter, ['occupied', 'reserved', 'due_out', 'due_in', 'no_show']))
                                                        <td>{{ $row['guest_name'] ?? '—' }}</td>
                                                        <td>{{ $row['check_in'] ?? '—' }}</td>
                                                        <td>{{ $row['check_out'] ?? '—' }}</td>
                                                        <td class="small">{{ $row['reservation_number'] ?? '—' }}</td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="small text-muted mb-0 mt-2">{{ count($detailItems) }} item(s)</p>
                            @else
                                <p class="text-muted mb-0">No rooms in this category.</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if(! auth()->user()?->isEffectiveReceptionist())
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3">Quick links</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('front-office.rooms') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-bed me-1"></i>Rooms</a>
                            <a href="{{ route('module.show', 'front-office') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-calendar-alt me-1"></i>Reservations (calendar)</a>
                            <a href="{{ route('front-office.add-reservation') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>New reservation</a>
                            <a href="{{ route('front-office.quick-group-booking') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-users me-1"></i>Quick group booking</a>
                            <a href="{{ route('front-office.self-registered') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-clipboard-list me-1"></i>Pre-arrival</a>
                            @php $regHotel = \App\Models\Hotel::getHotel(); $welcomeUrl = url('/welcome' . ($regHotel ? '?hotel=' . $regHotel->id : '')); @endphp
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('{{ $welcomeUrl }}'); alert('Pre-arrival registration URL copied to clipboard.');"><i class="fa fa-link me-1"></i>Copy pre-arrival URL</button>
                            <a href="{{ route('additional-charges.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-plus-circle me-1"></i>Charges</a>
                            <a href="{{ route('front-office.reports') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-chart-bar me-1"></i>Reports</a>
                            <a href="{{ route('front-office.daily-accommodation-report') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-bed me-1"></i>Rooms daily report</a>
                        </div>
                    </div>
                </div>
                @endif

                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Client list</h6>
                                <p class="text-muted small mb-0">Reservations + pre-arrivals for this hotel.</p>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6">
                                <div class="form-floating">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Search by name, phone or email..."
                                        wire:model.debounce.300ms="clientSearch"
                                    >
                                    <label>Search clients</label>
                                </div>
                            </div>
                        </div>

                        @if(count($clients) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Stays</th>
                                            <th>Pre-arrivals</th>
                                            <th>Last activity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clients as $c)
                                            <tr>
                                                <td class="fw-semibold">{{ $c['name'] ?: '—' }}</td>
                                                <td>{{ $c['phone'] ?: '—' }}</td>
                                                <td>{{ $c['email'] ?: '—' }}</td>
                                                <td>{{ (int) ($c['stay_count'] ?? 0) }}</td>
                                                <td>{{ (int) ($c['pre_count'] ?? 0) }}</td>
                                                <td class="small">{{ $c['last_activity'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0 mt-2">{{ count($clients) }} client(s) shown</p>
                        @else
                            <p class="text-muted mb-0">No clients found.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
