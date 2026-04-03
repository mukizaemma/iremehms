<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Permission requests</h5>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
    </div>
    <p class="text-muted small">When a Manager adds a permission to a user, it appears here. Only Super Admin can approve or reject. Approved permissions are then granted to the user.</p>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            @if($this->pendingRequests->isEmpty())
                <p class="text-muted mb-0">No pending permission requests.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Permission</th>
                                <th>Requested by</th>
                                <th>Requested at</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->pendingRequests as $req)
                                <tr>
                                    <td>{{ $req->user->name ?? '—' }} <span class="text-muted">({{ $req->user->email ?? '' }})</span></td>
                                    <td><span class="badge bg-secondary">{{ $req->permission->name ?? $req->permission->slug ?? '—' }}</span></td>
                                    <td>{{ $req->requestedBy->name ?? '—' }}</td>
                                    <td>{{ $req->created_at->format('M j, Y H:i') }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $req->id }})">Approve</button>
                                        <button type="button" class="btn btn-sm btn-danger" wire:click="openRejectModal({{ $req->id }})">Reject</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Reject modal --}}
    @if($rejectRequestId)
    <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject permission request</h5>
                    <button type="button" class="btn-close" wire:click="cancelReject" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason (optional)</label>
                    <textarea class="form-control" wire:model="rejectionReason" rows="3" placeholder="e.g. This permission is not needed for this role."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="cancelReject">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmReject">Reject</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
