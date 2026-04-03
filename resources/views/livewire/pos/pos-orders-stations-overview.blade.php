<div class="container-fluid py-4">
    <div class="bg-light rounded p-4 mb-3">
        <h4 class="mb-1"><i class="fa fa-tv me-2"></i>Orders & station overview</h4>
        <p class="text-muted small mb-0">
            Tables with active orders, waiter, invoice status, and whether orders were sent or printed to preparation/posting stations.
        </p>
        @include('livewire.pos.partials.pos-quick-links', ['active' => ''])
    </div>

    {{-- Links to each station display --}}
    @if(count($this->stations) > 0)
        <div class="mb-4">
            <h6 class="text-muted mb-2">View by station</h6>
            <div class="d-flex flex-wrap gap-2">
                @foreach($this->stations as $slug => $label)
                    <a href="{{ route('pos.station', ['station' => $slug]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-tv me-1"></i>{{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if(count($this->tablesWithOrders) === 0)
        <div class="alert alert-info">No tables with active orders right now.</div>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Table</th>
                        <th>Order</th>
                        <th>Waiter</th>
                        <th>Order status</th>
                        <th>Invoice</th>
                        <th>Sent to station</th>
                        <th>Printed to station</th>
                        <th class="text-center" style="width: 4rem;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->tablesWithOrders as $row)
                        @foreach($row['orders'] as $index => $order)
                            <tr>
                                @if($index === 0)
                                    <td rowspan="{{ count($row['orders']) }}" class="fw-bold">{{ $row['table_number'] }}</td>
                                @endif
                                <td>
                                    <a href="{{ route('pos.orders', ['order' => $order['id']]) }}">#{{ $order['id'] }}</a>
                                    <br><span class="small text-muted">{{ $order['created_at'] }}</span>
                                    @if(!empty($order['items_summary']))
                                        <br><span class="small">{{ $order['items_summary'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $order['waiter_name'] }}</td>
                                <td><span class="badge bg-{{ $order['order_status'] === 'CONFIRMED' ? 'primary' : 'warning' }}">{{ $order['order_status'] }}</span></td>
                                <td>
                                    @if($order['invoice_status'])
                                        <span class="badge bg-secondary">{{ $order['invoice_status'] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $anySent = collect($order['items_by_station'])->contains(fn ($s) => $s['sent'] ?? false);
                                    @endphp
                                    @if($anySent)
                                        <span class="text-success"><i class="fa fa-check-circle"></i> Yes</span>
                                    @else
                                        <span class="text-muted">No</span>
                                    @endif
                                </td>
                                <td>
                                    @if($order['order_ticket_printed_at'])
                                        <span class="text-success"><i class="fa fa-print"></i> Yes</span>
                                    @else
                                        @php
                                            $anyPrinted = collect($order['items_by_station'])->contains(fn ($s) => $s['printed'] ?? false);
                                        @endphp
                                        @if($anyPrinted)
                                            <span class="text-success"><i class="fa fa-check"></i> Items</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="showOrderDetails({{ $order['id'] }})" title="View items by station">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Details modal --}}
        @if($detailOrderId)
            <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Order #{{ $detailOrderId }} — Items by station</h5>
                            <button type="button" class="btn-close" wire:click="closeOrderDetails" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if(count($detailItemsByStation) > 0)
                                @foreach($detailItemsByStation as $stationName => $data)
                                    <div class="mb-3">
                                        <strong class="text-secondary">{{ $stationName }}</strong>
                                        <ul class="list-unstyled mb-0 mt-1 ps-2">
                                            @foreach($data['items'] as $it)
                                                <li class="small py-1">
                                                    @if($it['voided'] ?? false)
                                                        <s>{{ $it['quantity'] }}× {{ $it['name'] }}</s>
                                                        <span class="text-danger ms-1">(voided)</span>
                                                    @else
                                                        {{ $it['quantity'] }}× {{ $it['name'] }}
                                                        @if($it['sent_to_station_at'])
                                                            <span class="text-success" title="Sent to station"><i class="fa fa-paper-plane ms-1"></i></span>
                                                        @endif
                                                        @if($it['printed_at'])
                                                            <span class="text-info" title="Printed"><i class="fa fa-print ms-1"></i></span>
                                                        @endif
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-muted mb-0">No items.</p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('pos.orders', ['order' => $detailOrderId]) }}" class="btn btn-primary btn-sm">Open order</a>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="closeOrderDetails">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
