@php
    $hideWingBadge = $hideWingBadge ?? false;
    $stay_check_in = $stay_check_in ?? '';
    $stay_check_out = $stay_check_out ?? '';
    if ($this->chargeLevelIsRoom && !empty($room['rates'])) { $rates = $room['rates']; } else { $rates = $room['room_type']['rates'] ?? []; }
    $locals = collect($rates)->firstWhere('rate_type', 'Locals');
    $fromAmount = $locals ? (float) $locals['amount'] : null;
    if ($fromAmount === null && count($rates) > 0) { $fromAmount = (float) collect($rates)->min('amount'); }
    $currency = \App\Models\Hotel::getHotel()->currency ?? 'RWF';
    $rateDisplay = $fromAmount !== null ? number_format($fromAmount) . ' ' . $currency : '—';
    $guestName = $room['current_guest_name'] ?? null;
    $status = $room['availability_status'] ?? 'vacant';
    $firstUnitId = $this->getFirstUnitIdForRoom($room);
    $blocked = (bool)($room['selected_period_blocked'] ?? false);
    $reason = (string)($room['selected_period_reason'] ?? '');
    $wingName = $room['wing_name'] ?? null;
    $stayId = $room['stay_reservation_id'] ?? null;
    $blockId = $room['period_block_reservation_id'] ?? null;
    $sameBooking = $stayId && $blockId && (int) $stayId === (int) $blockId;
    $roomLabel = $room['room_number'] ?? $room['name'];
    $nameExtra = ($room['name'] && (string)$room['name'] !== (string)$roomLabel) ? $room['name'] : null;
    $primaryResLink = $blockId ?: $stayId;
@endphp
<div class="border rounded p-2 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="flex-grow-1" style="min-width: 0;">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <div class="d-flex flex-wrap align-items-center gap-2" style="min-width: 0;">
                    @if($primaryResLink)
                        <a href="{{ route('front-office.reservation-details', $primaryResLink) }}" class="fw-semibold text-decoration-none">{{ $roomLabel }}</a>
                    @else
                        <span class="fw-semibold">{{ $roomLabel }}</span>
                    @endif
                    @if($nameExtra)
                        <span class="text-muted small">{{ $nameExtra }}</span>
                    @endif
                    @if(!$hideWingBadge && $wingName)
                        <span class="badge bg-light text-dark border">{{ $wingName }}</span>
                    @endif
                    <span class="badge bg-light text-dark border">Fl. {{ $room['floor'] ?? '—' }}</span>
                    <span class="text-nowrap small text-muted">{{ $rateDisplay }}</span>
                    @if ($guestName)
                        <span class="badge bg-primary">{{ $status === 'occupied' ? 'Occupied' : 'Due out' }}</span>
                    @else
                        <span class="badge bg-success">Vacant</span>
                    @endif
                    @if($blocked)
                        <span class="badge bg-danger">{{ $reason === 'occupied' ? 'Busy' : 'Reserved' }}</span>
                    @endif
                </div>
                <div class="d-flex gap-1 align-items-center justify-content-end flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openRoomDetail({{ $room['id'] }})" title="Room details"><i class="fa fa-eye"></i></button>
                    @if($firstUnitId && !$blocked)
                        <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $firstUnitId, 'check_in' => $stay_check_in, 'check_out' => $stay_check_out]) }}" class="btn btn-sm btn-primary" title="Add guest">
                            <i class="fa fa-user-plus"></i>
                        </a>
                    @elseif($firstUnitId && $blocked)
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Not free for selected dates">
                            <i class="fa fa-user-plus"></i>
                        </button>
                        <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $firstUnitId]) }}" class="btn btn-sm btn-outline-primary" title="Other dates">
                            <i class="fa fa-calendar-alt"></i>
                        </a>
                    @else
                        <button type="button" class="btn btn-sm btn-secondary" disabled title="No unit">
                            <i class="fa fa-ban"></i>
                        </button>
                    @endif
                </div>
            </div>
            @if($sameBooking && $blockId)
                <div class="small text-body-secondary mt-1">
                    <span class="text-muted">Stay</span>
                    —
                    <a href="{{ route('front-office.reservation-details', $blockId) }}" class="text-decoration-none fw-medium">{{ $room['period_block_guest_name'] ?? $room['current_guest_name'] ?? 'Guest' }}</a>
                    <span class="text-muted ms-1">{{ $room['period_block_check_in'] ?? '—' }} → {{ $room['period_block_check_out'] ?? '—' }}</span>
                </div>
            @else
                @if($stayId)
                    <div class="small text-body-secondary mt-1">
                        <span class="text-muted">In-house</span>
                        —
                        <a href="{{ route('front-office.reservation-details', $stayId) }}" class="text-decoration-none fw-medium">{{ $room['current_guest_name'] ?? 'Guest' }}</a>
                        <span class="text-muted ms-1">{{ $room['stay_check_in'] ?? '—' }} → {{ $room['stay_check_out'] ?? '—' }}</span>
                    </div>
                @endif
                @if($blocked && $blockId && ! $sameBooking)
                    <div class="small text-body-secondary mt-1">
                        <span class="text-muted">Overlaps</span>
                        —
                        <a href="{{ route('front-office.reservation-details', $blockId) }}" class="text-decoration-none fw-medium">{{ $room['period_block_guest_name'] ?? 'Guest' }}</a>
                        <span class="text-muted ms-1">{{ $room['period_block_check_in'] ?? '—' }} → {{ $room['period_block_check_out'] ?? '—' }}</span>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
