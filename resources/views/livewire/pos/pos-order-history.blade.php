<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-0">Order history</h5>
                    <p class="text-muted small mb-0">Search orders by date and status.</p>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => 'invoices'])
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

                <div class="card mb-4">
                    <div class="card-body">
                        <form wire:submit.prevent="applySearch" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small">From date</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_from">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">To date</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_to">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Status</label>
                                <select class="form-select form-select-sm" wire:model.defer="filter_status">
                                    <option value="">All</option>
                                    <option value="OPEN">Open</option>
                                    <option value="CONFIRMED">Confirmed</option>
                                    <option value="PAID">Paid</option>
                                    <option value="CREDIT">Credit</option>
                                    <option value="CANCELLED">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Order</th>
                                <th>Table</th>
                                <th class="text-end">Total</th>
                                <th>Waiter</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                @php
                                    $canOpen = in_array($o->order_status, ['OPEN', 'CONFIRMED', 'PAID', 'CREDIT']);
                                    $orderUrl = $canOpen ? route('pos.orders', ['order' => $o->id]) : null;
                                    $totalAmount = isset($o->invoice)
                                        ? ($o->invoice->total_amount ?? 0)
                                        : ($o->total ?? 0);
                                    $inv = $o->invoice;
                                    $paymentLabel = '—';
                                    $paymentBadge = 'secondary';
                                    if ($inv) {
                                        if (($inv->invoice_status ?? null) === 'PAID') {
                                            $paymentLabel = 'Paid';
                                            $paymentBadge = 'success';
                                        } elseif (($inv->charge_type ?? null) === \App\Models\Invoice::CHARGE_TYPE_ROOM) {
                                            $paymentLabel = 'Assigned to room';
                                            $paymentBadge = 'info';
                                        } elseif (($inv->charge_type ?? null) === \App\Models\Invoice::CHARGE_TYPE_HOTEL_COVERED) {
                                            $paymentLabel = 'Hotel covered';
                                            $paymentBadge = 'secondary';
                                        } elseif (($inv->invoice_status ?? null) === 'CREDIT') {
                                            $paymentLabel = 'Credit';
                                            $paymentBadge = 'warning';
                                        } else {
                                            $paymentLabel = $inv->invoice_status ?? '—';
                                        }
                                    }
                                @endphp
                                <tr @if($canOpen) onclick="window.location='{{ $orderUrl }}'" style="cursor: pointer;" @endif>
                                    <td>{{ \App\Helpers\HotelTimeHelper::format($o->created_at, 'Y-m-d H:i') }}</td>
                                    <td>#{{ $o->id }}</td>
                                    <td>{{ $o->table->table_number ?? '—' }}</td>
                                    <td class="text-end">
                                        {{ $totalAmount > 0 ? \App\Helpers\CurrencyHelper::format($totalAmount) : '—' }}
                                    </td>
                                    <td>{{ $o->waiter->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $o->order_status === 'PAID' ? 'success' : ($o->order_status === 'OPEN' ? 'warning' : ($o->order_status === 'CREDIT' ? 'secondary' : 'info')) }}">
                                            {{ $o->order_status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($inv)
                                            <span class="badge bg-{{ $paymentBadge }}">{{ $paymentLabel }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($inv)
                                            <a href="{{ route('pos.payment', ['invoice' => $inv->id]) }}" class="btn btn-sm btn-outline-secondary" title="View invoice / payment details" wire:navigate onclick="event.stopPropagation();">View</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-muted text-center py-4">No orders found for the selected dates.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-3">
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
