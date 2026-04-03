<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-0">Receipt modification requests</h5>
                    <p class="text-muted small mb-0">Approve or reject requests from order creators to modify confirmed receipts (paid, credit, room, or hotel covered).</p>
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

                <div class="card">
                    <div class="card-body p-0">
                        @if(count($pendingRequests) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Requested by</th>
                                            <th>Reason</th>
                                            <th>Requested at</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingRequests as $req)
                                            <tr>
                                                <td>
                                                    {{ $req->invoice->invoice_number ?? '—' }}
                                                    <br><small class="text-muted">Order #{{ $req->invoice->order_id ?? '—' }}</small>
                                                </td>
                                                <td>{{ $req->requestedBy->name ?? '—' }}</td>
                                                <td>{{ $req->reason }}</td>
                                                <td>{{ $req->created_at?->format('d M Y H:i') }}</td>
                                                <td>
                                                    <a href="{{ route('pos.payment', ['invoice' => $req->invoice_id]) }}" class="btn btn-outline-secondary btn-sm me-1" target="_blank">View receipt</a>
                                                    <button type="button" class="btn btn-success btn-sm" wire:click="approveRequest({{ $req->id }})">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-sm" wire:click="rejectRequest({{ $req->id }})" wire:confirm="Reject this modification request?">Reject</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted text-center py-4 mb-0">No pending receipt modification requests.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
