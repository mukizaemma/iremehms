<div>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ireme.hotels.users', $hotel) }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fa fa-arrow-left"></i> Back to users</a>
        <h5 class="mb-0">Manage permissions – {{ $user->name }}</h5>
    </div>
    <p class="text-muted small mb-3">{{ $user->email }} · Role: <strong>{{ $user->role->name ?? '—' }}</strong> · Hotel: {{ $hotel->name }}</p>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <strong>Modules &amp; permissions</strong>
            <p class="small text-muted mb-0 mt-1">Assign module access and permissions for this user. They also have permissions from their role; selections here add or refine access.</p>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save">
                @if($modules && $modules->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="selectAllModules"><i class="fa fa-check-double me-1"></i>Select all modules</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearModules"><i class="fa fa-times me-1"></i>Clear modules</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="selectAllPermissions"><i class="fa fa-check-double me-1"></i>Select all permissions</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="clearPermissions"><i class="fa fa-times me-1"></i>Clear permissions</button>
                    </div>
                    <div class="accordion accordion-flush" id="iremeUserModulesAccordion">
                        @foreach($modules as $module)
                            @php
                                $modulePerms = $permissions->where('module_slug', $module->slug);
                            @endphp
                            <div class="accordion-item border rounded mb-2" x-data="{ open: false }">
                                <h2 class="accordion-header">
                                    <button class="accordion-button py-2" type="button"
                                            :class="{ 'collapsed': !open }"
                                            aria-expanded="false"
                                            x-bind:aria-expanded="open"
                                            @click="open = !open">
                                        <div class="form-check me-2" @click.stop>
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
                                <div x-show="open"
                                     x-collapse
                                     class="accordion-collapse">
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
                                            <p class="small text-muted mb-0">No permissions defined for this module. Checking the module above only grants access to the area.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fa fa-info-circle me-1"></i>
                        Modules are filtered by this hotel's enabled modules. User's effective permission = <strong>Role</strong> has it <strong>or</strong> it is checked here.
                    </small>
                @else
                    <div class="alert alert-warning mb-0">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>No modules available.</strong> Enable modules for this hotel in the hotel's System Configuration first.
                    </div>
                @endif

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save permissions</button>
                    <a href="{{ route('ireme.hotels.users', $hotel) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
