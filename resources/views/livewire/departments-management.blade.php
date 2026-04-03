<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Departments Management</h5>
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

    <div class="row g-4">
        <!-- Departments List -->
        <div class="col-md-4">
            <div class="bg-light rounded p-4">
                <h6 class="mb-3">Departments</h6>
                @if(count($departments) === 0)
                    <p class="text-muted small mb-0">
                        No departments are linked to this hotel yet. Enable the relevant <strong>modules</strong> (and/or department list) for this hotel in subscription / hotel settings, then return here.
                    </p>
                @endif
                <div class="list-group">
                    @foreach($departments as $department)
                        <a href="#" 
                           class="list-group-item list-group-item-action {{ $selectedDepartment == $department->id ? 'active' : '' }}"
                           wire:click.prevent="selectDepartment({{ $department->id }})">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $department->name }}</h6>
                                <small class="badge bg-primary rounded-pill">{{ $department->users_count ?? 0 }}</small>
                            </div>
                            <small>{{ $department->description ?? 'No description' }}</small>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Department Users -->
        <div class="col-md-8">
            @if($selectedDepartment)
                @php
                    $selectedDept = $departments->firstWhere('id', $selectedDepartment);
                @endphp
                <div class="bg-light rounded p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Users in {{ $selectedDept->name ?? 'Department' }}</h6>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" placeholder="Search users..." wire:model.live="search" style="max-width: 200px;">
                            <button class="btn btn-primary btn-sm" wire:click="createUser">
                                <i class="fa fa-plus me-1"></i>Add User
                            </button>
                        </div>
                    </div>

                    @if($showUserForm)
                        <div class="bg-white rounded p-4 mb-4">
                            <h6 class="mb-3">{{ $editingUserId ? 'Edit User' : 'Add New User' }}</h6>
                            <form wire:submit.prevent="saveUser">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="name" wire:model="name" placeholder="Full Name">
                                            <label for="name">Full Name</label>
                                        </div>
                                        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="email" wire:model="email" placeholder="Email">
                                            <label for="email">Email</label>
                                        </div>
                                        @error('email') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="password" wire:model="password" placeholder="Password">
                                            <label for="password">Password {{ $editingUserId ? '(leave blank to keep current)' : '' }}</label>
                                        </div>
                                        @error('password') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    @if(!$editingUserId || $password)
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="password_confirmation" wire:model="password_confirmation" placeholder="Confirm Password">
                                                <label for="password_confirmation">Confirm Password</label>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="role_id" wire:model="role_id">
                                                <option value="">-- Select Role --</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                            <label for="role_id">Role</label>
                                        </div>
                                        @error('role_id') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" id="is_active" wire:model="is_active">
                                            <label class="form-check-label" for="is_active">Active</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_verified" wire:model="email_verified">
                                            <label class="form-check-label" for="email_verified">Email Verified</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">Save</button>
                                            <button type="button" class="btn btn-secondary" wire:click="resetUserForm">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Email Verified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($departmentUsers as $user)
                                    <tr>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->role->name ?? 'No Role' }}</td>
                                        <td>
                                            @if($user->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($user->email_verified)
                                                <span class="badge bg-success">Verified</span>
                                            @else
                                                <span class="badge bg-warning">Unverified</span>
                                                <button class="btn btn-sm btn-outline-primary" wire:click="verifyEmail({{ $user->id }})">
                                                    Verify
                                                </button>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-primary" wire:click="editUser({{ $user->id }})">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                @if($user->id != Auth::id())
                                                    <button class="btn btn-danger" wire:click="deleteUser({{ $user->id }})" 
                                                        onclick="return confirm('Are you sure you want to delete this user?')">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No users found in this department</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="bg-light rounded p-4 text-center">
                    <p class="text-muted">Select a department to view and manage its users</p>
                </div>
            @endif
        </div>
    </div>
</div>
