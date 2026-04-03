<div class="row g-4">
    <div class="col-12">
        <div class="bg-light rounded p-4">
            <h5 class="mb-4">Super Admin Dashboard</h5>
            <p>Welcome, <strong>{{ $user->name }}</strong>! You have full system access.</p>
            
            <div class="row g-3 mt-3">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">System Configuration</h6>
                            <p class="card-text">Manage hotel settings, branding, and packages.</p>
                            <a href="{{ route('system.configuration') }}" class="btn btn-primary btn-sm">
                                Configure
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Shift Management</h6>
                            <p class="card-text">Configure shifts, business days, and time settings.</p>
                            <a href="{{ route('shift.management') }}" class="btn btn-primary btn-sm">
                                Manage Shifts
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">User Management</h6>
                            <p class="card-text">Create and manage users, roles, and permissions.</p>
                            <a href="{{ route('users.index') }}" class="btn btn-primary btn-sm">
                                Manage Users
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Module Management</h6>
                            <p class="card-text">Enable or disable modules based on package.</p>
                            <button class="btn btn-primary btn-sm">
                                Manage Modules
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
