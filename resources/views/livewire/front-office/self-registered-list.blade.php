<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div>
                            <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-arrow-left me-1"></i>Back</a>
                            <h5 class="mb-0">Pre-arrival</h5>
                        </div>
                        <div>
                            @php $regHotel = \App\Models\Hotel::getHotel(); $welcomeUrl = url('/welcome' . ($regHotel ? '?hotel=' . $regHotel->id : '')); @endphp
                            <a href="{{ $welcomeUrl }}" target="_blank" class="btn btn-outline-primary btn-sm me-2"><i class="fa fa-external-link-alt me-1"></i>Open registration page</a>
                            <button type="button" class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText('{{ $welcomeUrl }}'); alert('URL copied to clipboard.');"><i class="fa fa-link me-1"></i>Copy registration URL</button>
                        </div>
                    </div>
                    @include('livewire.front-office.partials.front-office-quick-nav')
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="search" placeholder="Search name, ID, reference…">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" wire:model.live="statusFilter">
                                    <option value="">All statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="checked_in">Checked in</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" wire:model.live="reservationFilter">
                                    <option value="">All reservations</option>
                                    @foreach($reservationsWithGroups as $r)
                                        <option value="{{ $r->id }}">{{ $r->group_name ?: $r->reservation_number }} ({{ $r->reservation_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" wire:model.live="groupFilter">
                                    <option value="">All groups</option>
                                    @foreach($groupIdentifiers as $gid)
                                        <option value="{{ $gid }}">Group {{ \Illuminate\Support\Str::limit($gid, 8) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        @if($preRegistrations->isEmpty())
                            <p class="text-muted small mb-0 p-4">No pre-arrival registrations found.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Full name</th>
                                            <th>ID/Passport</th>
                                            <th>Reference</th>
                                            <th>Contact</th>
                                            <th>Private notes</th>
                                            <th>Room</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($preRegistrations as $p)
                                            <tr>
                                                <td>
                                                    <strong>{{ $p->guest_name }}</strong>
                                                    @if($p->group_identifier)
                                                        <span class="badge bg-secondary small">Online group form</span>
                                                    @endif
                                                    @if($p->reservation)
                                                        <div class="small text-muted">
                                                            Reservation:
                                                            <a href="{{ route('front-office.reservation-details', ['reservation' => $p->reservation->id ?? $p->reservation_reference]) }}" target="_blank">
                                                                {{ $p->reservation->group_name ?: $p->reservation->reservation_number }}
                                                            </a>
                                                        </div>
                                                    @elseif($p->reservation_reference)
                                                        <div class="small text-muted">
                                                            Reference: {{ $p->reservation_reference }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $p->guest_id_number ?? '—' }}</td>
                                                <td>{{ $p->reservation_reference ?? '—' }}</td>
                                                <td>{{ $p->guest_phone ?: ($p->guest_email ?: '—') }}</td>
                                                <td><span class="small">{{ $p->private_notes ? \Illuminate\Support\Str::limit($p->private_notes, 40) : '—' }}</span></td>
                                                <td>
                                                    @if($p->roomUnit)
                                                        {{ $p->roomUnit->label }} @if($p->roomUnit->room)({{ $p->roomUnit->room->name }})@endif
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($p->status === 'pending')
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    @elseif($p->status === 'assigned')
                                                        <span class="badge bg-info">Assigned</span>
                                                    @else
                                                        <span class="badge bg-success">Checked in</span>
                                                    @endif
                                                </td>
                                                <td>{{ $p->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" wire:click="openEdit({{ $p->id }})" title="Modify"><i class="fa fa-edit"></i></button>
                                                    @if($p->status === 'pending')
                                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openAssign({{ $p->id }})">Assign room</button>
                                                    @elseif($p->status === 'assigned')
                                                        <button type="button" class="btn btn-outline-success btn-sm" wire:click="markCheckedIn({{ $p->id }})">Check in</button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($assigningId === $p->id)
                                                <tr class="table-secondary">
                                                    <td colspan="9" class="py-3">
                                                        @php
                                                            $reservedUnits = $this->reservedRoomUnitsForAssign;
                                                            $otherUnits = $this->otherRoomUnitsForAssign;
                                                            $hasReservation = $p->reservation_id && $reservedUnits->isNotEmpty();
                                                        @endphp
                                                        @if($hasReservation)
                                                            <p class="small text-muted mb-1">
                                                                This guest is linked to reservation
                                                                <strong>{{ $p->reservation_reference ?? $p->reservation?->reservation_number }}</strong>.
                                                                Assign to one of the reserved rooms to only verify details here, or pick another room to open the full reservation form.
                                                            </p>
                                                            <p class="small text-muted mb-1">
                                                                Reservation stay dates:
                                                                <strong>
                                                                    {{ $p->reservation?->check_in_date?->format('d M Y') }}
                                                                    –
                                                                    {{ $p->reservation?->check_out_date?->format('d M Y') }}
                                                                </strong>
                                                                (these are used as the default check-in / check-out for this guest).
                                                            </p>
                                                            <p class="small text-info mb-2">You can assign multiple pre-registered guests to the same room (e.g. 2 people in one room from the booking).</p>
                                                        @endif
                                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                                            <span class="fw-medium">Assign to room:</span>
                                                            <select class="form-select form-select-sm" style="width: auto; min-width: 180px;" wire:model.live="assignRoomUnitId">
                                                                <option value="">Select room…</option>
                                                                @if($hasReservation)
                                                                    <optgroup label="Reserved rooms (verify & assign)">
                                                                        @foreach($reservedUnits as $u)
                                                                            <option value="{{ $u->id }}">{{ $u->label }} @if($u->room)({{ $u->room->name }})@endif</option>
                                                                        @endforeach
                                                                    </optgroup>
                                                                    @if($otherUnits->isNotEmpty())
                                                                        <optgroup label="Other rooms">
                                                                            @foreach($otherUnits as $u)
                                                                                <option value="{{ $u->id }}">{{ $u->label }} @if($u->room)({{ $u->room->name }})@endif</option>
                                                                            @endforeach
                                                                        </optgroup>
                                                                    @endif
                                                                @else
                                                                    @foreach($roomUnits as $u)
                                                                        <option value="{{ $u->id }}">{{ $u->label }} @if($u->room)({{ $u->room->name }})@endif</option>
                                                                    @endforeach
                                                                @endif
                                                            </select>
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <div class="input-group input-group-sm" style="width: 180px;">
                                                                    <span class="input-group-text">Check-in</span>
                                                                    <input type="date" class="form-control" wire:model.live="assignCheckInDate" min="{{ now()->toDateString() }}">
                                                                </div>
                                                                <div class="input-group input-group-sm" style="width: 190px;">
                                                                    <span class="input-group-text">Check-out</span>
                                                                    <input type="date" class="form-control" wire:model.live="assignCheckOutDate" min="{{ now()->toDateString() }}">
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn btn-primary btn-sm" wire:click="saveAssign" wire:loading.attr="disabled">Save</button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cancelAssign">Cancel</button>
                                                        </div>
                                                        @error('assignRoomUnitId')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                                        @error('assignCheckInDate')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                                        @error('assignCheckOutDate')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                                    </td>
                                                </tr>
                                            @endif
                                            @if($editingId === $p->id)
                                                <tr class="table-warning">
                                                    <td colspan="9" class="py-3">
                                                        <div class="small fw-semibold mb-2">Modify pre-arrival information</div>
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_name" placeholder="Full name"></div>
                                                            <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_id_number" placeholder="ID/Passport"></div>
                                                            <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_country" placeholder="Country/Region"></div>
                                                            <div class="col-md-2"><input type="email" class="form-control form-control-sm" wire:model="edit_guest_email" placeholder="Email"></div>
                                                            <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_phone" placeholder="Phone"></div>
                                                        </div>
                                                        <div class="row g-2 mb-2">
                                                            <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_profession" placeholder="Profession/Occupation"></div>
                                                            <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="edit_guest_stay_purpose" placeholder="Purpose of stay"></div>
                                                            <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="edit_organization" placeholder="Organization/Company"></div>
                                                            <div class="col-md-4"><input type="text" class="form-control form-control-sm" wire:model="edit_private_notes" placeholder="Private notes"></div>
                                                        </div>
                                                        <button type="button" class="btn btn-primary btn-sm me-1" wire:click="saveEdit" wire:loading.attr="disabled">Save changes</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="cancelEdit">Cancel</button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
