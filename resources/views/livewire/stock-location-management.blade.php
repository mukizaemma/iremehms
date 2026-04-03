<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Stock Locations Management</h4>
        <button class="btn btn-primary" wire:click="openLocationForm">
            <i class="fa fa-plus me-2"></i>Add Main Location
        </button>
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

    <!-- Locations List -->
    <div class="card">
        <div class="card-body">
            @if(count($locations) > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Parent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($locations as $location)
                                <tr class="{{ $location['is_main_location'] ? 'table-primary' : '' }}">
                                    <td>
                                        @if($location['is_main_location'])
                                            <span class="badge bg-primary">Main</span>
                                        @else
                                            <span class="badge bg-secondary">Sub</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $location['name'] }}</strong></td>
                                    <td><code>{{ $location['code'] ?? 'N/A' }}</code></td>
                                    <td>{{ $location['description'] ?? 'N/A' }}</td>
                                    <td>
                                        @if($location['parent_location'])
                                            {{ $location['parent_location']['name'] }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($location['is_active'])
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($location['is_main_location'])
                                            <button class="btn btn-sm btn-warning" wire:click="openSubLocationForm({{ $location['id'] }})" title="Add Sub-Location">
                                                <i class="fa fa-plus"></i> Sub
                                            </button>
                                        @endif
                                        <button class="btn btn-sm btn-info" wire:click="openLocationForm({{ $location['id'] }})" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-{{ $location['is_active'] ? 'secondary' : 'success' }}" wire:click="toggleActive({{ $location['id'] }})" title="{{ $location['is_active'] ? 'Deactivate' : 'Activate' }}">
                                            <i class="fa fa-{{ $location['is_active'] ? 'eye-slash' : 'eye' }}"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" wire:click="deleteLocation({{ $location['id'] }})" title="Delete" onclick="return confirm('Are you sure? This will fail if location has stocks or sub-locations.')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="fa fa-info-circle me-2"></i>
                    No stock locations found. Create at least one main location to start.
                </div>
            @endif
        </div>
    </div>

    <!-- Location Form Modal -->
    @if($showLocationForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingLocationId ? 'Edit Stock Location' : ($parent_location_id ? 'Add Sub-Location' : 'Add Main Location') }}</h5>
                        <button type="button" class="btn-close" wire:click="closeLocationForm"></button>
                    </div>
                    <form wire:submit.prevent="saveLocation">
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

                            @if($parent_location_id)
                                @php
                                    $parentLocation = \App\Models\StockLocation::find($parent_location_id);
                                @endphp
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle me-2"></i>
                                    Adding sub-location under: <strong>{{ $parentLocation->name ?? 'N/A' }}</strong>
                                </div>
                            @endif

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model.defer="name" required>
                                <label for="name">Location Name <span class="text-danger">*</span></label>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" wire:model.defer="code">
                                <label for="code">Code (Optional)</label>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" wire:model.defer="description" style="height: 100px"></textarea>
                                <label for="description">Description</label>
                            </div>

                            @if(!$parent_location_id && !$editingLocationId)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_main_location" wire:model.defer="is_main_location" value="1">
                                    <label class="form-check-label" for="is_main_location">
                                        This is a Main Location
                                    </label>
                                </div>
                            @endif

                            @if(!$is_main_location && !$parent_location_id)
                                <div class="form-floating mb-3">
                                    <select class="form-select @error('parent_location_id') is-invalid @enderror" id="parent_location_id" wire:model.defer="parent_location_id" required>
                                        <option value="">Select Parent Location</option>
                                        @foreach($mainLocations as $mainLoc)
                                            <option value="{{ $mainLoc->id }}">{{ $mainLoc->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="parent_location_id">Parent Location <span class="text-danger">*</span></label>
                                    @error('parent_location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeLocationForm" wire:loading.attr="disabled">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove>Save</span>
                                <span wire:loading>
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
