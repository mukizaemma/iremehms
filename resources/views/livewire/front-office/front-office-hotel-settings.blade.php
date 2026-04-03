<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <h5 class="mb-3">Hotel settings (Front Office)</h5>
                <p class="text-muted small mb-4">Reservation contacts and public page URL for your hotel. Map is configured under System Configuration (Super Admin).</p>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $tab === 'contacts' ? 'active' : '' }}" wire:click="setTab('contacts')">Reservation contacts</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $tab === 'pricing' ? 'active' : '' }}" wire:click="setTab('pricing')">Pricing</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link {{ $tab === 'public-urls' ? 'active' : '' }}" wire:click="setTab('public-urls')">Public page URLs</button>
                    </li>
                </ul>

                @if ($tab === 'contacts')
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label">Reservation contacts</label>
                            <textarea class="form-control" wire:model="reservation_contacts" rows="5" placeholder="e.g. Email: reservations@hotel.com, Phone: +250..."></textarea>
                            <small class="text-muted">Contact details shown for reservations (email, phone, etc.).</small>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" wire:click="saveContacts">Save</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($tab === 'pricing')
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label">Charge by</label>
                            <p class="text-muted small">Choose whether nightly rates are defined per <strong>room type</strong> (e.g. all "Double" rooms share the same rates) or per <strong>room</strong> (each room can have its own rates).</p>
                            <select class="form-select w-auto" wire:model="charge_level">
                                <option value="room_type">Room type (one set of rates per category)</option>
                                <option value="room">Room (rates per individual room/unit)</option>
                            </select>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" wire:click="saveChargeLevel">Save</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($tab === 'public-urls')
                    <div class="card">
                        <div class="card-body">
                            <label class="form-label">Public page URL</label>
                            <p class="text-muted small">URL format: <strong>domain/booking/hotel-id</strong>. Set the base domain/URL below; management can change it anytime. Use for your public booking page.</p>

                            <div class="mb-3">
                                <label class="form-label">Booking base URL / domain</label>
                                <input type="text" class="form-control font-monospace" wire:model.defer="public_booking_domain" placeholder="e.g. https://yoursite.com or yoursite.com">
                                <small class="text-muted">Leave empty to use this app’s URL. You can enter a full URL (https://…) or just the domain.</small>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="savePublicUrlSettings">Save domain</button>
                                </div>
                            </div>

                            @if ($public_url)
                                <label class="form-label">Full public booking URL</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control font-monospace" value="{{ $public_url }}" id="publicUrlInput" readonly>
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('publicUrlInput').value); alert('Copied!');">Copy</button>
                                </div>
                                <small class="text-muted">Hotel id: <code>{{ $public_slug }}</code> (encoded; secure and unguessable)</small>
                            @else
                                <p class="text-muted mb-2">No public URL generated yet. Generate a hotel id to get your booking URL.</p>
                                <button type="button" class="btn btn-primary" wire:click="generatePublicSlug">Generate public URL</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
