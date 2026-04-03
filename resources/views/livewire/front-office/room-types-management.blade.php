<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Rooms management</h5>
                        <p class="text-muted small mb-0">Manage room types (e.g. Standard, Single) with <strong>rates</strong>. Under each type, add rooms with room number, room name, floor and number of units. Used by reservations and calendar.</p>
                    </div>
                    <button class="btn btn-primary" wire:click="openForm()">
                        <i class="fa fa-plus me-2"></i>Add room type
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

                <div class="card mb-4">
                    <div class="card-header py-2"><strong>Hotel physical layout</strong></div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="has_multiple_wings" wire:model="has_multiple_wings">
                                    <label class="form-check-label" for="has_multiple_wings">Hotel has multiple wings</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Floors</label>
                                <input type="number" class="form-control form-control-sm" min="1" max="120" wire:model.defer="total_floors">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-primary" wire:click="saveLayoutConfig">Save layout</button>
                            </div>
                        </div>

                        @if($has_multiple_wings)
                            <hr>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small">Wing name</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="new_wing_name" placeholder="e.g. East Wing">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Code</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.defer="new_wing_code" placeholder="E">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-outline-primary" wire:click="addWing"><i class="fa fa-plus me-1"></i>Add wing</button>
                                </div>
                            </div>
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                @forelse($wings as $w)
                                    <span class="badge bg-light text-dark border">
                                        {{ $w['name'] }} @if(!empty($w['code'])) ({{ $w['code'] }}) @endif
                                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger" wire:click="removeWing({{ $w['id'] }})" wire:confirm="Remove this wing?"><i class="fa fa-times"></i></button>
                                    </span>
                                @empty
                                    <span class="text-muted small">No wings yet.</span>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
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
                        @if (count($types) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Rates (from)</th>
                                            <th>Rooms</th>
                                            <th>Images</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $currency = \App\Models\Hotel::getHotel()->currency ?? 'RWF'; @endphp
                                        @foreach ($types as $type)
                                            @php
                                                $rates = $type['rates'] ?? [];
                                                $locals = collect($rates)->firstWhere('rate_type', 'Locals');
                                                $fromAmount = $locals ? (float) $locals['amount'] : null;
                                                if ($fromAmount === null && count($rates) > 0) {
                                                    $fromAmount = (float) collect($rates)->min('amount');
                                                }
                                                $rateDisplay = $fromAmount !== null ? number_format($fromAmount) . ' ' . $currency : '—';
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $type['name'] }}</strong></td>
                                                <td class="text-muted small">{{ \Illuminate\Support\Str::limit($type['description'] ?? '', 50) }}</td>
                                                <td class="text-nowrap">{{ $rateDisplay }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openRoomsModal({{ $type['id'] }})" title="Manage rooms">
                                                        <i class="fa fa-door-open me-1"></i> {{ $type['rooms_count'] ?? 0 }}
                                                    </button>
                                                </td>
                                                <td>
                                                    @php $imgCount = count($type['images'] ?? []); @endphp
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openImagesModal({{ $type['id'] }})" title="Manage images">
                                                        <i class="fa fa-images me-1"></i> {{ $imgCount }}
                                                    </button>
                                                </td>
                                                <td>
                                                    @if ($type['is_active'])
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" wire:click="openForm({{ $type['id'] }})" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-{{ $type['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $type['id'] }})" title="{{ $type['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fa fa-{{ $type['is_active'] ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" wire:click="deleteType({{ $type['id'] }})" wire:confirm="Delete this room type?" title="Delete">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>No room types yet. Add one (e.g. Standard Room, Single Room) then add room details under each type.
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
                        <h5 class="modal-title">{{ $editingId ? 'Edit' : 'New' }} Room Type</h5>
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
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" wire:model.defer="description" rows="2"></textarea>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6 col-md-3">
                                    <label for="max_adults" class="form-label">Max adults</label>
                                    <input type="number" class="form-control" id="max_adults" wire:model.defer="max_adults" min="1" max="20" placeholder="2">
                                    <small class="text-muted">Used on reservation form for capacity (e.g. 2 adults + 1 child).</small>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="max_children" class="form-label">Max children</label>
                                    <input type="number" class="form-control" id="max_children" wire:model.defer="max_children" min="0" max="20" placeholder="0">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rates by rate type</label>
                                <p class="text-muted small mb-2">Set the default nightly rate for each rate type (e.g. Locals, International). Used when creating reservations. Leave blank to skip.</p>
                                <div class="row g-2">
                                    @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $idx => $rateType)
                                        <div class="col-6 col-md-4">
                                            <label class="form-label small">{{ $rateType }}</label>
                                            <input type="number" class="form-control form-control-sm" wire:model.defer="rate_amounts.{{ $idx }}" step="0.01" min="0" placeholder="0">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @if($roomAmenities->isNotEmpty())
                                <div class="mb-3">
                                    <label class="form-label">Room amenities</label>
                                    <p class="text-muted small mb-2">Select amenities that apply to this room type (e.g. AC, TV).</p>
                                    <div class="row g-2">
                                        @foreach($roomAmenities as $amenity)
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="{{ $amenity->id }}" id="amenity_{{ $amenity->id }}" wire:model="room_amenity_ids">
                                                    <label class="form-check-label" for="amenity_{{ $amenity->id }}">{{ $amenity->name }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
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

    @if ($showImagesModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Room type images — {{ $imagesForTypeName }}</h5>
                        <button type="button" class="btn-close" wire:click="closeImagesModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Add image</label>
                            <div class="input-group">
                                <input type="file" class="form-control" wire:model="newImage" accept="image/*">
                                <input type="text" class="form-control" wire:model.defer="newImageCaption" placeholder="Caption (optional)" style="max-width: 200px;">
                                <button type="button" class="btn btn-primary" wire:click="addRoomTypeImage" wire:loading.attr="disabled">Upload</button>
                            </div>
                            @error('newImage')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2">
                            @foreach($typeImages as $img)
                                <div class="col-6 col-md-4">
                                    <div class="card">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($img['path']) }}" class="card-img-top" alt="{{ $img['caption'] ?? '' }}" style="height: 120px; object-fit: cover;">
                                        <div class="card-body py-2">
                                            <p class="card-text small mb-1">{{ $img['caption'] ?? '—' }}</p>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeRoomTypeImage({{ $img['id'] }})" wire:confirm="Remove this image?">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if (count($typeImages) === 0)
                            <p class="text-muted small mb-0">No images yet. Upload one above.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeImagesModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showRoomsModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Room details — {{ $roomsForTypeName }}</h5>
                        <button type="button" class="btn-close" wire:click="closeRoomsModal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">Add rooms under this type with <strong>room number</strong>, optional description and optional price (if different from room type). Leave price blank to use the room type rate.</p>
                        <div class="card mb-4">
                            <div class="card-header py-2"><strong>Add room</strong></div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    @if($has_multiple_wings)
                                        <div class="col-md-3">
                                            <label class="form-label small mb-0">Wing <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" wire:model.defer="room_wing_id">
                                                <option value="">Select wing</option>
                                                @foreach($wings as $w)
                                                    <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                                                @endforeach
                                            </select>
                                            @error('room_wing_id')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>
                                    @endif
                                    <div class="col-md-2">
                                        <label class="form-label small mb-0">Floor</label>
                                        <input type="number" class="form-control form-control-sm" wire:model.defer="room_floor" min="1" max="{{ max(1,(int)$total_floors) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-0">Room number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" wire:model.defer="room_number" placeholder="e.g. 101" required>
                                        @error('room_number')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-0">Description (optional)</label>
                                        <input type="text" class="form-control form-control-sm" wire:model.defer="room_description" placeholder="Short description if needed">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Price details (optional — same as room type if left blank)</label>
                                    <div class="row g-2">
                                        @foreach(\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $idx => $rateType)
                                            <div class="col-6 col-md-4">
                                                <label class="form-label small mb-0">{{ $rateType }}</label>
                                                <input type="number" class="form-control form-control-sm" wire:model.defer="room_rate_amounts.{{ $idx }}" step="0.01" min="0" placeholder="Same as type">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="border rounded p-2 mb-3 bg-light-subtle">
                                    <div class="small fw-semibold mb-2">Bulk create rooms in this category</div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label small mb-0">Count</label>
                                            <input type="number" class="form-control form-control-sm" wire:model.defer="bulk_count" min="1" max="300">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-0">Prefix</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.defer="bulk_prefix" placeholder="e.g. A1-">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-0">Start number</label>
                                            <input type="number" class="form-control form-control-sm" wire:model.defer="bulk_start_number" min="1">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small mb-0">Digits</label>
                                            <input type="number" class="form-control form-control-sm" wire:model.defer="bulk_digits" min="1" max="6">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100" wire:click="bulkCreateRooms" wire:loading.attr="disabled">Generate</button>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="btn btn-primary btn-sm" wire:click="addRoomToType" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="addRoomToType"><i class="fa fa-plus me-1"></i>Add</span>
                                    <span wire:loading wire:target="addRoomToType"><span class="spinner-border spinner-border-sm"></span></span>
                                </button>
                            </div>
                        </div>
                        <h6 class="mb-2">Rooms in this category</h6>
                        @if (count($roomsList) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Room #</th>
                                            <th>Wing</th>
                                            <th>Floor</th>
                                            <th>Description</th>
                                            <th>Assign</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($roomsList as $r)
                                            <tr>
                                                <td><code>{{ $r['room_number'] ?? '—' }}</code></td>
                                                <td class="small">{{ $r['wing'] ?? '—' }}</td>
                                                <td class="small">{{ $r['floor'] ?? '—' }}</td>
                                                <td class="text-muted small">{{ \Illuminate\Support\Str::limit($r['description'] ?? '', 40) }}</td>
                                                <td style="min-width: 280px;">
                                                    <div class="d-flex gap-2 align-items-start">
                                                        @if($has_multiple_wings)
                                                            <div style="min-width: 120px;">
                                                                <select class="form-select form-select-sm" wire:model.defer="assign_wing_id.{{ $r['id'] }}">
                                                                    <option value="">Wing</option>
                                                                    @foreach($wings as $w)
                                                                        <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                                                                    @endforeach
                                                                </select>
                                                                @error('assign_wing_id.' . $r['id'])<div class="text-danger small">{{ $message }}</div>@enderror
                                                            </div>
                                                        @endif
                                                        <div style="width: 90px;">
                                                            <input type="number" class="form-control form-control-sm" min="1" max="{{ max(1,(int)$total_floors) }}" wire:model.defer="assign_floor.{{ $r['id'] }}" placeholder="Floor">
                                                            @error('assign_floor.' . $r['id'])<div class="text-danger small">{{ $message }}</div>@enderror
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="assignRoomPlacement({{ $r['id'] }})">Save</button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteRoomFromType({{ $r['id'] }})" wire:confirm="Remove this room?"><i class="fa fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No rooms yet. Add one above.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeRoomsModal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
