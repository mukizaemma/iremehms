<div class="container-fluid py-4">
    <div class="mb-3">
        <h5 class="mb-2 fw-bold">Proforma invoices</h5>
        @include('livewire.front-office.partials.front-office-quick-nav')
        <div class="d-flex flex-wrap gap-2 mt-2">
            <a href="{{ route('front-office.proforma-line-defaults') }}" class="btn btn-outline-secondary btn-sm">Default prices</a>
            <a href="{{ route('front-office.proforma-invoices.create') }}" class="btn btn-primary btn-sm">
                <i class="fa fa-plus me-1"></i> New proforma
            </a>
        </div>
    </div>


    @if(($canVerify ?? false) && ($pendingApprovalCount ?? 0) > 0)
        <div class="alert alert-warning py-2 px-3 small d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <strong>{{ $pendingApprovalCount }}</strong> proforma(s) pending your approval.
                @if(($newApprovalAlerts ?? 0) > 0)
                    <span class="badge bg-danger ms-1">{{ $newApprovalAlerts }} new</span>
                @endif
            </div>
            <button type="button" class="btn btn-outline-warning btn-sm" wire:click="$set('statusFilter', 'pending_manager')">Show pending</button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-auto">
                    <label class="form-label small mb-0">Status</label>
                    <select class="form-select form-select-sm" wire:model.live="statusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="pending_manager">Pending manager</option>
                        <option value="rejected">Rejected</option>
                        <option value="verified">Verified</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="invoiced">Invoiced</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th>
                            <th>Client / org</th>
                            <th>Event</th>
                            <th>Dates</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($proformas as $p)
                            <tr>
                                <td><code>{{ $p->proforma_number }}</code></td>
                                <td>
                                    <div class="fw-medium">{{ $p->client_name }}</div>
                                    @if($p->client_organization)
                                        <div class="small text-muted">{{ $p->client_organization }}</div>
                                    @endif
                                </td>
                                <td>{{ $p->event_title ?: '—' }}</td>
                                <td class="small">
                                    @if($p->service_start_date && $p->service_end_date)
                                        {{ \Carbon\Carbon::parse($p->service_start_date)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($p->service_end_date)->format('M j, Y') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format((float) $p->grand_total, 2) }} {{ $p->currency }}</td>
                                <td><span class="badge bg-secondary text-capitalize">{{ $p->status }}</span></td>
                                <td class="text-end">
                                    @if(($canVerify ?? false) && ($p->status ?? '') === 'pending_manager'
                                        )
                                        <a href="{{ route('front-office.proforma-invoices.edit', $p) }}" class="btn btn-warning btn-sm">Approve</a>
                                    @else
                                        <a href="{{ route('front-office.proforma-invoices.edit', $p) }}" class="btn btn-outline-primary btn-sm">View</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">No proforma invoices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $proformas->links() }}</div>
        </div>
    </div>
</div>
