<div>
    <div class="d-flex align-items-center mb-4">
        <a href="{{ $hotel ? route('ireme.hotels.index') : route('ireme.hotels.index') }}" class="btn btn-outline-secondary btn-sm me-2"><i class="fa fa-arrow-left"></i></a>
        <h5 class="mb-0">{{ $hotel ? 'Edit Hotel' : 'Onboard Hotel' }}</h5>
    </div>

    <form wire:submit.prevent="save">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Basic details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Hotel number (code)</label>
                        <input type="number" class="form-control" wire:model="hotel_code" min="100" max="999" placeholder="100-999">
                        @error('hotel_code') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" wire:model="name" placeholder="Hotel name">
                        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" wire:model="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact</label>
                        <input type="text" class="form-control" wire:model="contact">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" wire:model="address" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hotel logo</label>
                        <input type="file" class="form-control" wire:model="logo" accept="image/*">
                        @if($logo_preview)
                            <img src="{{ $logo_preview }}" alt="Logo" class="mt-2" style="max-height: 60px; width: auto;">
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Subscription</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select class="form-select" wire:model="subscription_type">
                            @foreach($subscriptionTypes as $t)
                                <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" wire:model="subscription_status">
                            @foreach($subscriptionStatuses as $s)
                                <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Amount (per period)</label>
                        <input type="number" step="0.01" min="0" class="form-control" wire:model="subscription_amount" placeholder="0.00">
                        @error('subscription_amount') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start date</label>
                        <input type="date" class="form-control" wire:model="subscription_start_date">
                        @error('subscription_start_date') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Next due date</label>
                        <input type="date" class="form-control" wire:model="next_due_date" placeholder="Invoices generated 15 days before">
                        @error('next_due_date') <small class="text-danger">{{ $message }}</small> @enderror
                        <small class="text-muted">Invoices are generated 15 days before this date; reminders at 7 days and 24 hours.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light">Modules</div>
            <div class="card-body">
                @if($canEditModules)
                    <p class="text-muted small">Select modules this hotel can use.</p>
                    <div class="row g-2">
                        @foreach($modules as $m)
                            <div class="col-md-4">
                                <label class="d-flex align-items-center">
                                    <input type="checkbox" wire:model="module_ids" value="{{ $m->id }}" class="me-2">
                                    {{ $m->name }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-2">Only Ireme Super Admin can modify modules.</p>
                    @if($hotel)
                        <p class="mb-0">{{ $hotel->modules->pluck('name')->join(', ') ?: 'None assigned' }}</p>
                    @else
                        <p class="mb-0">—</p>
                    @endif
                @endif
            </div>
        </div>

        @if(!$hotel && $canEditModules)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">First hotel admin</div>
                <div class="card-body">
                    <label class="d-flex align-items-center mb-3">
                        <input type="checkbox" wire:model="create_admin" class="me-2">
                        Create hotel admin / director / GM user
                    </label>
                    @if($create_admin)
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Role</label>
                                <select class="form-select" wire:model="admin_role">
                                    @foreach($hotelRoles as $r)
                                        <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" wire:model="admin_name">
                                @error('admin_name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" wire:model="admin_email">
                                @error('admin_email') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" wire:model="admin_password">
                                @error('admin_password') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary">{{ $hotel ? 'Update' : 'Onboard Hotel' }}</button>
        <a href="{{ route('ireme.hotels.index') }}" class="btn btn-secondary">Cancel</a>
        @if($hotel && Auth::user()->isSuperAdmin())
            <span class="ms-3 text-muted">|</span>
            <a href="{{ route('ireme.hotels.rooms', $hotel) }}" class="btn btn-outline-secondary btn-sm ms-2">Rooms</a>
            <a href="{{ route('ireme.hotels.menu-items', $hotel) }}" class="btn btn-outline-secondary btn-sm">Menu items</a>
            <a href="{{ route('ireme.hotels.additional-charges', $hotel) }}" class="btn btn-outline-secondary btn-sm">Additional charges</a>
        @endif
    </form>
</div>
