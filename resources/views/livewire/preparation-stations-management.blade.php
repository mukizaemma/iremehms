<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">Preparation &amp; posting stations</h5>
            <p class="text-muted small mb-0">Stations where menu items are prepared. Orders are posted here; you can assign a printer to each station for order tickets.</p>
        </div>
        <button class="btn btn-primary" wire:click="openForm()">
            <i class="fa fa-plus me-2"></i>Add station
        </button>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="mb-3">
        <input type="text" class="form-control" placeholder="Search stations..." wire:model.live.debounce.300ms="search" style="max-width: 300px;">
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Printer (posting)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stations as $station)
                            <tr>
                                <td><strong>{{ $station->name }}</strong></td>
                                <td>{{ $station->display_order }}</td>
                                <td>
                                    @if($station->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    @if($station->has_printer && $station->printer_name)
                                        <span class="badge bg-info">{{ $station->printer_name }}</span>
                                    @elseif($station->has_printer)
                                        <span class="badge bg-light text-dark">Default printer</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openForm({{ $station->id }})">Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteStation({{ $station->id }})" onclick="return confirm('Delete this station?')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No stations yet. Add one to assign menu items and optionally a printer for order tickets.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($showForm)
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Edit station' : 'Add station' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeForm"></button>
                    </div>
                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model="name" placeholder="e.g. Kitchen, Bar">
                                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Display order</label>
                                <input type="number" class="form-control" wire:model="display_order" min="0">
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" wire:model="has_printer" id="has_printer">
                                    <label class="form-check-label" for="has_printer">This station has a printer (for order tickets)</label>
                                </div>
                            </div>
                            @if($has_printer)
                                <div class="mb-3 ms-4">
                                    <label class="form-label">Printer name / identifier</label>
                                    <input type="text" class="form-control" wire:model="printer_name" placeholder="e.g. Kitchen Printer, Bar POS">
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
