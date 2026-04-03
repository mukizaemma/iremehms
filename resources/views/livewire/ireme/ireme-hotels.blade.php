<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Hotels</h5>
        @if(Auth::user()->hasPermission('ireme_onboard_hotels'))
            <a href="{{ route('ireme.hotels.create') }}" class="btn btn-primary">Onboard Hotel</a>
        @endif
    </div>
    <div class="mb-3">
        <input type="text" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Search by name, code, email..." wire:model.live.debounce.200ms="search">
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Contact / Email</th>
                        <th>Subscription</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hotels as $h)
                        <tr>
                            <td><strong>{{ $h->hotel_code ?? '—' }}</strong></td>
                            <td>{{ $h->name }}</td>
                            <td>{{ $h->contact ?? $h->email ?? '—' }}</td>
                            <td>{{ $h->subscription_type ?? '—' }}</td>
                            <td><span class="badge {{ ($h->subscription_status ?? 'active') === 'active' ? 'bg-success' : 'bg-warning' }}">{{ $h->subscription_status ?? 'active' }}</span></td>
                            <td>
                                @if(Auth::user()->hasPermission('ireme_onboard_hotels'))
                                    <a href="{{ route('ireme.hotels.edit', $h) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @if(Auth::user()->hasPermission('ireme_manage_hotel_users'))
                                    <a href="{{ route('ireme.hotels.users', $h) }}" class="btn btn-sm btn-outline-secondary">Users</a>
                                @endif
                                @if(Auth::user()->isSuperAdmin())
                                    <a href="{{ route('ireme.hotels.rooms', $h) }}" class="btn btn-sm btn-outline-secondary" title="Rooms">Rooms</a>
                                    <a href="{{ route('ireme.hotels.menu-items', $h) }}" class="btn btn-sm btn-outline-secondary" title="Menu items">Menu</a>
                                    <a href="{{ route('ireme.hotels.additional-charges', $h) }}" class="btn btn-sm btn-outline-secondary" title="Additional charges">Charges</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No hotels yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $hotels->links() }}</div>
    </div>
</div>
