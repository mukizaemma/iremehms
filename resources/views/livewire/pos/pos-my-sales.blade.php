<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-0">My Sales</h5>
                    <p class="text-muted small mb-0">This session · Shift: {{ $shiftName }}</p>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => 'my-sales'])
                </div>

                @php
                    $today = now()->toDateString();
                    $orderHistoryUrl = url('/pos/order-history');
                    $orderHistoryToday = $orderHistoryUrl . '?date_from=' . $today . '&date_to=' . $today;
                @endphp
                <style>.pos-stat-card:hover { box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.12) !important; }</style>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ $orderHistoryToday }}" class="text-decoration-none text-dark d-block pos-stat-card" wire:navigate>
                            <div class="card h-100 border shadow-sm">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 text-muted small">Total orders</h6>
                                    <h4 class="mb-0">{{ $todayTotalOrders }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ $orderHistoryToday }}&status=PAID" class="text-decoration-none text-dark d-block pos-stat-card" wire:navigate>
                            <div class="card h-100 border shadow-sm">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 text-muted small">Paid orders</h6>
                                    <h4 class="mb-0">{{ $paidOrdersCount }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="#unpaid-orders" class="text-decoration-none text-dark d-block pos-stat-card">
                            <div class="card h-100 border shadow-sm border-warning">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 text-muted small">Not paid orders</h6>
                                    <h4 class="mb-0">{{ $unpaidOrdersCount }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="#paid-orders" class="text-decoration-none text-dark d-block pos-stat-card">
                            <div class="card h-100 border shadow-sm bg-primary text-white">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 opacity-75 small">Total collected</h6>
                                    <h4 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($totalCollected) }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="#paid-orders" class="text-decoration-none text-dark d-block pos-stat-card">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 text-muted small">Paid amount</h6>
                                    <h4 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($paidAmount) }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="#unpaid-orders" class="text-decoration-none text-dark d-block pos-stat-card">
                            <div class="card h-100 border shadow-sm border-warning">
                                <div class="card-body py-3 text-center">
                                    <h6 class="card-subtitle mb-1 text-muted small">Not paid amount</h6>
                                    <h4 class="mb-0">{{ \App\Helpers\CurrencyHelper::format($notPaidAmount) }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                @if(count($unpaidOrders) > 0)
                    <div class="card mb-4 border-warning" id="unpaid-orders">
                        <div class="card-header bg-light">Orders not yet paid (today, this session)</div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Click an order to add items or receive payment. Only today's orders are shown. <a href="{{ url('/pos/order-history') }}">Search order history</a> for older orders.</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Table</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($unpaidOrders as $o)
                                            @php $orderUrl = route('pos.orders', ['order' => $o['id']]); @endphp
                                            <tr role="button" tabindex="0" class="align-middle" style="cursor: pointer;" onclick="window.location.href='{{ $orderUrl }}'" data-url="{{ $orderUrl }}">
                                                <td><a href="{{ $orderUrl }}" class="text-decoration-none text-dark fw-bold" wire:navigate>#{{ $o['id'] }}</a></td>
                                                <td>{{ $o['table']['table_number'] ?? '—' }}</td>
                                                <td><span class="badge bg-{{ $o['order_status'] === 'OPEN' ? 'warning' : 'info' }}">{{ $o['order_status'] }}</span></td>
                                                <td>{{ isset($o['invoice']) ? \App\Helpers\CurrencyHelper::format($o['invoice']['total_amount'] ?? 0) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card mb-4" id="paid-orders">
                    <div class="card-header">Paid / settled orders (today, this session)</div>
                    <div class="card-body">
                        @if(count($orders) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Table</th>
                                            <th>Total</th>
                                            <th>Payment status</th>
                                            <th>Time</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($orders as $o)
                                            @php
                                                $inv = $o['invoice'] ?? null;
                                                $paymentLabel = '—';
                                                $paymentBadge = 'secondary';
                                                if ($inv) {
                                                    if (($inv['invoice_status'] ?? null) === 'PAID') {
                                                        $paymentLabel = 'Paid';
                                                        $paymentBadge = 'success';
                                                    } elseif (($inv['charge_type'] ?? null) === \App\Models\Invoice::CHARGE_TYPE_ROOM) {
                                                        $paymentLabel = 'Assigned to room';
                                                        $paymentBadge = 'info';
                                                    } elseif (($inv['charge_type'] ?? null) === \App\Models\Invoice::CHARGE_TYPE_HOTEL_COVERED) {
                                                        $paymentLabel = 'Hotel covered';
                                                        $paymentBadge = 'secondary';
                                                    } elseif (($inv['invoice_status'] ?? null) === 'CREDIT') {
                                                        $paymentLabel = 'Credit';
                                                        $paymentBadge = 'warning';
                                                    } else {
                                                        $paymentLabel = $inv['invoice_status'] ?? '—';
                                                    }
                                                }
                                            @endphp
                                            <tr>
                                                <td>#{{ $o['id'] }}</td>
                                                <td>{{ $o['table']['table_number'] ?? '—' }}</td>
                                                <td>{{ \App\Helpers\CurrencyHelper::format($inv['total_amount'] ?? 0) }}</td>
                                                <td><span class="badge bg-{{ $paymentBadge }}">{{ $paymentLabel }}</span></td>
                                                <td>{{ isset($o['updated_at']) ? \App\Helpers\HotelTimeHelper::format($o['updated_at'], 'H:i') : '—' }}</td>
                                                <td>
                                                    @if(!empty($inv['id']))
                                                        <a href="{{ route('pos.payment', ['invoice' => $inv['id']]) }}" class="btn btn-sm btn-outline-secondary" title="View invoice / payment details" wire:navigate>View</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No paid or settled orders today yet.</p>
                        @endif
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <a href="{{ url('/pos/order-history') }}" class="btn btn-outline-secondary btn-sm">Search order history</a>
                </div>
            </div>
        </div>
    </div>
</div>
