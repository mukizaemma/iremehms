<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4 mb-3">
                <h5 class="mb-1">Aging POS orders / backlog</h5>
                <p class="text-muted small mb-0">
                    Review open or unpaid orders from previous days so they can be followed up, written off, or regularized by a manager or accountant.
                </p>
                @include('livewire.pos.partials.pos-quick-links', ['active' => ''])
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Reference date (hotel day)</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Orders to show</label>
                            <select class="form-select form-select-sm" wire:model.live="statusFilter">
                                <option value="unpaid">Unpaid / open / confirmed</option>
                                <option value="all">All orders</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="onlyPreviousDays" wire:model.live="onlyPreviousDays">
                                <label class="form-check-label small" for="onlyPreviousDays">
                                    Show backlog from days <strong>before</strong> this date
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            @php $s = $snapshot; @endphp
                            <div class="border rounded p-2 small bg-light">
                                <div class="fw-semibold mb-1">Snapshot for {{ $date }}</div>
                                <div>Total orders: <strong>{{ $s['total_orders'] }}</strong></div>
                                <div>Unpaid: <strong>{{ $s['unpaid_orders'] }}</strong> (approx. {{ \App\Helpers\CurrencyHelper::format($s['unpaid_amount'] ?? 0) }})</div>
                                <div>Paid: <strong>{{ $s['paid_orders'] }}</strong> ({{ \App\Helpers\CurrencyHelper::format($s['paid_amount'] ?? 0) }})</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    @if($orders->count() === 0)
                        <p class="text-muted small mb-0">
                            No backlog orders match the current filters.
                            @if($onlyPreviousDays)
                                Change the reference date or disable "Show backlog from days before this date" to inspect a specific day.
                            @endif
                        </p>
                    @else
                        @php $user = Auth::user(); @endphp
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Table</th>
                                        <th>Waiter</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Invoice</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orders as $order)
                                        @php
                                            $inv = $order->invoice;
                                            $total = $inv
                                                ? (float) $inv->total_amount
                                                : (float) ($order->orderItems?->sum('line_total') ?? 0);
                                            $status = $order->order_status;
                                            $statusColor = $status === 'PAID'
                                                ? 'success'
                                                : ($status === 'OPEN'
                                                    ? 'warning'
                                                    : ($status === 'CONFIRMED' ? 'info' : 'secondary'));
                                            $paymentLabel = '—';
                                            $paymentBadge = 'secondary';
                                            if ($inv) {
                                                if ($inv->invoice_status === 'PAID') {
                                                    $paymentLabel = 'Paid';
                                                    $paymentBadge = 'success';
                                                } elseif ($inv->invoice_status === 'CREDIT') {
                                                    $paymentLabel = 'Credit / Pay later';
                                                    $paymentBadge = 'warning';
                                                } else {
                                                    $paymentLabel = $inv->invoice_status ?? 'Unpaid';
                                                    $paymentBadge = 'secondary';
                                                }
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ \App\Helpers\HotelTimeHelper::format($order->created_at, 'Y-m-d H:i') }}</td>
                                            <td>{{ $order->table->table_number ?? 'Takeaway' }}</td>
                                            <td>{{ $order->waiter->name ?? '—' }}</td>
                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($total) }}</td>
                                            <td><span class="badge bg-{{ $statusColor }}">{{ $status }}</span></td>
                                            <td>
                                                @if($inv)
                                                    <span class="badge bg-{{ $paymentBadge }}">{{ $paymentLabel }}</span>
                                                @else
                                                    <span class="badge bg-secondary">No invoice</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    @if($inv)
                                                        <a href="{{ route('pos.payment', ['invoice' => $inv->id]) }}"
                                                           class="btn btn-outline-primary"
                                                           title="Open payment / assignment screen"
                                                           target="_blank">
                                                            <i class="fa fa-file-invoice"></i>
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('pos.orders', ['order' => $order->id]) }}"
                                                       class="btn btn-outline-secondary"
                                                       title="Open order details"
                                                       target="_blank">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

