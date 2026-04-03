<div>
    @if(!$hotel)
        <div class="alert alert-warning">Hotel not found.</div>
        @return
    @endif

    <h5 class="mb-4">Subscription & Billing</h5>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-2">Amount per period</h6>
                    <p class="mb-0 fs-5 fw-bold">{{ $hotel->subscription_amount !== null ? \App\Helpers\CurrencyHelper::format((float) $hotel->subscription_amount) : '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-2">Start date</h6>
                    <p class="mb-0">{{ $hotel->subscription_start_date?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase mb-2">Next due date</h6>
                    <p class="mb-0">{{ $hotel->next_due_date?->format('d M Y') ?? '—' }}</p>
                    <small class="text-muted">Invoices generated up to 30 days before due; reminders at 7 days and 24 hours.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-warning border-0 shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="text-muted small text-uppercase mb-1">Not paid (outstanding)</h6>
                        <p class="mb-0 fs-4 fw-bold text-danger">{{ \App\Helpers\CurrencyHelper::format($notPaidAmount) }}</p>
                    </div>
                    <button type="button" class="btn btn-primary" wire:click="openSupportRequest">
                        <i class="fa fa-question-circle me-1"></i>Send support request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">Recent invoices</div>
                <div class="card-body p-0">
                    @if($recentInvoices->isEmpty())
                        <p class="text-muted p-3 mb-0">No invoices yet.</p>
                    @else
                        <div class="table-responsive">
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
                                    @foreach($recentInvoices as $inv)
                                        <tr>
                                            <td>{{ $inv->invoice_number }}</td>
                                            <td>{{ $inv->due_date->format('d M Y') }}</td>
                                            <td>{{ \App\Helpers\CurrencyHelper::format((float) $inv->amount) }}</td>
                                            <td>
                                                @if($inv->status === 'paid')
                                                    <span class="badge bg-success">Paid</span>
                                                @elseif($inv->status === 'overdue')
                                                    <span class="badge bg-danger">Overdue</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Sent</span>
                                                @endif
                                            </td>
                                            <td><a href="{{ route('subscription.invoice.show', $inv) }}" class="btn btn-sm btn-outline-secondary" target="_blank">View / Print</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">Upcoming invoices</div>
                <div class="card-body p-0">
                    @if($upcomingInvoices->isEmpty())
                        <p class="text-muted p-3 mb-0">No upcoming invoices.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Due date</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingInvoices as $inv)
                                        <tr>
                                            <td>{{ $inv->invoice_number }}</td>
                                            <td>{{ $inv->due_date->format('d M Y') }}</td>
                                            <td>{{ \App\Helpers\CurrencyHelper::format((float) $inv->amount) }}</td>
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

    @if($recentRequests->isNotEmpty())
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-light">Your recent support requests</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($recentRequests as $req)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ $req->subject }}</strong>
                                    <br><small class="text-muted">{{ $req->created_at->format('d M Y H:i') }} · {{ ucfirst($req->status) }}</small>
                                </div>
                                <div>
                                    <span class="badge bg-{{ $req->status === 'resolved' ? 'success' : ($req->status === 'in_progress' ? 'info' : 'secondary') }} me-2">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="expandRequest({{ $req->id }})">{{ $expandedRequest && $expandedRequest->id === $req->id ? 'Hide' : 'View' }}</button>
                                </div>
                            </div>
                            @if($expandedRequest && $expandedRequest->id === $req->id)
                                <div class="mt-3 pt-3 border-top">
                                    <p class="mb-2"><strong>Your message:</strong></p>
                                    <p class="text-muted small mb-3">{{ $req->message }}</p>
                                    @if($expandedRequest->responses->isNotEmpty())
                                        <p class="mb-2"><strong>Replies from Ireme:</strong></p>
                                        @foreach($expandedRequest->responses as $resp)
                                            <div class="bg-light rounded p-2 mb-2">
                                                <p class="mb-0 small">{{ $resp->message }}</p>
                                                <small class="text-muted">{{ $resp->user->name ?? 'Ireme' }} · {{ $resp->created_at->format('d M Y H:i') }}</small>
                                            </div>
                                        @endforeach
                                    @else
                                        <p class="text-muted small mb-0">No replies yet.</p>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($showSupportModal)
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Send support request to Ireme</h5>
                        <button type="button" class="btn-close" wire:click="closeSupportModal"></button>
                    </div>
                    <form wire:submit.prevent="submitSupportRequest">
                        <div class="modal-body">
                            <p class="text-muted small">Request help with the system or payment. Ireme will respond via this page.</p>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" wire:model="support_subject" placeholder="e.g. Payment question, Feature request">
                                @error('support_subject') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" wire:model="support_message" rows="4" placeholder="Describe your request..."></textarea>
                                @error('support_message') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeSupportModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
