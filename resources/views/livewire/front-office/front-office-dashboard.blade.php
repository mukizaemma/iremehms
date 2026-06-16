@php
    $displayedRows = $this->displayedRoomRows;
    $summaryCards = [
        ['key' => 'all', 'label' => 'Total rooms', 'count' => $totalUnits, 'tone' => 'total', 'icon' => 'fa-building'],
        ['key' => 'vacant', 'label' => 'Vacant', 'count' => $vacant, 'tone' => 'vacant', 'icon' => 'fa-door-open'],
        ['key' => 'occupied', 'label' => 'Occupied', 'count' => $occupied, 'tone' => 'occupied', 'icon' => 'fa-user'],
        ['key' => 'due_out', 'label' => 'Due out', 'count' => $dueOut, 'tone' => 'due-out', 'icon' => 'fa-sign-out-alt'],
        ['key' => 'due_in', 'label' => 'Due in', 'count' => $dueIn, 'tone' => 'due-in', 'icon' => 'fa-sign-in-alt'],
        ['key' => 'dirty', 'label' => 'Dirty', 'count' => $dirty, 'tone' => 'dirty', 'icon' => 'fa-broom'],
        ['key' => 'no_show', 'label' => 'No show', 'count' => $noShow, 'tone' => 'no-show', 'icon' => 'fa-user-times'],
    ];
@endphp
<div class="fo-dashboard-fullwidth">
    <div class="fo-dashboard-toolbar d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-uppercase">Summary</h5>
            <p class="text-muted small mb-0">Room status overview — click a card to filter the table below.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($tableFilter && $tableFilter !== 'all')
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearTableFilter">
                    <i class="fa fa-times me-1"></i>Clear filter
                </button>
            @endif
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refreshDashboard">
                <i class="fa fa-sync-alt me-1" wire:loading.class="fa-spin" wire:target="refreshDashboard"></i>Refresh
            </button>
        </div>
    </div>

    <div class="fo-summary-cards mb-4">
        @foreach($summaryCards as $card)
            <button
                type="button"
                class="fo-summary-card fo-summary-card--{{ $card['tone'] }} {{ ($tableFilter === $card['key'] || ($card['key'] === 'all' && ! $tableFilter)) ? 'fo-summary-card--active' : '' }}"
                wire:click="toggleTableFilter('{{ $card['key'] }}')"
            >
                <span class="fo-summary-card__icon"><i class="fa {{ $card['icon'] }}"></i></span>
                <span class="fo-summary-card__label">{{ $card['label'] }}</span>
                <span class="fo-summary-card__count">{{ $card['count'] }} {{ Str::plural('Room', $card['count']) }}</span>
            </button>
        @endforeach
    </div>

    <div class="fo-rooms-panel card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="mb-0 fw-bold">Rooms overview</h6>
                <p class="text-muted small mb-0">
                    {{ count($displayedRows) }} of {{ count($roomRows) }} unit(s)
                    @if($tableFilter && $tableFilter !== 'all')
                        — filtered by <span class="fw-semibold">{{ str_replace('_', ' ', $tableFilter) }}</span>
                    @else
                        — all units (occupied &amp; in-house first)
                    @endif
                </p>
            </div>
            <span class="badge bg-light text-dark border">Standard view</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table fo-rooms-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Room type</th>
                            <th>Status</th>
                            <th>Guest names</th>
                            <th>Company</th>
                            <th>B. source</th>
                            <th>Check in</th>
                            <th>Check out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($displayedRows as $row)
                            @php
                                $newReservationUrl = route('front-office.add-reservation', ['room_unit_id' => $row['unit_id']]);
                            @endphp
                            <tr class="fo-room-row fo-room-row--{{ $row['status_key'] }}">
                                <td class="fw-semibold">
                                    <a href="{{ $newReservationUrl }}" class="fo-room-link" title="New reservation for this room">{{ $row['room_number'] }}</a>
                                </td>
                                <td>
                                    <a href="{{ $newReservationUrl }}" class="fo-room-link" title="New reservation for this room">{{ $row['room_type'] }}</a>
                                </td>
                                <td>{{ $row['status'] }}</td>
                                <td>
                                    @if($row['guest_name'])
                                        {{ $row['guest_name'] }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $row['company'] ?: '—' }}</td>
                                <td>{{ $row['booking_source'] ?: '—' }}</td>
                                <td>{{ $row['check_in'] ?: '—' }}</td>
                                <td>{{ $row['check_out'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">No rooms match this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
