<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-0">POS & Sales</h5>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => ''])
                </div>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @php $hotel = \App\Models\Hotel::getHotel(); $strictShift = $hotel->isStrictShiftMode(); @endphp

                @if(!empty($usesOperationalShiftFlow) && !$openOperationalShift)
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>No POS operational shift is open.</strong> You need an open shift before selling.
                            With <strong>per-module</strong> shifts (default), POS can close while Front office or Store stay open — each module is independent unless management enables a <strong>global</strong> shift.
                            <a href="{{ route('shift.management') }}" class="alert-link ms-1">Open Shift management</a>
                        </div>
                @elseif(!empty($usesOperationalShiftFlow) && !$session)
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <h6 class="mb-3">You need an open POS session to sell</h6>
                                <p class="text-muted small mb-2">
                                    <strong>Operational shift:</strong>
                                    ref. {{ $openOperationalShift->reference_date ? \Carbon\Carbon::parse($openOperationalShift->reference_date)->format('d M Y') : '—' }}
                                    · opened {{ $openOperationalShift->opened_at ? \App\Helpers\HotelTimeHelper::format($openOperationalShift->opened_at, 'Y-m-d H:i') : '—' }}
                                </p>
                                <p class="text-muted small mb-4">A business day is not required when using operational shifts. Open your session to start selling.</p>
                                <button type="button" class="btn btn-primary btn-lg" wire:click="openSession">
                                    <i class="fa fa-play me-2"></i>Open POS Session
                                </button>
                            </div>
                        </div>
                @elseif(empty($usesOperationalShiftFlow) && !$openBusinessDay)
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        No business day is open. A manager must open a business day before you can use POS.
                    </div>
                @elseif($strictShift && !$openShiftOrLog)
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>No shift is currently open.</strong> The business day is open, but a shift must be open before you can use POS.
                        @if($canOpenShift)
                            @if($pendingDayShiftsNotEmpty)
                                <p class="mb-2 mt-2">You can open a shift here; your POS session will start automatically:</p>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($pendingDayShifts as $ds)
                                        <button type="button" class="btn btn-primary" wire:click="openShift({{ $ds->id }})">
                                            <i class="fa fa-play me-1"></i>Open {{ $ds->name }} and start POS
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <p class="mb-2 mt-2">You have permission to open a shift. There are no pending shifts for this business day—open one in Shift Management, then return here to start your POS session.</p>
                                <a href="{{ route('shift.management') }}" class="btn btn-primary">
                                    <i class="fa fa-external-link-alt me-1"></i>Open Shift Management
                                </a>
                            @endif
                        @else
                            <p class="mb-0 mt-2">
                                <a href="{{ route('shift.management') }}" class="alert-link">Open a shift in Shift Management</a> (e.g. open the first shift of the day), then return here to open your POS session.
                            </p>
                        @endif
                    </div>
                @elseif(!$session)
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <h6 class="mb-3">You need an open POS session to sell</h6>
                            <p class="text-muted small mb-2">
                                <strong>Current business day:</strong> {{ $openBusinessDay->business_date ? \Carbon\Carbon::parse($openBusinessDay->business_date)->format('d M Y') : 'N/A' }}
                                · Day runs until <strong>{{ $rolloverTimeFormatted ?? '03:00 AM' }}</strong> next morning
                            </p>
                            @if($openShiftOrLog)
                                <p class="text-muted small mb-2">Shift: {{ $openShiftOrLog->name ?? ($openShiftOrLog->shift->name ?? 'N/A') }}</p>
                            @endif
                            <p class="text-muted small mb-4">Open a session to start selling. Close your session when you finish.</p>
                            <button type="button" class="btn btn-primary btn-lg" wire:click="openSession">
                                <i class="fa fa-play me-2"></i>Open POS Session
                            </button>
                        </div>
                    </div>
                @else
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <span class="badge bg-success me-2">Session open</span>
                                    <span class="text-muted small">
                                        Started {{ \App\Helpers\HotelTimeHelper::format($session->opened_at, 'H:i') }}
                                        @if($session->operationalShift)
                                            · <strong>Operational shift</strong> ref. {{ $session->operationalShift->reference_date ? \Carbon\Carbon::parse($session->operationalShift->reference_date)->format('d M Y') : '—' }}
                                        @else
                                            · Business day: {{ $session->businessDay && $session->businessDay->business_date ? \Carbon\Carbon::parse($session->businessDay->business_date)->format('d M Y') : 'N/A' }}
                                            (until {{ $rolloverTimeFormatted ?? '03:00 AM' }} next day)
                                            @if($session->dayShift)
                                                · Shift: {{ $session->dayShift->name }}
                                            @elseif($session->shiftLog && $session->shiftLog->shift)
                                                · Shift: {{ $session->shiftLog->shift->name }}
                                            @endif
                                        @endif
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="{{ route('pos.orders') }}" class="btn btn-primary">
                                        <i class="fa fa-utensils me-2"></i>Orders
                                    </a>
                                    <a href="{{ route('pos.tables') }}" class="btn btn-outline-primary">Tables</a>
                                    <a href="{{ route('pos.my-sales') }}" class="btn btn-outline-secondary">My Sales</a>

                                    <div class="dropdown">
                                        <button class="btn btn-outline-info dropdown-toggle btn-sm" type="button" id="posStationDisplays" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fa fa-tv me-1"></i>Station displays
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="posStationDisplays">
                                            @foreach($activePreparationStations as $slug => $label)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('pos.station', ['station' => $slug]) }}" target="_blank" rel="noopener">
                                                        {{ $label }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" id="posMoreActions" data-bs-toggle="dropdown" aria-expanded="false">
                                            More
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="posMoreActions">
                                            <li>
                                                <h6 class="dropdown-header">Session actions</h6>
                                            </li>
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item text-warning"
                                                        wire:click="closeSession"
                                                        wire:confirm="Close your POS session? You will need to open a new one to sell again.">
                                                    <i class="fa fa-stop me-2"></i>Close session
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small mb-1">Today's sales</div>
                                        <h5 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($todayTotalSales) }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small mb-1">Active orders</div>
                                        <h5 class="mb-0">{{ $todayActiveOrders }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small mb-1">Not paid orders</div>
                                        <h5 class="mb-0">{{ $todayUnpaidOrders }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small mb-1">My sales today</div>
                                        <h5 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($todayMySales) }}</h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="POS report view">
                                        <button type="button" class="btn {{ $report_view === 'sales' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('report_view', 'sales')">
                                            Sales
                                        </button>
                                        <button type="button" class="btn {{ $report_view === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('report_view', 'active')">
                                            Active orders
                                        </button>
                                        <button type="button" class="btn {{ $report_view === 'unpaid' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('report_view', 'unpaid')">
                                            Not paid orders
                                        </button>
                                        <button type="button" class="btn {{ $report_view === 'my_sales' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('report_view', 'my_sales')">
                                            My sales
                                        </button>
                                    </div>
                                    <form class="d-flex align-items-center gap-2" wire:submit.prevent="applyReportDates">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">From</span>
                                            <input type="date" class="form-control" wire:model.defer="report_from">
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">To</span>
                                            <input type="date" class="form-control" wire:model.defer="report_to">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Apply
                                        </button>
                                    </form>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Order</th>
                                            <th>Table</th>
                                            <th>Waiter</th>
                                            <th class="text-end">Total</th>
                                            <th>Status</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($report_rows as $row)
                                            <tr>
                                                <td>{{ \App\Helpers\HotelTimeHelper::format($row['date'], 'Y-m-d H:i') }}</td>
                                                <td>#{{ $row['order_id'] }}</td>
                                                <td>{{ $row['table'] ?? '—' }}</td>
                                                <td>{{ $row['waiter'] ?? '—' }}</td>
                                                <td class="text-end">
                                                    {{ \App\Helpers\CurrencyHelper::format($row['total'] ?? 0) }}
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $row['status'] ?? '-' }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted small py-3">
                                                    No records found for the selected dates.
                                                </td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
