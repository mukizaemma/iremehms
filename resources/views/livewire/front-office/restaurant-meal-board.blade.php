@php
    $list = $this->mealList;
    $rows = $list['rows'];
@endphp
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h5 class="mb-1 fw-bold">Restaurant — meal board</h5>
            <p class="text-muted small mb-0">In-house guests entitled to each meal today. Use this list so kitchen and service know who to prepare for.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="small text-muted mb-0">Date</label>
            <input type="date" class="form-control form-control-sm" wire:model.live="listDate" style="max-width: 160px;">
            <a href="{{ route('front-office.restaurant.print', ['date' => $listDate, 'meal' => $mealTab, 'auto' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-print me-1"></i>Print list
            </a>
        </div>
    </div>

    <div class="btn-group btn-group-sm mb-3">
        <button type="button" class="btn {{ $mealTab === 'breakfast' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setMealTab('breakfast')">
            <i class="fa fa-coffee me-1"></i>Breakfast
        </button>
        <button type="button" class="btn {{ $mealTab === 'lunch' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setMealTab('lunch')">
            <i class="fa fa-utensils me-1"></i>Lunch
        </button>
        <button type="button" class="btn {{ $mealTab === 'dinner' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setMealTab('dinner')">
            <i class="fa fa-moon me-1"></i>Dinner
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 small text-muted">
            <strong>BB</strong> = breakfast only &nbsp;·&nbsp;
            <strong>HB</strong> = breakfast + dinner &nbsp;·&nbsp;
            <strong>FB</strong> = breakfast, lunch + dinner &nbsp;·&nbsp;
            Complimentary meals = all meals included (see complimentary report for financials)
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0 fw-semibold">{{ $list['meal_label'] }} — {{ \Carbon\Carbon::parse($listDate)->format('d M Y') }}</h6>
            <span class="badge bg-primary">{{ count($rows) }} reservation(s) · {{ $list['total_covers'] }} cover(s)</span>
        </div>
        <div class="card-body p-0">
            @if($rows->isEmpty())
                <p class="text-muted small p-4 mb-0">No in-house guests are entitled to {{ strtolower($list['meal_label']) }} on this date.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Room</th>
                                <th>Guest</th>
                                <th>Res. #</th>
                                <th>Board</th>
                                <th class="text-center">Covers</th>
                                <th>Time / location</th>
                                <th>Notes</th>
                                <th>Check-out</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['room'] }}</td>
                                    <td>
                                        <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none">{{ $row['guest_name'] }}</a>
                                    </td>
                                    <td class="text-muted small">{{ $row['reservation_number'] }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border" title="{{ $row['meal_plan_label'] }}">{{ $row['meal_plan'] }}</span>
                                    </td>
                                    <td class="text-center">{{ $row['covers'] }}</td>
                                    <td class="small">{{ $row['preferences'] }}</td>
                                    <td class="small text-muted">{{ $row['notes'] ?? '—' }}</td>
                                    <td class="small text-muted">{{ $row['check_out'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
