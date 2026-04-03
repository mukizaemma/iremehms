<div class="container-fluid py-4">
    <div class="mb-3">
        <h5 class="mb-2 fw-bold">Wellness</h5>
        @include('livewire.front-office.partials.front-office-quick-nav')
        <div class="mt-2">
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn {{ $tab === 'services' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="$set('tab', 'services')">Services</button>
                <button type="button" class="btn {{ $tab === 'payments' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="$set('tab', 'payments')">Payments</button>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($tab === 'services')
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-semibold">{{ $editingServiceId ? 'Edit service' : 'New service' }}</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="svc_name">
                            @error('svc_name') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Code</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="svc_code" placeholder="Optional short code">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Billing</label>
                            <select class="form-select form-select-sm" wire:model.blur="svc_billing_type">
                                <option value="per_visit">Per visit</option>
                                <option value="daily">Daily</option>
                                <option value="subscription">Subscription</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Default price (per visit)</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="svc_default_price">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Price per day</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="svc_price_per_day" placeholder="Optional — for proforma &quot;Per day&quot;">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Monthly subscription</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="svc_price_monthly" placeholder="Optional — for proforma &quot;Monthly&quot;">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Duration (minutes)</label>
                            <input type="number" class="form-control form-control-sm" wire:model.blur="svc_duration" min="1" placeholder="Optional">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Report column</label>
                            <select class="form-select form-select-sm" wire:model.blur="svc_report_bucket">
                                @foreach($reportBucketOptions as $val => $lab)
                                    <option value="{{ $val }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Description</label>
                            <textarea class="form-control form-control-sm" rows="2" wire:model.blur="svc_description"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" wire:model.blur="svc_is_active" id="svcActive">
                            <label class="form-check-label small" for="svcActive">Active</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="saveService">Save</button>
                            @if($editingServiceId)
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="startNewService">Cancel edit</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-semibold">Catalog</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Billing</th>
                                        <th>Visit / day / month</th>
                                        <th>Column</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($services as $s)
                                        <tr>
                                            <td>
                                                <div class="fw-medium">{{ $s->name }}</div>
                                                @if($s->code)<code class="small">{{ $s->code }}</code>@endif
                                            </td>
                                            <td class="text-capitalize small">{{ str_replace('_', ' ', $s->billing_type) }}</td>
                                            <td class="small">
                                                V: {{ number_format((float) $s->default_price, 2) }}
                                                @if($s->price_per_day !== null)<br>D: {{ number_format((float) $s->price_per_day, 2) }}@endif
                                                @if($s->price_monthly_subscription !== null)<br>M: {{ number_format((float) $s->price_monthly_subscription, 2) }}@endif
                                            </td>
                                            <td><code class="small">{{ $s->report_bucket_key }}</code></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-link btn-sm py-0" wire:click="editService({{ $s->id }})">Edit</button>
                                                <button type="button" class="btn btn-link text-danger btn-sm py-0" wire:click="deleteService({{ $s->id }})" wire:confirm="Remove this service?">Delete</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-muted text-center py-4">No services yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'payments')
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-semibold">Record payment</div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small">Service</label>
                            <select class="form-select form-select-sm" wire:model.blur="wp_service_id">
                                <option value="">— Select —</option>
                                @foreach($services as $s)
                                    @if($s->is_active)
                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ number_format((float) $s->default_price, 2) }})</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('wp_service_id') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Destination</label>
                            <select class="form-select form-select-sm" wire:model.live="wp_destination">
                                @foreach($paymentDestinations as $val => $lab)
                                    <option value="{{ $val }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($wp_destination === 'room_folio')
                            <div class="mb-2">
                                <label class="form-label small">Search room guest</label>
                                <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="wp_reservation_search"
                                       placeholder="Type room number, guest name, phone, or reservation #">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Room guest reservation</label>
                                <select class="form-select form-select-sm" wire:model.blur="wp_reservation_id">
                                    <option value="">— Select guest/reservation —</option>
                                    @foreach($roomReservations as $res)
                                        @php
                                            $roomLabels = $res->roomUnits->map(function($u){
                                                return $u->room?->room_number ?: ($u->room?->name ?: $u->label);
                                            })->filter()->values()->all();
                                        @endphp
                                        <option value="{{ $res->id }}">
                                            {{ $res->reservation_number }} · {{ $res->guest_name }}
                                            @if($res->guest_phone) · {{ $res->guest_phone }} @endif
                                            @if($roomLabels) · Room {{ implode(', ', $roomLabels) }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('wp_reservation_id') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        @endif
                        <div class="mb-2">
                            <label class="form-label small">Amount</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="wp_amount">
                            @error('wp_amount') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Received at</label>
                            <input type="datetime-local" class="form-control form-control-sm" wire:model.blur="wp_received_at">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Payment kind</label>
                            <select class="form-select form-select-sm" wire:model.blur="wp_kind">
                                @foreach($paymentKindLabels as $val => $lab)
                                    <option value="{{ $val }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small">Period start</label>
                                <input type="date" class="form-control form-control-sm" wire:model.blur="wp_period_start">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Period end</label>
                                <input type="date" class="form-control form-control-sm" wire:model.blur="wp_period_end">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Guest / member name</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="wp_guest_name">
                        </div>

                        <div class="mb-2 small text-muted">
                            Report column is auto-mapped from the selected service configuration.
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Notes</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="wp_notes">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="savePayment">Save payment</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-semibold">Recent payments</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>When</th>
                                        <th>Service</th>
                                        <th>Kind</th>
                                        <th class="text-end">Amount</th>
                                        <th>Destination</th>
                                        <th>Column</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payments as $p)
                                        <tr>
                                            <td class="small">{{ \Carbon\Carbon::parse($p->received_at)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $p->service_name_snapshot }}</td>
                                            <td class="text-capitalize small">{{ $p->payment_kind }}</td>
                                            <td class="text-end">{{ number_format((float) $p->amount, 2) }}</td>
                                            <td class="small">
                                                @if(($p->destination ?? "direct_payment") === "room_folio")
                                                    Room folio @if($p->reservation_id) <code>#{{ $p->reservation_id }}</code>@endif
                                                @else
                                                    Direct
                                                @endif
                                            </td>
                                            <td><code class="small">{{ $p->report_bucket_key }}</code></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-muted text-center py-4">No payments yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2">{{ $payments->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
