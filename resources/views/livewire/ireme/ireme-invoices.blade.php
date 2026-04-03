<div>
    <h5 class="mb-4">Invoices & Payments</h5>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted">Filter:</span>
        <select class="form-select form-select-sm" style="width: auto;" wire:model.live="filter_hotel_id">
            <option value="">All hotels</option>
            @foreach($hotels as $h)
                <option value="{{ $h->id }}">{{ $h->name }} (#{{ $h->hotel_code }})</option>
            @endforeach
        </select>
        <select class="form-select form-select-sm" style="width: auto;" wire:model.live="filter_status">
            <option value="">All statuses</option>
            <option value="sent">Sent</option>
            <option value="overdue">Overdue</option>
            <option value="paid">Paid</option>
            <option value="draft">Draft</option>
        </select>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">Generated / recent invoices</div>
                <div class="card-body p-0">
                    @if($generatedInvoices->isEmpty())
                        <p class="text-muted p-4 mb-0">No subscription invoices yet. Generate from <a href="{{ route('ireme.subscriptions.index') }}">Subscriptions</a> (per hotel).</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hotel</th>
                                        <th>Invoice</th>
                                        <th>Invoice date</th>
                                        <th>Due date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($generatedInvoices as $inv)
                                        <tr>
                                            <td>
                                                <a href="{{ route('ireme.subscriptions.show', $inv->hotel) }}" class="text-primary text-decoration-none">{{ $inv->hotel->name ?? '—' }}</a>
                                                @if($inv->hotel->hotel_code)
                                                    <br><small class="text-muted">#{{ $inv->hotel->hotel_code }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $inv->invoice_number }}</td>
                                            <td>{{ ($inv->invoice_date ?? $inv->created_at)?->format('d M Y') }}</td>
                                            <td>{{ $inv->due_date->format('d M Y') }}</td>
                                            <td>{{ number_format((float) $inv->amount, 2) }} {{ $inv->hotel->currency ?? 'RWF' }}</td>
                                            <td>
                                                @if($inv->status === 'paid')
                                                    <span class="badge bg-success">Paid</span>
                                                @elseif($inv->status === 'overdue')
                                                    <span class="badge bg-danger">Overdue</span>
                                                @elseif($inv->status === 'sent')
                                                    <span class="badge bg-warning text-dark">Sent</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $inv->status }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('ireme.subscription-invoice.show', $inv) }}" class="btn btn-sm btn-outline-secondary me-1" target="_blank">View / Print</a>
                                                @if($inv->status !== 'paid')
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="confirmPayment({{ $inv->id }})" wire:confirm="Mark this invoice as paid?">Confirm payment</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top">
                            {{ $generatedInvoices->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">Upcoming invoices (due later, not paid)</div>
                <div class="card-body p-0">
                    @if($upcomingInvoices->isEmpty())
                        <p class="text-muted p-4 mb-0">No upcoming invoices. Invoices are generated automatically when a hotel's next due date is within 30 days (see <a href="{{ route('ireme.subscriptions.index') }}">Subscriptions</a>).</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hotel</th>
                                        <th>Invoice</th>
                                        <th>Due date</th>
                                        <th>Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingInvoices as $inv)
                                        <tr>
                                            <td>
                                                <a href="{{ route('ireme.subscriptions.show', $inv->hotel) }}" class="text-primary text-decoration-none">{{ $inv->hotel->name ?? '—' }}</a>
                                                @if($inv->hotel->hotel_code)
                                                    <br><small class="text-muted">#{{ $inv->hotel->hotel_code }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $inv->invoice_number }}</td>
                                            <td>{{ $inv->due_date->format('d M Y') }}</td>
                                            <td>{{ number_format((float) $inv->amount, 2) }} {{ $inv->hotel->currency ?? 'RWF' }}</td>
                                            <td>
                                                <a href="{{ route('ireme.subscription-invoice.show', $inv) }}" class="btn btn-sm btn-outline-secondary" target="_blank">View / Print</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
