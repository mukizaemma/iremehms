<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Waiters</h5>
        <div>
            <input type="text" class="form-control" placeholder="Search by name or email..." wire:model.live.debounce.300ms="search" style="max-width: 280px;">
        </div>
    </div>
    <p class="text-muted small mb-3">Active <strong>waiters for this hotel</strong> only (same hotel as your current session). They place orders in POS; orders are posted to preparation/posting stations.</p>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($waiters as $waiter)
                            <tr>
                                <td>{{ $waiter->name }}</td>
                                <td>{{ $waiter->email }}</td>
                                <td><span class="badge bg-secondary">{{ $waiter->role->name ?? 'Waiter' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No active waiters found. Assign the Waiter role to users in <a href="{{ route('users.index') }}">Users</a> (Manager or Super Admin).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
