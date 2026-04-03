@push('styles')
<style>
    @media print {
        .fo-report-no-print { display: none !important; }
        .fo-report-print-header { display: block !important; }
        body .sidebar,
        body .sidebar-toggler,
        body .content > nav.navbar { display: none !important; }
        body .content { margin: 0 !important; padding: 0 !important; }
        body .container-fluid.pt-4 { padding-top: 0 !important; }
        .fo-report-print-root { background: #fff !important; box-shadow: none !important; }
        .fo-report-print-root .bg-light { background: #fff !important; }
    }
    .fo-report-print-header { display: none; }
</style>
@endpush

@php
    $hotelReport = \App\Models\Hotel::getHotel();
    $foAvg = static function (float $revenue, $nights): float {
        $n = (float) $nights;
        return $n > 0 ? $revenue / $n : 0.0;
    };
@endphp

<div class="container-fluid py-4 fo-report-print-root">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="fo-report-print-header mb-3 pb-2 border-bottom">
                    @if($hotelReport)
                        <x-hotel-document-header :hotel="$hotelReport" subtitle="Front Office sales report" />
                    @endif
                    <div class="small text-muted mt-2">
                        Period: <strong>{{ $date_from }}</strong> to <strong>{{ $date_to }}</strong>
                        @if($room_type_id !== '' || $room_unit_id !== '' || $filter_reservation_type !== '' || $filter_rate_plan !== '' || $filter_business_source !== '')
                            <span class="d-block mt-1">Filters:
                                @if($room_type_id !== '')
                                    Room type: {{ $roomTypes->firstWhere('id', (int) $room_type_id)?->name ?? $room_type_id }}
                                @endif
                                @if($room_unit_id !== '')
                                    · Room/unit: {{ $rooms->firstWhere('id', (int) $room_unit_id)?->label ?? $room_unit_id }}
                                @endif
                                @if($filter_reservation_type !== '')
                                    · Reservation type: {{ $filter_reservation_type }}
                                @endif
                                @if($filter_rate_plan !== '')
                                    · Rate: {{ $filter_rate_plan }}
                                @endif
                                @if($filter_business_source !== '')
                                    · Business source: {{ $filter_business_source }}
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mb-3 fo-report-no-print">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                        <h5 class="mb-0">Front Office sales reports</h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fa fa-print me-1"></i>Print report
                        </button>
                    </div>
                    @include('livewire.front-office.partials.front-office-quick-nav')
                </div>

                <div class="card mb-3 fo-report-no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small">From</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_from">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">To</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_to">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Room type</label>
                                <select class="form-select form-select-sm" wire:model.defer="room_type_id">
                                    <option value="">All types</option>
                                    @foreach($roomTypes as $rt)
                                        <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Room / unit</label>
                                <select class="form-select form-select-sm" wire:model.defer="room_unit_id">
                                    <option value="">All rooms</option>
                                    @foreach($rooms as $u)
                                        <option value="{{ $u->id }}">{{ $u->label }} @if($u->room && $u->room->name) ({{ $u->room->name }}) @endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Reservation type</label>
                                <select class="form-select form-select-sm" wire:model.defer="filter_reservation_type">
                                    <option value="">All</option>
                                    @foreach($reservationTypeOptions as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Rate type</label>
                                <select class="form-select form-select-sm" wire:model.defer="filter_rate_plan">
                                    <option value="">All</option>
                                    @foreach($ratePlanOptions as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Business source</label>
                                <select class="form-select form-select-sm" wire:model.defer="filter_business_source">
                                    <option value="">All</option>
                                    @foreach($businessSourceOptions as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3 gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="clearFilters">
                                Clear
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="applyFilters">
                                Filter
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Total revenue</div>
                                <div class="h5 mb-0">{{ \App\Helpers\CurrencyHelper::format($summary['revenue'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small mb-1">Avg. rate per night</div>
                                <div class="h5 mb-0">{{ \App\Helpers\CurrencyHelper::format($summary['avg_rate'] ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 border-primary border-opacity-25">
                    <div class="card-header py-2 bg-primary bg-opacity-10">
                        <strong><i class="fa fa-bullseye me-2"></i>Management insights</strong>
                        <span class="text-muted small ms-2">Same date range &amp; filters as above</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <h6 class="text-muted small text-uppercase mb-2">By reservation type</h6>
                                @if(count($byReservationType ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light"><tr><th>Type</th><th class="text-end">Revenue</th><th class="text-end">Avg. rate / night</th></tr></thead>
                                            <tbody>
                                                @foreach($byReservationType as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['revenue']) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($foAvg((float) $row['revenue'], $row['nights'] ?? 0)) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No data in this period.</p>
                                @endif
                            </div>
                            <div class="col-lg-4">
                                <h6 class="text-muted small text-uppercase mb-2">By rate type</h6>
                                @if(count($byRatePlan ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light"><tr><th>Rate</th><th class="text-end">Revenue</th><th class="text-end">Avg. rate / night</th></tr></thead>
                                            <tbody>
                                                @foreach($byRatePlan as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['revenue']) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($foAvg((float) $row['revenue'], $row['nights'] ?? 0)) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No data in this period.</p>
                                @endif
                            </div>
                            <div class="col-lg-4">
                                <h6 class="text-muted small text-uppercase mb-2">By business source</h6>
                                @if(count($byBusinessSource ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light"><tr><th>Source</th><th class="text-end">Revenue</th><th class="text-end">Avg. rate / night</th></tr></thead>
                                            <tbody>
                                                @foreach($byBusinessSource as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['revenue']) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($foAvg((float) $row['revenue'], $row['nights'] ?? 0)) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No data in this period.</p>
                                @endif
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="text-muted small text-uppercase mb-2">Top clients in period &amp; returning guests</h6>
                        <p class="small text-muted mb-2 fo-report-no-print">Ranked by revenue in this report. <strong>Lifetime stays</strong> counts all non-cancelled reservations at this hotel (same email, or phone if no email). <span class="badge bg-info">Returning</span> = at least two stays overall.</p>
                        @if(count($topGuests ?? []) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Guest</th>
                                            <th class="text-end">Revenue (period)</th>
                                            <th class="text-end">Lifetime stays</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topGuests as $g)
                                            <tr>
                                                <td><strong>{{ $g['guest'] }}</strong></td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($g['revenue']) }}</td>
                                                <td class="text-end">{{ $g['lifetime_stays'] }}</td>
                                                <td>
                                                    @if(!empty($g['is_recurring']))
                                                        <span class="badge bg-info">Returning</span>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No guests in this period.</p>
                        @endif
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header py-2">Accommodation payments by type (period)</div>
                            <div class="card-body">
                                <p class="small text-muted mb-2">Amounts use each payment’s <strong>sales date</strong> when staff mark a payment as a debt settlement; otherwise the date the payment was received.</p>
                                @if(count($paymentsByType ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>Payment type</th><th class="text-end">Amount</th></tr></thead>
                                            <tbody>
                                                @foreach($paymentsByType as $row)
                                                    <tr>
                                                        <td>{{ $row['label'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['total']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No recorded payments in this period.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm border-start border-warning border-3">
                            <div class="card-header py-2 bg-warning bg-opacity-10">
                                <strong><i class="fa fa-hand-holding-usd me-2"></i>Outstanding balance (debt) settlements</strong>
                                <span class="text-muted small ms-2">Confirmed by reception / management; shows payment method and sales date</span>
                            </div>
                            <div class="card-body">
                                @if(count($debtSettlements ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Receipt</th>
                                                    <th>Guest</th>
                                                    <th>Reservation</th>
                                                    <th>Payment method</th>
                                                    <th class="text-end">Amount</th>
                                                    <th>Sales date (report)</th>
                                                    <th>Received</th>
                                                    <th>Confirmed by</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($debtSettlements as $row)
                                                    <tr>
                                                        <td class="small">{{ $row['receipt'] }}</td>
                                                        <td>{{ $row['guest'] }}</td>
                                                        <td class="small">{{ $row['reservation'] }}</td>
                                                        <td class="small">{{ $row['payment_method'] }}</td>
                                                        <td class="text-end">{{ $row['currency'] }} {{ number_format((float) $row['amount'], 2, '.', '') }}</td>
                                                        <td class="small">{{ $row['sales_date'] }}</td>
                                                        <td class="small text-muted">{{ $row['received_at'] }}</td>
                                                        <td class="small">{{ $row['confirmed_by'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No debt settlements in this period (use “Confirming outstanding balance” when recording a payment).</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="mb-0">Breakdown</h6>
                    <div class="btn-group btn-group-sm fo-report-no-print">
                        <button type="button" class="btn {{ $group_by === 'room_type' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('group_by', 'room_type')">
                            By room type
                        </button>
                        <button type="button" class="btn {{ $group_by === 'room' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('group_by', 'room')">
                            By room
                        </button>
                    </div>
                </div>

                @if($group_by === 'room_type')
                    <div class="card mb-3">
                        <div class="card-body">
                            @if(count($byRoomType) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Room type</th>
                                                <th class="text-end">Revenue</th>
                                                <th class="text-end">Avg. rate / night</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($byRoomType as $row)
                                                <tr>
                                                    <td>{{ $row['room_type'] }}</td>
                                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['revenue']) }}</td>
                                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($foAvg((float) $row['revenue'], $row['nights'] ?? 0)) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small mb-0">No reservations in this period.</p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="card mb-3">
                        <div class="card-body">
                            @if(count($byRoom) > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Room / unit</th>
                                                <th>Type</th>
                                                <th class="text-end">Revenue</th>
                                                <th class="text-end">Avg. rate / night</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($byRoom as $row)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $row['room_label'] }}</strong>
                                                        @if(!empty($row['room_name']))
                                                            <span class="text-muted small">({{ $row['room_name'] }})</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $row['room_type'] }}</td>
                                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['revenue']) }}</td>
                                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($foAvg((float) $row['revenue'], $row['nights'] ?? 0)) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small mb-0">No reservations in this period.</p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Reservation list</h6>
                        @if(count($reservations) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Reservation</th>
                                            <th>Guest</th>
                                            <th>Room type</th>
                                            <th>Res. type</th>
                                            <th>Rate</th>
                                            <th>Business</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($reservations as $r)
                                            <tr>
                                                <td>{{ $r['number'] }}</td>
                                                <td>{{ $r['guest'] }}</td>
                                                <td>{{ $r['room_type'] }}</td>
                                                <td class="small">{{ $r['reservation_type'] ?? '—' }}</td>
                                                <td class="small">{{ $r['rate_plan'] ?? '—' }}</td>
                                                <td class="small">{{ $r['business_source'] ?? '—' }}</td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['amount']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No reservations in this period.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
