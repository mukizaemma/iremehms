<div class="bg-light rounded p-4">
    <div class="mb-4">
        <h5 class="mb-2">Shift Management</h5>
        @include('livewire.front-office.partials.front-office-quick-nav')
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

    @if($useOperationalShifts)
        <!-- Manual operational shifts: reference date = hotel date when opened; POS/FO can differ unless global -->
        <div class="card mb-4 border-primary" wire:poll.60s="loadOperationalShiftsUi">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Operational shifts</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Shifts are opened and closed <strong>manually</strong> by any user who has the matching <strong>open</strong> or <strong>close</strong> permission (no separate “manager only” rule).
                    Each shift is labeled with the <strong>hotel date when it was opened</strong> (it may run past midnight).
                    <strong>Default is per-module:</strong> POS, Front office, and Store each have their own shift — they can be closed at different times (e.g. POS closes and runs reports while Front office is still open).
                    <strong>Global mode</strong> is optional: only when management sets it below does one shift apply to the whole hotel.
                    Add an optional <strong>close comment</strong> when something should be communicated to supervisors.
                    <strong>Operational shifts do not use the legacy business-day flow</strong> — no business day is required for POS, Front office, or Store.
                </p>
                @if($shift_mode === 'NO_SHIFT')
                    <div class="alert alert-warning py-2 small mb-3">
                        <strong>Shift mode is NO_SHIFT.</strong> Operational shift enforcement for POS / Front office / Store is turned off.
                        Use <strong>STRICT_SHIFT</strong> or <strong>OPTIONAL_SHIFT</strong> below if you want manual operational shifts to be required.
                    </div>
                @endif

                @if(\App\Services\OperationalShiftOpenRequestService::isEnabled() && count($pendingOpenRequests) > 0)
                    <div class="alert alert-info border-info mb-3">
                        <div class="fw-semibold mb-2"><i class="fa fa-inbox me-1"></i> Pending shift open requests (from staff)</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-2">
                                <thead class="table-light">
                                    <tr>
                                        <th>Area</th>
                                        <th>Requested by</th>
                                        <th>Note</th>
                                        <th>When</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingOpenRequests as $pr)
                                        <tr wire:key="pending-req-{{ $pr['id'] }}">
                                            <td><span class="badge bg-secondary">{{ $pr['scope_label'] }}</span></td>
                                            <td>{{ $pr['requested_by'] }}</td>
                                            <td class="small">{{ $pr['note'] ? \Illuminate\Support\Str::limit($pr['note'], 80) : '—' }}</td>
                                            <td class="small text-muted">{{ $pr['created_at'] }}</td>
                                            <td class="text-end text-nowrap">
                                                @if(!empty($pr['can_fulfill']))
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="fulfillPendingOpenRequest({{ (int) $pr['id'] }})" wire:confirm="Open a shift to fulfill this request? Confirm you intend to start this shift." wire:loading.attr="disabled">
                                                        Open shift &amp; fulfill
                                                    </button>
                                                @endif
                                                @if(!empty($pr['can_reject']))
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" wire:click="promptRejectOpenRequest({{ (int) $pr['id'] }})">Reject</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label small mb-0">Optional note when opening shift from a request</label>
                                <input type="text" class="form-control form-control-sm" wire:model="fulfill_open_note" placeholder="Handover / context (optional)">
                            </div>
                            <div class="col-md-4 small text-muted">
                                “Open shift &amp; fulfill” uses this note as the operational shift open note when a shift is opened.
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label small">Optional note when opening next shift</label>
                    <input type="text" class="form-control form-control-sm" wire:model="open_op_note" placeholder="Handover notes (optional)">
                </div>

                @if($operational_shift_scope === 'global')
                    <div class="border rounded p-3 mb-3">
                        <h6 class="small fw-bold text-uppercase text-muted">All modules (global)</h6>
                        @if(!empty($opShiftGlobal))
                            @if(!empty($opShiftGlobal['over_24h']))
                                <div class="alert alert-warning py-2 small mb-2">
                                    <i class="fa fa-exclamation-triangle me-1"></i>
                                    <strong>This shift has been open more than 24 hours.</strong> Please close it when you can and open a new shift for a clear handover. You can keep working on this shift until someone closes it — this is a reminder only.
                                </div>
                            @endif
                            <p class="mb-1"><span class="badge bg-success">Open</span> Reference {{ $opShiftGlobal['reference_date'] ?? '—' }} · since {{ $opShiftGlobal['opened_at'] ?? '—' }} · ~{{ $opShiftGlobal['hours_open'] ?? '—' }} h</p>
                            @if(\App\Services\OperationalShiftService::userCanCloseGlobal(Auth::user()))
                                <button type="button" class="btn btn-warning btn-sm" wire:click="promptCloseOperationalShift({{ $opShiftGlobal['id'] }})" wire:confirm="Start closing the global operational shift? You will review a checklist and confirm in the next step.">Close global shift</button>
                            @endif
                        @else
                            <p class="text-muted mb-2">No global shift open.</p>
                            @if(\App\Services\OperationalShiftService::userCanOpenGlobal(Auth::user()))
                                <button type="button" class="btn btn-success btn-sm" wire:click="startOpenOperationalShift('global')" wire:confirm="Open a new global shift for all modules (POS, Front office, Store)?"><i class="fa fa-play me-1"></i>Open global shift</button>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="row g-3">
                        @if($showPosScope)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="small fw-bold text-uppercase text-muted">POS / Restaurant</h6>
                                    @if(!empty($opShiftPos))
                                        @if(!empty($opShiftPos['over_24h']))
                                            <div class="alert alert-warning py-2 small mb-2">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                <strong>Open more than 24 hours.</strong> Close when possible and open a new shift. You may continue using this shift until it is closed.
                                            </div>
                                        @endif
                                        <p class="mb-1"><span class="badge bg-success">Open</span> Ref. {{ $opShiftPos['reference_date'] ?? '—' }} · {{ $opShiftPos['opened_at'] ?? '—' }} · ~{{ $opShiftPos['hours_open'] ?? '—' }} h</p>
                                        @if(\App\Services\OperationalShiftService::userCanClosePos(Auth::user()))
                                            <button type="button" class="btn btn-warning btn-sm" wire:click="promptCloseOperationalShift({{ $opShiftPos['id'] }})" wire:confirm="Start closing the POS shift? You will review a checklist and confirm in the next step.">Close POS shift</button>
                                        @endif
                                    @else
                                        <p class="text-muted small mb-2">No POS shift open — strict mode blocks POS sales.</p>
                                        @if(\App\Services\OperationalShiftService::userCanOpenPos(Auth::user()))
                                            <button type="button" class="btn btn-success btn-sm" wire:click="startOpenOperationalShift('pos')" wire:confirm="Open a new POS / Restaurant shift?">Open POS shift</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if($showFrontOfficeScope)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="small fw-bold text-uppercase text-muted">Front office</h6>
                                    @if(!empty($opShiftFrontOffice))
                                        @if(!empty($opShiftFrontOffice['over_24h']))
                                            <div class="alert alert-warning py-2 small mb-2">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                <strong>Open more than 24 hours.</strong> Please close this shift and open a new one when you can. You can keep working from this shift until it is closed — this is a reminder only.
                                            </div>
                                        @endif
                                        <p class="mb-1"><span class="badge bg-success">Open</span> Ref. {{ $opShiftFrontOffice['reference_date'] ?? '—' }} · {{ $opShiftFrontOffice['opened_at'] ?? '—' }} · ~{{ $opShiftFrontOffice['hours_open'] ?? '—' }} h</p>
                                        @if(\App\Services\OperationalShiftService::userCanCloseFrontOffice(Auth::user()))
                                            <button type="button" class="btn btn-warning btn-sm" wire:click="promptCloseOperationalShift({{ $opShiftFrontOffice['id'] }})" wire:confirm="Start closing the Front office shift? You will review a checklist and confirm in the next step.">Close FO shift</button>
                                        @endif
                                    @else
                                        <p class="text-muted small mb-2">No Front office shift open.</p>
                                        @if(\App\Services\OperationalShiftService::userCanOpenFrontOffice(Auth::user()))
                                            <button type="button" class="btn btn-success btn-sm" wire:click="startOpenOperationalShift('front-office')" wire:confirm="Open a new Front office shift?">Open FO shift</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if($showStoreScope)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="small fw-bold text-uppercase text-muted">Store / Stock</h6>
                                    @if(!empty($opShiftStore))
                                        @if(!empty($opShiftStore['over_24h']))
                                            <div class="alert alert-warning py-2 small mb-2">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                <strong>Open more than 24 hours.</strong> Close when possible and open a new shift. You may continue until this shift is closed.
                                            </div>
                                        @endif
                                        <p class="mb-1"><span class="badge bg-success">Open</span> Ref. {{ $opShiftStore['reference_date'] ?? '—' }} · {{ $opShiftStore['opened_at'] ?? '—' }} · ~{{ $opShiftStore['hours_open'] ?? '—' }} h</p>
                                        @if(\App\Services\OperationalShiftService::userCanCloseStore(Auth::user()))
                                            <button type="button" class="btn btn-warning btn-sm" wire:click="promptCloseOperationalShift({{ $opShiftStore['id'] }})" wire:confirm="Start closing the Store shift? You will review a checklist and confirm in the next step.">Close Store shift</button>
                                        @endif
                                    @else
                                        <p class="text-muted small mb-2">No Store shift open.</p>
                                        @if(\App\Services\OperationalShiftService::userCanOpenStore(Auth::user()))
                                            <button type="button" class="btn btn-success btn-sm" wire:click="startOpenOperationalShift('store')" wire:confirm="Open a new Store / Stock shift?">Open Store shift</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <h6 class="mt-4 small fw-bold text-muted">Recent operational shifts</h6>
                <p class="text-muted small mb-2">Shifts opened in the last 21 days, plus <strong>any shift still open</strong> (even if started earlier). Duration is time since open for open shifts, or total length for closed shifts.</p>
                @if(count($operationalShiftHistory) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Area</th>
                                    <th>Status</th>
                                    <th>Ref. date</th>
                                    <th>Opened</th>
                                    <th>Closed</th>
                                    <th>Duration</th>
                                    <th>Opened by</th>
                                    <th>Closed by</th>
                                    <th>Close comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($operationalShiftHistory as $h)
                                    <tr class="{{ !empty($h['over_24h']) ? 'table-warning' : '' }}">
                                        <td><span class="badge bg-secondary">{{ $h['scope_label'] ?? $h['scope'] }}</span></td>
                                        <td>
                                            @if(($h['status'] ?? '') === 'open')
                                                <span class="badge bg-success">Open</span>
                                                @if(!empty($h['over_24h']))
                                                    <span class="badge bg-warning text-dark ms-1">24h+</span>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Closed</span>
                                            @endif
                                        </td>
                                        <td>{{ $h['reference_date'] }}</td>
                                        <td class="small">{{ $h['opened_at'] }}</td>
                                        <td class="small">{{ $h['closed_at'] ?? '—' }}</td>
                                        <td class="small text-nowrap">~{{ $h['hours_open'] ?? '—' }} h</td>
                                        <td class="small">{{ $h['opened_by'] }}</td>
                                        <td class="small">{{ ($h['status'] ?? '') === 'open' ? '—' : ($h['closed_by'] ?? '—') }}</td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($h['close_comment'] ?? '—', 80) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted small mb-0">No operational shifts recorded yet. When shifts are opened or closed, they will appear here.</p>
                @endif
            </div>
        </div>
    @else
        <!-- Legacy: business day + day shifts (before operational_shifts migration) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Business Day</h6>
            </div>
            <div class="card-body">
                @if($openBusinessDay)
                    @php
                        $rolloverDisplay = '03:00 AM';
                        if ($business_day_rollover_time) {
                            try {
                                $rolloverDisplay = \Carbon\Carbon::createFromFormat('H:i:s', $business_day_rollover_time)->format('g:i A');
                            } catch (\Exception $e) {
                                try {
                                    $rolloverDisplay = \Carbon\Carbon::createFromFormat('H:i', $business_day_rollover_time)->format('g:i A');
                                } catch (\Exception $e2) {}
                            }
                        }
                    @endphp
                    <p class="mb-2"><strong>Business day:</strong> {{ \Carbon\Carbon::parse($openBusinessDay->business_date)->format('d M Y') }} · Opened at {{ \App\Helpers\HotelTimeHelper::format($openBusinessDay->opened_at, 'H:i') }} · Day runs until <strong>{{ $rolloverDisplay }}</strong> next morning</p>
                    @if(Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules())
                        <button type="button" class="btn btn-warning" wire:click="closeBusinessDay" wire:confirm="Close the business day? No reopening allowed.">Close Business Day</button>
                    @endif
                @else
                    <p class="text-muted mb-2">No business day is open.</p>
                    @if(Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules())
                        <button type="button" class="btn btn-primary" wire:click="openBusinessDay">Open Business Day</button>
                    @endif
                @endif
            </div>
        </div>

        @if($openBusinessDay && $shift_mode !== 'NO_SHIFT')
            @if(count($dayShifts) > 0)
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">Shifts for {{ $openBusinessDay->business_date }}</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                @foreach($dayShifts as $ds)
                                    <tr>
                                        <td>{{ $ds['name'] }}</td>
                                        <td>{{ \App\Helpers\HotelTimeHelper::format($ds['start_at'], 'H:i') }}</td>
                                        <td>{{ \App\Helpers\HotelTimeHelper::format($ds['end_at'], 'H:i') }}</td>
                                        <td>
                                            @if($ds['status'] === 'OPEN')<span class="badge bg-success">Open</span>
                                            @elseif($ds['status'] === 'CLOSED')<span class="badge bg-secondary">Closed</span>
                                            @else<span class="badge bg-warning text-dark">Pending</span>@endif
                                        </td>
                                        <td>
                                            @if($ds['status'] === 'PENDING' && (Auth::user()->hasPermission('pos_open_shift') || Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules()))
                                                <button type="button" class="btn btn-sm btn-success" wire:click="openDayShift({{ $ds['id'] }})">Open</button>
                                            @elseif($ds['status'] === 'OPEN' && (Auth::user()->hasPermission('pos_close_shift') || Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules()))
                                                <button type="button" class="btn btn-sm btn-warning" wire:click="closeDayShift({{ $ds['id'] }})">Close</button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        @endif
    @endif

    <!-- Hotel Shift Configuration (managers only) -->
    @if($canEditShiftHotelConfig)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Shift Configuration</h6>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="saveHotelConfig">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Hotel timezone (business day &amp; POS use local time)</label>
                            <select class="form-select" wire:model="timezone" id="timezone">
                                @foreach(\App\Livewire\ShiftManagement::getTimezoneOptions() as $region => $zones)
                                    <optgroup label="{{ $region }}">
                                        @foreach($zones as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-muted">Set when the hotel is in another country or the server is elsewhere. All business day and POS times follow this timezone.</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="time" class="form-control" id="rolloverTime" wire:model="business_day_rollover_time" required>
                                <label for="rolloverTime">Business Day Rollover Time</label>
                                <small class="text-muted">Time when business day changes (default: 03:00 AM), in hotel local time</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="shiftsEnabled" wire:model="shifts_enabled">
                                <label class="form-check-label" for="shiftsEnabled">
                                    Enable Shifts (legacy)
                                </label>
                                <small class="text-muted d-block">If disabled, system will create SYSTEM_SHIFT_* automatically</small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Shift mode</label>
                            <select class="form-select" wire:model="shift_mode">
                                @if($useOperationalShifts)
                                <option value="STRICT_SHIFT">STRICT_SHIFT — Recommended: require manual operational shifts (POS / FO / Store per scope)</option>
                                <option value="OPTIONAL_SHIFT">OPTIONAL_SHIFT — Operational shifts still enforced when the table is enabled; legacy day-shift optional</option>
                                <option value="NO_SHIFT">NO_SHIFT — Disable operational shift enforcement (POS/FO/Store); legacy business-day only if used</option>
                                @else
                                <option value="NO_SHIFT">NO_SHIFT — No shifts; POS uses business day only</option>
                                <option value="OPTIONAL_SHIFT">OPTIONAL_SHIFT — Shifts optional</option>
                                <option value="STRICT_SHIFT">STRICT_SHIFT — POS requires an open shift</option>
                                @endif
                            </select>
                            @if($useOperationalShifts)
                                <small class="text-muted d-block mt-1">
                                    With operational shifts, <strong>STRICT_SHIFT</strong> or <strong>OPTIONAL_SHIFT</strong> keeps POS / Front office / Store gated on open shifts.
                                    <strong>NO_SHIFT</strong> turns that off entirely.
                                </small>
                            @endif
                        </div>
                        @if($useOperationalShifts)
                        <div class="col-md-12">
                            <label class="form-label">Operational shift scope</label>
                            <select class="form-select" wire:model="operational_shift_scope">
                                <option value="per_module">Per module (default) — POS, Front office, and Store each open/close independently</option>
                                <option value="global">Global — one shift for the whole hotel (all modules share the same open shift)</option>
                            </select>
                            <small class="text-muted">Change only when no operational shift is open. Default is per-module so each team can close at different times.</small>
                        </div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </form>
            </div>
        </div>
    @else
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Shift Configuration</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p><strong>Timezone:</strong> {{ $timezone }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Business Day Rollover Time:</strong> {{ $business_day_rollover_time }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Shifts Enabled:</strong> {{ $shifts_enabled ? 'Yes' : 'No' }}</p>
                    </div>
                    @if($useOperationalShifts)
                    <div class="col-md-12">
                        <p><strong>Operational shift scope:</strong> {{ $operational_shift_scope === 'global' ? 'Global (one shift for whole hotel)' : 'Per module (default)' }}</p>
                    </div>
                    @endif
                </div>
                <small class="text-muted">Super Admin and hotel managers (Director, GM, Manager) can modify shift configuration.</small>
            </div>
        </div>
    @endif

    <!-- Shift Templates (legacy day-shift flow only; hidden when using operational shifts) -->
    @if(!$useOperationalShifts && $shift_mode !== 'NO_SHIFT' && (Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules()))
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Shift Templates</h6>
                <button class="btn btn-sm btn-primary" wire:click="openTemplateForm"><i class="bi bi-plus-circle"></i> Add Template</button>
            </div>
            <div class="card-body">
                @if(count($shiftTemplates) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Name</th><th>Start</th><th>End</th><th>Order</th><th>Active</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach($shiftTemplates as $t)
                                    <tr>
                                        <td>{{ $t['name'] }}</td>
                                        <td>{{ is_string($t['start_time'] ?? null) ? substr($t['start_time'], 0, 5) : '—' }}</td>
                                        <td>{{ is_string($t['end_time'] ?? null) ? substr($t['end_time'], 0, 5) : '—' }}</td>
                                        <td>{{ $t['display_order'] ?? 0 }}</td>
                                        <td>{{ ($t['is_active'] ?? true) ? 'Yes' : 'No' }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-info" wire:click="openTemplateForm({{ $t['id'] }})"><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No shift templates. Add templates; they are created when you open a business day.</p>
                @endif
            </div>
        </div>
    @endif

    <!-- Legacy business-day shift UI (hidden when using operational_shifts table) -->
    @if(!$useOperationalShifts)
    <!-- Shifts List (legacy) - hidden when NO_SHIFT to avoid confusion -->
    @if($shift_mode === 'NO_SHIFT')
        <div class="card mb-4 border-info">
            <div class="card-body">
                <p class="mb-2 text-info">
                    <i class="fa fa-info-circle me-2"></i>
                    <strong>POS uses business day only.</strong> Shifts are not used. Any previous open shifts are closed automatically when you open a new business day.
                </p>
                @if(Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules())
                    <button type="button" class="btn btn-sm btn-outline-info" wire:click="closeStaleShifts">
                        Close any open shifts from previous days
                    </button>
                @endif
            </div>
        </div>
    @else
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Shifts (legacy)</h6>
                @if(Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules())
                    <button class="btn btn-sm btn-primary" wire:click="openShiftForm">
                        <i class="bi bi-plus-circle"></i> Add Shift
                    </button>
                @endif
            </div>
            <div class="card-body">
                @if(count($shifts) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shifts as $shift)
                                    <tr>
                                        <td>{{ $shift['name'] }}</td>
                                        <td><code>{{ $shift['code'] }}</code></td>
                                        <td>{{ $shift['start_time'] }}</td>
                                        <td>{{ $shift['end_time'] }}</td>
                                        <td>{{ $shift['order'] }}</td>
                                        <td>
                                            @if($shift['is_active'])
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules())
                                                <button class="btn btn-sm btn-info" wire:click="openShiftForm({{ $shift['id'] }})">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" wire:click="confirmDelete({{ $shift['id'] }})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">View Only</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted">No shifts configured. Click "Add Shift" to create one.</p>
                @endif
            </div>
        </div>
    @endif

    <!-- Shift Logs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Recent Shift Logs</h6>
            <button class="btn btn-sm btn-secondary" wire:click="loadShiftLogs">
                <i class="bi bi-clock-history"></i> View Logs
            </button>
        </div>
        @if($showShiftLogs && count($selectedShiftLogs) > 0)
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Business Date</th>
                                <th>Shift</th>
                                <th>Opened At</th>
                                <th>Closed At</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedShiftLogs as $log)
                                <tr>
                                    <td>{{ $log['business_date'] }}</td>
                                    <td>{{ $log['shift']['name'] ?? 'N/A' }}</td>
                                    <td>{{ \App\Helpers\HotelTimeHelper::format($log['opened_at'], 'Y-m-d H:i') }}</td>
                                    <td>{{ $log['closed_at'] ? \App\Helpers\HotelTimeHelper::format($log['closed_at'], 'Y-m-d H:i') : 'Open' }}</td>
                                    <td>
                                        @if($log['is_locked'])
                                            <span class="badge bg-danger">Locked</span>
                                        @else
                                            <span class="badge bg-success">Open</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$log['is_locked'])
                                            <button class="btn btn-sm btn-warning" wire:click="closeShiftLog({{ $log['id'] }})">
                                                Close
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
    @endif

    <!-- Shift Form Modal -->
    @if($showShiftForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingShiftId ? 'Edit Shift' : 'Add Shift' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeShiftForm"></button>
                    </div>
                    <form wire:submit.prevent="saveShift">
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
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model.defer="name" required>
                                <label for="name">Shift Name</label>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" wire:model.defer="code" required>
                                <label for="code">Shift Code (Unique)</label>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="time" class="form-control @error('start_time') is-invalid @enderror" id="start_time" wire:model.defer="start_time" required>
                                        <label for="start_time">Start Time</label>
                                        @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="time" class="form-control @error('end_time') is-invalid @enderror" id="end_time" wire:model.defer="end_time" required>
                                        <label for="end_time">End Time</label>
                                        @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" wire:model.defer="description" style="height: 100px"></textarea>
                                <label for="description">Description</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" wire:model.defer="order" min="0">
                                <label for="order">Display Order</label>
                                @error('order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" wire:model.defer="is_active">
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeShiftForm" wire:loading.attr="disabled">Cancel</button>
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

    <!-- Delete Confirmation Modal -->
    @if($showDeleteConfirmation)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" wire:click="$set('showDeleteConfirmation', false)"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this shift? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showDeleteConfirmation', false)">Cancel</button>
                        <button type="button" class="btn btn-danger" wire:click="deleteShift">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Close Day Shift Confirmation Modal -->
    @if($showCloseDayShiftConfirmation)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Close Shift</h5>
                        <button type="button" class="btn-close" wire:click="$set('showCloseDayShiftConfirmation', false)"></button>
                    </div>
                    <div class="modal-body">
                        <p>Close this shift? All POS sessions for this shift will be closed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="$set('showCloseDayShiftConfirmation', false)">Cancel</button>
                        <button type="button" class="btn btn-warning" wire:click="confirmCloseDayShift">Close Shift</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Shift Template Form Modal -->
    @if($showTemplateForm)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingTemplateId ? 'Edit' : 'Add' }} Shift Template</h5>
                        <button type="button" class="btn-close" wire:click="closeTemplateForm"></button>
                    </div>
                    <form wire:submit.prevent="saveTemplate">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="template_name" wire:model.defer="template_name" required>
                                <label for="template_name">Name</label>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="form-floating mb-3">
                                        <input type="time" class="form-control" id="template_start_time" wire:model.defer="template_start_time" required>
                                        <label for="template_start_time">Start</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating mb-3">
                                        <input type="time" class="form-control" id="template_end_time" wire:model.defer="template_end_time" required>
                                        <label for="template_end_time">End</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="number" min="0" class="form-control" id="template_display_order" wire:model.defer="template_display_order">
                                <label for="template_display_order">Display order</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="template_is_active" wire:model.defer="template_is_active">
                                <label class="form-check-label" for="template_is_active">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeTemplateForm">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showCloseOpModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1060;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Close operational shift</h5>
                        <button type="button" class="btn-close" wire:click="cancelCloseOperationalShift"></button>
                    </div>
                    <form wire:submit.prevent="confirmCloseOperationalShift">
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                Review today’s operations before closing this shift. Resolve or document unpaid orders, pending arrivals / in-house guests, and open requests if needed.
                            </p>
                            @if(!empty($closeChecklist))
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Unpaid / open invoices (today)</div>
                                            <div class="fw-semibold">
                                                {{ $closeChecklist['unpaid_orders_count'] ?? 0 }} invoice(s)
                                            </div>
                                            <div class="small text-muted">
                                                Total: {{ number_format($closeChecklist['unpaid_orders_total'] ?? 0, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Today’s arrivals / in-house</div>
                                            <div class="fw-semibold">
                                                {{ $closeChecklist['pending_reservations_count'] ?? 0 }} reservation(s)
                                            </div>
                                            <div class="small text-muted">
                                                Check arrivals, due check-ins and in-house balances in Front office reports.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Open shift open requests</div>
                                            <div class="fw-semibold">
                                                {{ $closeChecklist['open_requests_count'] ?? 0 }} request(s)
                                            </div>
                                            <div class="small text-muted">
                                                Resolve or leave a note if today’s requests are still pending.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <p class="text-muted small">Optional note for managers or supervisors (handover, incidents, exceptions). Leave blank if nothing to report.</p>
                            <div class="mb-3">
                                <label class="form-label">Close comment <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" rows="4" wire:model="close_op_comment" placeholder="e.g. All POS invoices settled; one issue escalated to night manager."></textarea>
                                @error('close_op_comment') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="cancelCloseOperationalShift">Cancel</button>
                            <button type="submit" class="btn btn-warning" wire:confirm="Close this operational shift now? Make sure POS invoices and Front office rules are satisfied.">Close shift</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showRejectRequestModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1070;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject shift open request</h5>
                        <button type="button" class="btn-close" wire:click="cancelRejectOpenRequest"></button>
                    </div>
                    <form wire:submit.prevent="confirmRejectOpenRequest">
                        <div class="modal-body">
                            <p class="small text-muted">The requester will not be notified automatically; consider contacting them.</p>
                            <div class="mb-3">
                                <label class="form-label">Reason <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" rows="3" wire:model="reject_request_note" placeholder="e.g. Opening after 2 PM — use morning shift first"></textarea>
                                @error('reject_request_note') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="cancelRejectOpenRequest">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Close Shift Confirmation Modal -->
    @if($showCloseConfirmation)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="z-index: 1051;">
                    <div class="modal-header">
                        <h5 class="modal-title">Close Shift</h5>
                        <button type="button" class="btn-close" wire:click="$set('showCloseConfirmation', false)"></button>
                    </div>
                    <form wire:submit.prevent="confirmCloseShiftLog">
                        <div class="modal-body">
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" id="closing_cash" wire:model="closing_cash" step="0.01" min="0">
                                <label for="closing_cash">Closing Cash Amount</label>
                            </div>
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="close_notes" wire:model="close_notes" style="height: 100px"></textarea>
                                <label for="close_notes">Notes</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="$set('showCloseConfirmation', false)">Cancel</button>
                            <button type="submit" class="btn btn-warning">Close Shift</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
