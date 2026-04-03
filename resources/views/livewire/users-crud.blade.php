<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Users Management</h5>
        <div class="d-flex gap-2">
            <input type="text" class="form-control" placeholder="Search users..." wire:model.live="search" style="max-width: 300px;">
            <button class="btn btn-primary" wire:click="create">
                <i class="fa fa-plus me-2"></i>Add User
            </button>
        </div>
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

    @if($showForm)
        <div class="bg-white rounded p-4 mb-4">
            <h6 class="mb-3">{{ $editingUserId ? 'Edit User' : 'Create New User' }}</h6>
            <form wire:submit.prevent="save">
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
                    <div class="col-12">
                        <label class="form-label">Departments</label>
                        <p class="text-muted small mb-2">Optional. Assign one or more departments for accountability and recovery.</p>
                        @if(count($departments) > 0)
                            <div class="row g-2">
                                @foreach($departments as $dept)
                                    <div class="col-md-4 col-lg-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="dept_{{ $dept->id }}" value="{{ $dept->id }}" wire:model="selectedDepartments">
                                            <label class="form-check-label" for="dept_{{ $dept->id }}">{{ $dept->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="small text-muted mb-0">No departments defined. Add departments in <a href="{{ route('departments.index') }}">Departments</a>.</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" wire:model="is_active">
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="email_verified" wire:model="email_verified">
                            <label class="form-check-label" for="email_verified">Email Verified</label>
                        </div>
                    </div>
                    
                    @php
                        $selectedRole = $roles->firstWhere('id', $role_id);
                        $isSuperAdminRole = $selectedRole && $selectedRole->slug === 'super-admin';
                    @endphp
                    
                    @php
                        $canSetPermissions = Auth::user()->isSuperAdmin() || Auth::user()->isEffectiveDirector() || Auth::user()->isEffectiveGeneralManager();
                    @endphp
                    @if($canSetPermissions && $role_id && !$isSuperAdminRole)
                        <div class="col-12">
                            <label class="form-label fw-bold">Assign modules &amp; permissions</label>
                            <p class="text-muted small mb-2">
                                Super Admin, Director, and General Manager can assign modules and permissions. For each module tick <strong>module access</strong> and the <strong>permissions</strong> (operations) this user can perform. The user also has permissions from their <strong>Role</strong>; selections here add or refine access.
                            </p>
                            @if(count($modules) > 0)
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="selectAllModules"><i class="fa fa-check-double me-1"></i>Select all modules</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearModules"><i class="fa fa-times me-1"></i>Clear modules</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="selectAllPermissions"><i class="fa fa-check-double me-1"></i>Select all permissions</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="clearPermissions"><i class="fa fa-times me-1"></i>Clear permissions</button>
                                </div>
                                <div class="accordion accordion-flush" id="userModulesAccordion">
                                    @foreach($modules as $index => $module)
                                        @php
                                            $modulePerms = $permissions->where('module_slug', $module->slug);
                                        @endphp
                                        <div class="accordion-item border rounded mb-2">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#module-{{ $module->id }}" aria-expanded="false" aria-controls="module-{{ $module->id }}">
                                                    <div class="form-check me-2" onclick="event.stopPropagation()">
                                                        <input class="form-check-input" type="checkbox" id="module_{{ $module->id }}" value="{{ $module->id }}" wire:model="selectedModules">
                                                        <label class="form-check-label" for="module_{{ $module->id }}">
                                                            <i class="fa fa-{{ $module->icon ?? 'circle' }} me-1"></i>
                                                            <strong>{{ $module->name }}</strong>
                                                            @if($modulePerms->isNotEmpty())
                                                                <span class="badge bg-secondary ms-1">{{ $modulePerms->count() }} permissions</span>
                                                            @endif
                                                        </label>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="module-{{ $module->id }}" class="accordion-collapse collapse" data-bs-parent="#userModulesAccordion">
                                                <div class="accordion-body pt-0 pb-2">
                                                    @if($modulePerms->isNotEmpty())
                                                        <p class="small text-muted mb-2">Operations this user can perform in <strong>{{ $module->name }}</strong> (in addition to role permissions):</p>
                                                        <div class="row g-2">
                                                            @foreach($modulePerms as $perm)
                                                                <div class="col-md-6 col-lg-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" id="perm_{{ $perm->id }}" value="{{ $perm->id }}" wire:model="selectedPermissions">
                                                                        <label class="form-check-label small" for="perm_{{ $perm->id }}" title="{{ $perm->description ?? '' }}">
                                                                            {{ $perm->name }}
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <p class="small text-muted mb-0">No permissions defined for this module. Checking the module above only grants access to the area; operations come from the user's role.</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Modules are filtered by hotel-enabled modules. User's effective permission = <strong>Role</strong> has it <strong>or</strong> it is checked here.
                                </small>
                            @else
                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    <strong>No modules available.</strong> Please enable modules for the hotel in <a href="{{ route('system.configuration') }}" target="_blank">System Configuration</a> first.
                                </div>
                            @endif
                        </div>
                    @elseif($canSetPermissions && $role_id && $isSuperAdminRole)
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>
                                Super Admin has access to all modules automatically.
                            </div>
                        </div>
                    @endif
                    
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" class="btn btn-secondary" wire:click="resetForm">Cancel</button>
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
                    <th>Departments</th>
                    <th>Status</th>
                    <th>Email Verified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->name ?? 'No Role' }}</td>
                        <td>
                            @if($user->departments->isNotEmpty())
                                @foreach($user->departments as $d)
                                    <span class="badge bg-light text-dark border me-1">{{ $d->name }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
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
                                @if(Auth::user()->isSuperAdmin())
                                    <button class="btn btn-sm btn-outline-primary" wire:click="verifyEmail({{ $user->id }})">
                                        Verify
                                    </button>
                                @endif
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                @if(Auth::user()->isSuperAdmin() || Auth::user()->isEffectiveDirector() || Auth::user()->isEffectiveGeneralManager())
                                    <button type="button" class="btn btn-outline-info" wire:click="viewModulesPermissions({{ $user->id }})" title="View modules & permissions">
                                        <i class="fa fa-list-alt"></i>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-primary" wire:click="edit({{ $user->id }})" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </button>
                                @if($user->id != Auth::id())
                                    <button type="button" class="btn btn-danger" wire:click="delete({{ $user->id }})" 
                                        onclick="return confirm('Are you sure you want to delete this user?')" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No users found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($showModulesPermissionsModal && $viewingUser)
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,.5);" wire:click.self="closeModulesPermissionsModal">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" wire:click.stop>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modules &amp; permissions — {{ $viewingUser->name }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModulesPermissionsModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">{{ $viewingUser->email }} · Role: <strong>{{ $viewingUser->role->name ?? '—' }}</strong></p>
                        @if($viewingUser->departments->isNotEmpty())
                            <p class="mb-3"><strong>Departments:</strong>
                                @foreach($viewingUser->departments as $d)
                                    <span class="badge bg-secondary me-1">{{ $d->name }}</span>
                                @endforeach
                            </p>
                        @endif
                        @php
                            $accessibleModules = $viewingUser->getAccessibleModules();
                            $rolePerms = $viewingUser->role ? $viewingUser->role->permissions : collect();
                            $userPerms = $viewingUser->permissions;
                            $allPerms = $rolePerms->merge($userPerms)->unique('id')->sortBy('module_slug');
                        @endphp
                        <h6 class="mt-3">Modules ({{ $accessibleModules->count() }})</h6>
                        <ul class="list-unstyled small mb-3">
                            @forelse($accessibleModules as $mod)
                                <li><i class="fa fa-{{ $mod->icon ?? 'circle' }} me-1"></i>{{ $mod->name }}</li>
                            @empty
                                <li class="text-muted">None</li>
                            @endforelse
                        </ul>
                        <h6 class="mt-3">Permissions by module</h6>
                        @foreach($allPerms->groupBy('module_slug') as $modSlug => $permissions)
                            <div class="mb-2">
                                <strong class="text-secondary">{{ $modSlug ?: 'General' }}</strong>
                                <ul class="list-unstyled small ms-2 mb-0">
                                    @foreach($permissions as $p)
                                        <li>{{ $p->name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                        @if($allPerms->isEmpty())
                            <p class="text-muted small mb-0">No permissions assigned (role may have no permissions, or Super Admin has all).</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModulesPermissionsModal">Close</button>
                        <button type="button" class="btn btn-primary" wire:click="closeAndEditUser({{ $viewingUser->id }})">Edit user</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
