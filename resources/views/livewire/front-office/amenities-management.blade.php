<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Amenities</h5>
                        <p class="text-muted small mb-0">Manage room amenities (AC, TV, etc.) and hotel amenities (WiFi, pool, etc.) for your public page.</p>
                    </div>
                    <button class="btn btn-primary" wire:click="openForm()">
                        <i class="fa fa-plus me-2"></i>Add Amenity
                    </button>
                </div>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small">Type</label>
                                <select class="form-select" wire:model.live="filterType">
                                    <option value="">All</option>
                                    <option value="room">Room amenities</option>
                                    <option value="hotel">Hotel amenities</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="search" wire:model.live="search" placeholder="Search...">
                                    <label for="search">Search by name</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @if (count($amenities) > 0)
                            @if ($filterType === '')
                                @php
                                    $hotelAmenities = collect($amenities)->where('type', 'hotel')->values()->all();
                                    $roomAmenitiesList = collect($amenities)->where('type', 'room')->values()->all();
                                @endphp
                                <h6 class="text-muted mb-2">General hotel amenities</h6>
                                <p class="text-muted small mb-3">Amenities that apply to the whole property (e.g. pool, parking).</p>
                                @if (count($hotelAmenities) > 0)
                                    <div class="table-responsive mb-4">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Icon</th>
                                                    <th>Order</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($hotelAmenities as $a)
                                                    <tr>
                                                        <td><strong>{{ $a['name'] }}</strong></td>
                                                        <td><code>{{ $a['icon'] ?? '—' }}</code></td>
                                                        <td>{{ $a['sort_order'] ?? 0 }}</td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary" wire:click="openForm({{ $a['id'] }})" title="Edit"><i class="fa fa-edit"></i></button>
                                                            <button class="btn btn-sm btn-danger" wire:click="deleteAmenity({{ $a['id'] }})" wire:confirm="Delete this amenity?" title="Delete"><i class="fa fa-trash"></i></button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-4">None. Use the type filter to add hotel amenities.</p>
                                @endif
                                <h6 class="text-muted mb-2">Room amenities</h6>
                                <p class="text-muted small mb-3">Amenities that can be assigned to room types and individual rooms (e.g. AC, TV).</p>
                                @if (count($roomAmenitiesList) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Icon</th>
                                                    <th>Order</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($roomAmenitiesList as $a)
                                                    <tr>
                                                        <td><strong>{{ $a['name'] }}</strong></td>
                                                        <td><code>{{ $a['icon'] ?? '—' }}</code></td>
                                                        <td>{{ $a['sort_order'] ?? 0 }}</td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary" wire:click="openForm({{ $a['id'] }})" title="Edit"><i class="fa fa-edit"></i></button>
                                                            <button class="btn btn-sm btn-danger" wire:click="deleteAmenity({{ $a['id'] }})" wire:confirm="Delete this amenity?" title="Delete"><i class="fa fa-trash"></i></button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">None. Use the type filter to add room amenities.</p>
                                @endif
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Icon</th>
                                                <th>Order</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($amenities as $a)
                                                <tr>
                                                    <td><strong>{{ $a['name'] }}</strong></td>
                                                    <td>
                                                        @if (($a['type'] ?? '') === 'hotel')
                                                            <span class="badge bg-info">Hotel</span>
                                                        @else
                                                            <span class="badge bg-secondary">Room</span>
                                                        @endif
                                                    </td>
                                                    <td><code>{{ $a['icon'] ?? '—' }}</code></td>
                                                    <td>{{ $a['sort_order'] ?? 0 }}</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" wire:click="openForm({{ $a['id'] }})" title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" wire:click="deleteAmenity({{ $a['id'] }})" wire:confirm="Delete this amenity?" title="Delete">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>No amenities yet. Add room amenities (AC, TV, etc.) and hotel amenities (WiFi, Pool) for your public page. Run <code>php artisan db:seed --class=AmenitySeeder</code> to seed default amenities.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Edit' : 'New' }} Amenity</h5>
                        <button type="button" class="btn-close" wire:click="closeForm"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" wire:model.defer="name" required>
                                @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" wire:model.defer="type">
                                    <option value="room">Room amenity</option>
                                    <option value="hotel">Hotel amenity</option>
                                </select>
                                <small class="text-muted">Room amenities can be assigned to room types. Hotel amenities apply to the whole property.</small>
                            </div>
                            <div class="mb-3">
                                <label for="icon" class="form-label">Icon (optional)</label>
                                <input type="text" class="form-control" id="icon" wire:model.defer="icon" placeholder="e.g. fa-wifi, fa-snowflake">
                            </div>
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Sort order</label>
                                <input type="number" min="0" class="form-control" id="sort_order" wire:model.defer="sort_order">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save"><i class="fa fa-save me-2"></i>Save</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
