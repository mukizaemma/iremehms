<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <a href="{{ route('front-office.reports') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-arrow-left me-1"></i>Back to reports</a>
            <h5 class="mb-1 fw-bold">Complimentary services report</h5>
            <p class="text-muted small mb-0">Room and/or meal complimentary offers for financial tracking. Reference rates × nights estimate waived value.</p>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label class="small text-muted">From</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="date_from">
            </div>
            <div>
                <label class="small text-muted">To</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="date_to">
            </div>
            <a href="{{ route('front-office.reports.complementary.print', ['date_from' => $date_from, 'date_to' => $date_to, 'auto' => 1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-print me-1"></i>Print
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="small text-muted">Stays with complimentary</div>
                    <div class="fs-4 fw-bold">{{ $summary['count'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="small text-muted">Est. room value waived</div>
                    <div class="fs-5 fw-bold">{{ $currency }} {{ number_format($summary['room_waived'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="small text-muted">Est. meal value waived</div>
                    <div class="fs-5 fw-bold">{{ $currency }} {{ number_format($summary['meal_waived'] ?? 0, 2) }}</div>
                    <div class="small text-muted">Total waived: {{ $currency }} {{ number_format($summary['total_waived'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($rows === [])
                <p class="text-muted small p-4 mb-0">No complimentary room or meal services in this period.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Res. #</th>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Stay</th>
                                <th>Services</th>
                                <th>Board</th>
                                <th>Reason</th>
                                <th class="text-end">Room waived</th>
                                <th class="text-end">Meal waived</th>
                                <th class="text-end">Total waived</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('front-office.reservation-details', $row['reservation_id']) }}" class="text-decoration-none">{{ $row['reservation_number'] }}</a>
                                    </td>
                                    <td>{{ $row['guest_name'] }}</td>
                                    <td>{{ $row['room'] }}</td>
                                    <td class="small">{{ $row['check_in'] }} → {{ $row['check_out'] }}<br><span class="text-muted">{{ $row['nights'] }} night(s)</span></td>
                                    <td><span class="badge bg-warning text-dark">{{ $row['services'] }}</span></td>
                                    <td>{{ $row['meal_plan'] }}</td>
                                    <td class="small" style="max-width:200px">{{ $row['reason'] }}</td>
                                    <td class="text-end small">{{ $row['is_room_complimentary'] ? $currency.' '.number_format($row['room_value_waived'], 2) : '—' }}</td>
                                    <td class="text-end small">{{ $row['is_meal_complimentary'] ? $currency.' '.number_format($row['meal_value_waived'], 2) : '—' }}</td>
                                    <td class="text-end fw-semibold">{{ $currency }} {{ number_format($row['total_value_waived'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
