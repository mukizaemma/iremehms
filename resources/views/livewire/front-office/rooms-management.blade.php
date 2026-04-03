@if($embed)
{{-- Embedded in Rooms page: no extra wrapper or duplicate heading --}}
<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="d-flex justify-content-end mb-2">
        @if ($this->canManageRooms())
            <button class="btn btn-primary btn-sm" wire:click="openForm()">
                <i class="fa fa-plus me-1"></i>Add Room
            </button>
        @endif
    </div>
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Check-in</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="stay_check_in">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Check-out</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="stay_check_out">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-0">Search room</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="search" placeholder="Room number, name or floor">
                </div>
                @if($viewByTypeId)
                    <div class="col-md-2 d-grid">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearViewByType">Back to categories</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body py-2">
            @if($viewByTypeId)
                @if (count($rooms) > 0)
                    @if($this->shouldRenderWingColumns())
                        @php $wingColumns = $this->getWingColumnsForRoomView(); @endphp
                        <div class="row g-3">
                            @foreach($wingColumns as $col)
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="rounded border h-100 bg-light overflow-hidden d-flex flex-column">
                                        <div class="px-2 py-2 border-bottom bg-white d-flex justify-content-between align-items-center gap-2">
                                            <span class="small fw-semibold text-truncate" title="{{ $col['name'] }}">
                                                {{ $col['name'] }}
                                                @if(!empty($col['code']))
                                                    <span class="badge bg-secondary ms-1">{{ $col['code'] }}</span>
                                                @endif
                                            </span>
                                            <span class="badge rounded-pill bg-white text-dark border flex-shrink-0">{{ count($col['rooms']) }}</span>
                                        </div>
                                        <div class="p-2 d-flex flex-column gap-2 flex-grow-1">
                                            @forelse($col['rooms'] as $room)
                                                @include('livewire.front-office.partials.room-explorer-card', [
                                                    'room' => $room,
                                                    'hideWingBadge' => true,
                                                    'stay_check_in' => $stay_check_in,
                                                    'stay_check_out' => $stay_check_out,
                                                ])
                                            @empty
                                                <div class="text-muted small text-center py-3 mb-0">—</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex flex-column gap-2">
                            @foreach ($rooms as $room)
                                @include('livewire.front-office.partials.room-explorer-card', [
                                    'room' => $room,
                                    'hideWingBadge' => false,
                                    'stay_check_in' => $stay_check_in,
                                    'stay_check_out' => $stay_check_out,
                                ])
                            @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <p class="text-muted small mb-0">No rooms in this category.</p>
                @endif
            @else
                @php $summaryRows = $this->getRoomTypeSummaryRows(); @endphp
                @if (count($summaryRows) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Room type</th>
                                    <th>All</th>
                                    <th>Vacant</th>
                                    <th>Occupied</th>
                                    <th>Price (from)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summaryRows as $row)
                                    <tr role="button" style="cursor: pointer;" wire:click="openByType({{ $row['id'] }})">
                                        <td><strong>{{ $row['name'] }}</strong></td>
                                        <td>{{ $row['all'] }}</td>
                                        <td><span class="badge bg-success">{{ $row['vacant'] }}</span></td>
                                        <td><span class="badge bg-primary">{{ $row['occupied'] }}</span></td>
                                        <td class="text-nowrap">{{ $row['rate_display'] }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click.stop="openByType({{ $row['id'] }})">
                                                View rooms
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted small mb-0">No room types configured yet.</p>
                @endif
            @endif
        </div>
    </div>
    @if ($showRoomDetailModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Room — {{ $selectedRoomLabel }}</h5>
                        <button type="button" class="btn-close" wire:click="closeRoomDetail"></button>
                    </div>
                    <div class="modal-body">
                        @if(!empty($selectedRoomDetails))
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Floor</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['floor'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Room #</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['room_number'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Room name</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['name'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Type</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['type_name'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Units</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['units_count'] ?? 0 }}</dd>
                                <dt class="col-sm-4">Rate (from)</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['rate_display'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    @if(!empty($selectedRoomDetails['guest_name']))
                                        <span class="text-primary">{{ $selectedRoomDetails['guest_name'] }}</span>
                                        <span class="badge bg-{{ $selectedRoomDetails['status_label'] === 'Occupied' ? 'primary' : 'warning' }} ms-1">{{ $selectedRoomDetails['status_label'] }}</span>
                                    @else
                                        <span class="badge bg-success">Vacant</span>
                                    @endif
                                </dd>
                            </dl>
                            @if($selectedRoomFirstUnitId)
                                <div class="mt-3">
                                    <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $selectedRoomFirstUnitId]) }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>New reservation</a>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeRoomDetail">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@else
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">Rooms management</h5>
                        <p class="text-muted small mb-0">{{ $this->canManageRooms() ? 'Add rooms as Standard or Single room, then set room number, name, floor and units. Reservations and calendar use these rooms.' : 'View rooms and their bookable units.' }} @if($this->chargeLevelIsRoom) <strong>Rates:</strong> set per room when adding or editing. @else To set nightly rates for Standard/Single, <a href="{{ route('room-types.index') }}">set room category rates</a>. @endif</p>
                    </div>
                    @if ($this->canManageRooms())
                        <button class="btn btn-primary" wire:click="openForm()">
                            <i class="fa fa-plus me-2"></i>Add Room
                        </button>
                    @endif
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
                            <div class="col-md-4">
                                <label class="form-label small">Room type</label>
                                <select class="form-select" wire:model.live="filterType">
                                    <option value="">All types</option>
                                    @foreach ($roomTypes as $rt)
                                        <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="search" wire:model.live="search" placeholder="Search...">
                                    <label for="search">Search name or floor</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        @if (count($rooms) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Room #</th>
                                            <th>Room</th>
                                            <th>Type</th>
                                            <th>Floor</th>
                                            <th>Units</th>
                                            <th>Rate (from)</th>
                                            <th>Status / Guest</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $currency = \App\Models\Hotel::getHotel()->currency ?? 'RWF'; @endphp
                                        @foreach ($rooms as $room)
                                            @php
                                                if ($this->chargeLevelIsRoom && !empty($room['rates'])) {
                                                    $rates = $room['rates'];
                                                } else {
                                                    $rates = $room['room_type']['rates'] ?? [];
                                                }
                                                $locals = collect($rates)->firstWhere('rate_type', 'Locals');
                                                $fromAmount = $locals ? (float) $locals['amount'] : null;
                                                if ($fromAmount === null && count($rates) > 0) {
                                                    $fromAmount = (float) collect($rates)->min('amount');
                                                }
                                                $rateDisplay = $fromAmount !== null ? number_format($fromAmount) . ' ' . $currency : '—';
                                                $status = $room['availability_status'] ?? 'vacant';
                                                $guestName = $room['current_guest_name'] ?? null;
                                            @endphp
                                            <tr>
                                                <td><code>{{ $room['room_number'] ?? $room['name'] }}</code></td>
                                                <td><strong>{{ $room['name'] }}</strong></td>
                                                <td>{{ $room['room_type']['name'] ?? '—' }}</td>
                                                <td>{{ $room['floor'] ?? '—' }}</td>
                                                <td>{{ count($room['room_units'] ?? []) }}</td>
                                                <td class="text-nowrap">{{ $rateDisplay }}</td>
                                                <td>
                                                    @if ($guestName)
                                                        <span class="text-primary fw-medium" title="{{ $status === 'occupied' ? 'Occupied' : 'Due out' }}">{{ $guestName }}</span>
                                                        <span class="badge bg-{{ $status === 'occupied' ? 'primary' : 'warning' }} ms-1">{{ $status === 'occupied' ? 'Occupied' : 'Due out' }}</span>
                                                    @else
                                                        <span class="badge bg-success">Vacant</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" wire:click="openRoomDetail({{ $room['id'] }})" title="View room & upcoming reservations">
                                                        <i class="fa fa-eye"></i> View
                                                    </button>
                                                    @if ($this->canManageRooms())
                                                    <button class="btn btn-sm btn-outline-primary" wire:click="openUnitsForm({{ $room['id'] }})" title="Manage units/beds">
                                                        <i class="fa fa-list"></i> Units
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" wire:click="openForm({{ $room['id'] }})" title="Edit">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-{{ $room['is_active'] ? 'warning' : 'success' }}" wire:click="toggleActive({{ $room['id'] }})" title="{{ $room['is_active'] ? 'Deactivate' : 'Activate' }}">
                                                        <i class="fa fa-{{ $room['is_active'] ? 'ban' : 'check' }}"></i>
                                                    </button>
                                                    @if ($room['has_reservations'] ?? false)
                                                        @if ($this->allowDeleteFromIreme && $this->canForceDeleteOrResetRoom())
                                                            <button class="btn btn-sm btn-danger" wire:click="resetAndDeleteRoom({{ $room['id'] }})" wire:confirm="Unlink this room from all reservations and delete it? Only Super Admin can do this." title="Reset and delete"><i class="fa fa-trash"></i></button>
                                                        @endif
                                                        <button class="btn btn-sm btn-warning" wire:click="removeFromUse({{ $room['id'] }})" title="Remove from use (pending Super Admin from Ireme to confirm delete)"><i class="fa fa-ban"></i> Remove from use</button>
                                                    @elseif ($room['pending_deletion'] ?? false)
                                                        @if ($this->allowDeleteFromIreme && $this->canForceDeleteOrResetRoom())
                                                            <button class="btn btn-sm btn-danger" wire:click="deleteRoom({{ $room['id'] }})" wire:confirm="Confirm delete this room?" title="Confirm delete"><i class="fa fa-trash"></i></button>
                                                        @else
                                                            <span class="text-muted small">Pending deletion</span>
                                                        @endif
                                                    @else
                                                        @if ($this->allowDeleteFromIreme && $this->canForceDeleteOrResetRoom())
                                                            <button class="btn btn-sm btn-danger" wire:click="deleteRoom({{ $room['id'] }})" wire:confirm="Delete this room and its units?" title="Delete">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        @else
                                                            <button class="btn btn-sm btn-warning" wire:click="removeFromUse({{ $room['id'] }})" title="Remove from use (pending Super Admin from Ireme to confirm delete)"><i class="fa fa-ban"></i> Remove from use</button>
                                                        @endif
                                                    @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>No rooms yet. Click <strong>Add Room</strong> to add a Standard or Single room with room number, name, floor and number of units.
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
                        <h5 class="modal-title">{{ $editingId ? 'Edit' : 'New' }} Room</h5>
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
                                <label for="room_type_id" class="form-label">Room category <span class="text-danger">*</span></label>
                                <select class="form-select" id="room_type_id" wire:model.defer="room_type_id" required>
                                    @foreach ($roomTypes as $rt)
                                        <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Choose Standard room or Single room, then enter details below.</small>
                            </div>
                            <div class="mb-3">
                                <label for="room_number" class="form-label">Room number</label>
                                <input type="text" class="form-control" id="room_number" wire:model.defer="room_number" placeholder="e.g. 101">
                            </div>
                            <div class="mb-3">
                                <label for="name" class="form-label">Room name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" wire:model.defer="name" placeholder="e.g. Standard Double" required>
                                @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="floor" class="form-label">Floor</label>
                                <input type="text" class="form-control" id="floor" wire:model.defer="floor" placeholder="e.g. 3">
                            </div>
                            @if(!$editingId)
                            <div class="mb-3">
                                <label for="number_of_units" class="form-label">Number of rooms in this category</label>
                                <input type="number" class="form-control" id="number_of_units" wire:model.defer="number_of_units" min="1" max="50" placeholder="1">
                                <small class="text-muted">How many bookable units (beds/rooms) to create for this room. Default 1.</small>
                                @error('number_of_units')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            @endif
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            @if ($this->chargeLevelIsRoom)
                                <div class="mb-3">
                                    <label class="form-label">Rates by rate type</label>
                                    <p class="text-muted small mb-2">Set the nightly rate for each rate type for this room (hotel is set to charge by room).</p>
                                    <div class="row g-2">
                                        @foreach (\App\Livewire\FrontOffice\AddReservation::RATE_TYPES as $idx => $rateType)
                                            <div class="col-6 col-md-4">
                                                <label class="form-label small">{{ $rateType }}</label>
                                                <input type="number" class="form-control form-control-sm" wire:model.defer="rate_amounts.{{ $idx }}" step="0.01" min="0" placeholder="0">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label">Room amenities</label>
                                <p class="text-muted small mb-2">Select amenities available in this room.</p>
                                @if (count($roomAmenities) > 0)
                                    <div class="row g-2">
                                        @foreach ($roomAmenities as $amenity)
                                            <div class="col-12 col-md-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="amenity_{{ $amenity->id }}" value="{{ $amenity->id }}" wire:model="room_amenity_ids">
                                                    <label class="form-check-label" for="amenity_{{ $amenity->id }}">{{ $amenity->name }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">No room amenities defined. Add them in the Amenities tab.</p>
                                @endif
                            </div>
                            @if ($editingId)
                                <div class="mb-3 border-top pt-3">
                                    <label class="form-label">Room gallery</label>
                                    <p class="text-muted small mb-2">Upload images for this room.</p>
                                    @if (count($roomImages) > 0)
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            @foreach ($roomImages as $img)
                                                <div class="position-relative" style="width: 100px;">
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($img['path']) }}" alt="" class="img-fluid rounded border" style="height: 80px; object-fit: cover; width: 100%;">
                                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" wire:click="removeRoomImage({{ $img['id'] }})" wire:confirm="Remove this image?" title="Remove">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="input-group">
                                        <input type="file" class="form-control" wire:model="newRoomImage" accept="image/*">
                                        <input type="text" class="form-control" placeholder="Caption (optional)" wire:model.defer="newRoomImageCaption" style="max-width: 180px;">
                                        <button type="button" class="btn btn-outline-primary" wire:click="addRoomImage" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="addRoomImage,newRoomImage"><i class="fa fa-upload me-1"></i>Add</span>
                                            <span wire:loading wire:target="addRoomImage,newRoomImage"><span class="spinner-border spinner-border-sm me-1"></span>Uploading...</span>
                                        </button>
                                    </div>
                                    @error('newRoomImage')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeForm">{{ $editingId ? 'Cancel' : 'Cancel' }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="save"><i class="fa fa-save me-2"></i>{{ $editingId ? 'Save' : 'Save' }}</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-2"></span>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showUnitsForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Units / Beds — {{ $unitsRoomName }}</h5>
                        <button type="button" class="btn-close" wire:click="closeUnitsForm"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Each unit is a bookable slot (e.g. one bed in a dorm, or the whole room for a standard).</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" wire:model.defer="unitLabel" placeholder="Unit label (e.g. Mixed Dorm 302 (1))" wire:keydown.enter.prevent="addUnit">
                            <button type="button" class="btn btn-primary" wire:click="addUnit" wire:loading.attr="disabled">Add unit</button>
                        </div>
                        @error('unitLabel')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <ul class="list-group">
                            @foreach ($units as $u)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    {{ $u['label'] }}
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeUnit({{ $u['id'] }})" wire:confirm="Remove this unit?">Remove</button>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($units) === 0)
                            <p class="text-muted small mb-0 mt-2">No units yet. Add one above.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeUnitsForm">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showRoomDetailModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Room — {{ $selectedRoomLabel }}</h5>
                        <button type="button" class="btn-close" wire:click="closeRoomDetail"></button>
                    </div>
                    <div class="modal-body">
                        @if(!empty($selectedRoomDetails))
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Floor</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['floor'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Room #</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['room_number'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Room name</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['name'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Type</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['type_name'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Units</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['units_count'] ?? 0 }}</dd>
                                <dt class="col-sm-4">Rate (from)</dt>
                                <dd class="col-sm-8">{{ $selectedRoomDetails['rate_display'] ?? '—' }}</dd>
                                <dt class="col-sm-4">Status</dt>
                                <dd class="col-sm-8">
                                    @if(!empty($selectedRoomDetails['guest_name']))
                                        <span class="text-primary">{{ $selectedRoomDetails['guest_name'] }}</span>
                                        <span class="badge bg-{{ $selectedRoomDetails['status_label'] === 'Occupied' ? 'primary' : 'warning' }} ms-1">{{ $selectedRoomDetails['status_label'] }}</span>
                                    @else
                                        <span class="badge bg-success">Vacant</span>
                                    @endif
                                </dd>
                            </dl>
                            @if($selectedRoomFirstUnitId)
                                <div class="mt-3">
                                    <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $selectedRoomFirstUnitId]) }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>New reservation</a>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeRoomDetail">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endif
