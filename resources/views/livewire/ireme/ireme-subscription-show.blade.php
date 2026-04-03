<div>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ireme.subscriptions.index') }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fa fa-arrow-left"></i></a>
        <div>
            <h5 class="mb-1">{{ $hotel->name }}</h5>
            <p class="text-muted small mb-0">#{{ $hotel->hotel_code ?? '—' }} · {{ $hotel->email ?? 'No email' }}</p>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="button" class="btn btn-primary" wire:click="generateInvoice">
            <i class="fa fa-file-invoice me-1"></i>Generate invoice
        </button>
        <a href="{{ route('ireme.hotels.edit', $hotel) }}" class="btn btn-outline-primary">
            <i class="fa fa-edit me-1"></i>Update subscription
        </a>
        <a href="{{ route('ireme.requests.index', ['hotel_id' => $hotel->id]) }}" class="btn btn-outline-primary">
            <i class="fa fa-inbox me-1"></i>View requests
            @if($requestsCount > 0)
                <span class="badge bg-primary ms-1">{{ $requestsCount }}</span>
            @endif
        </a>
        <button type="button" class="btn btn-outline-primary" wire:click="openNotifyModal">
            <i class="fa fa-bell me-1"></i>Send notification
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-1">Start date</h6>
                    <p class="mb-0">{{ $hotel->subscription_start_date ? $hotel->subscription_start_date->format('d M Y') : '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-1">Next due date</h6>
                    <p class="mb-0">{{ $hotel->next_due_date ? $hotel->next_due_date->format('d M Y') : '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-1">Amount</h6>
                    <p class="mb-0">{{ $hotel->subscription_amount !== null ? number_format((float) $hotel->subscription_amount, 2) . ' ' . ($hotel->currency ?? 'RWF') : '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-1">Remaining / Past due</h6>
                    <p class="mb-0">{{ $daysText }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span>Confirm payment</span>
        </div>
        <div class="card-body">
            @if($unpaidInvoices->isEmpty())
                <p class="text-muted mb-0">No unpaid invoices. Use "Generate invoice" when the next due date is within 15 days.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Due date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ $inv->due_date->format('d M Y') }}</td>
                                    <td>{{ number_format((float) $inv->amount, 2) }} {{ $hotel->currency ?? 'RWF' }}</td>
                                    <td><span class="badge {{ $inv->status === 'overdue' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $inv->status }}</span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" wire:click="confirmPayment({{ $inv->id }})" wire:confirm="Mark this invoice as paid?">Confirm payment</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">Recent subscription invoices</div>
        <div class="card-body p-0">
            @if($hotel->subscriptionInvoices->isEmpty())
                <p class="text-muted p-3 mb-0">No invoices yet.</p>
            @else
                <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Due date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($hotel->subscriptionInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ $inv->due_date->format('d M Y') }}</td>
                                    <td>{{ number_format((float) $inv->amount, 2) }} {{ $hotel->currency ?? 'RWF' }}</td>
                                    <td><span class="badge {{ $inv->status === 'paid' ? 'bg-success' : ($inv->status === 'overdue' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $inv->status }}</span></td>
                                    <td><a href="{{ route('ireme.subscription-invoice.show', $inv) }}" class="btn btn-sm btn-outline-secondary" target="_blank">View / Print</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($showNotifyModal)
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Send notification to hotel</h5>
                        <button type="button" class="btn-close" wire:click="closeNotifyModal"></button>
                    </div>
                    <form wire:submit.prevent="sendNotification">
                        <div class="modal-body">
                            <p class="text-muted small">Sends an email to {{ $hotel->email ?? 'hotel email' }}.</p>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" wire:model="notify_subject" placeholder="Subject">
                                @error('notify_subject') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" wire:model="notify_message" rows="4" placeholder="Message body..."></textarea>
                                @error('notify_message') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeNotifyModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
