<div>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ireme.hotels.index') }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fa fa-arrow-left"></i></a>
        <h5 class="mb-0">Users – {{ $hotel->name }} (#{{ $hotel->hotel_code }})</h5>
    </div>

    @if(Auth::user()->isSuperAdmin())
        @if(!$showForm)
            <button type="button" class="btn btn-primary mb-3" wire:click="$set('showForm', true)">Add User</button>
        @else
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">Add user</div>
                <div class="card-body">
                    <form wire:submit.prevent="addUser">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" wire:model="name">
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" wire:model="email">
                                @error('email') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" wire:model="password">
                                @error('password') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Role</label>
                                <select class="form-select" wire:model="role_id">
                                    <option value="">Select</option>
                                    @foreach($roles as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                                @error('role_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Add</button>
                                <button type="button" class="btn btn-secondary" wire:click="$set('showForm', false)">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Active</th>
                        @if(Auth::user()->isSuperAdmin())
                            <th>Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->role->name ?? '—' }}</td>
                            <td>{{ $u->is_active ? 'Yes' : 'No' }}</td>
                            @if(Auth::user()->isSuperAdmin())
                                <td>
                                    @php $isSuperAdminRole = $u->role && $u->role->slug === 'super-admin'; @endphp
                                    @if(!$isSuperAdminRole)
                                        <a href="{{ route('ireme.hotels.users.permissions', [$hotel, $u]) }}" class="btn btn-sm btn-outline-primary" title="Manage permissions">
                                            <i class="fa fa-key me-1"></i>Permissions
                                        </a>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ Auth::user()->isSuperAdmin() ? 5 : 4 }}" class="text-center text-muted">No users for this hotel.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
