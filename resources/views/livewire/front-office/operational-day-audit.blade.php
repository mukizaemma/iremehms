<div class="container py-4" style="max-width: 960px">
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h5 class="mb-1">Operational day audit</h5>
            <p class="text-muted small mb-0">Review guests, payments, POS, and communications before closing the business day.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="date" class="form-control form-control-sm" wire:model.live="date" style="max-width: 11rem">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="refreshAudit"><i class="fa fa-sync-alt"></i></button>
            <a href="{{ route('shift.management') }}" class="btn btn-outline-primary btn-sm">My shift</a>
        </div>
    </div>

    @php
        $s = $audit['summary'] ?? ['blockers' => 0, 'warnings' => 0, 'ok' => 0];
        $pt = $audit['payments_today'] ?? [];
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-danger border-opacity-50 h-100">
                <div class="card-body py-2 text-center">
                    <div class="fs-4 fw-bold text-danger">{{ $s['blockers'] ?? 0 }}</div>
                    <div class="small text-muted">Blockers</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-warning border-opacity-50 h-100">
                <div class="card-body py-2 text-center">
                    <div class="fs-4 fw-bold text-warning">{{ $s['warnings'] ?? 0 }}</div>
                    <div class="small text-muted">Warnings</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-success border-opacity-50 h-100">
                <div class="card-body py-2 text-center">
                    <div class="fs-4 fw-bold text-success">{{ $s['ok'] ?? 0 }}</div>
                    <div class="small text-muted">OK</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card bg-light h-100">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted">Audit date</div>
                    <div class="fw-semibold">{{ $audit['audit_date_label'] ?? $date }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-2">Cash reconciliation</div>
        <div class="card-body py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1">System cash (payments today)</label>
                    <div class="form-control form-control-sm bg-light">{{ $audit['currency'] ?? 'RWF' }} {{ number_format((float) ($pt['cash'] ?? 0), 2) }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Physical cash counted</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.blur="physicalCashCounted" placeholder="Count drawer">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">Variance</label>
                    @if($cashVariance !== null)
                        <div class="form-control form-control-sm bg-light {{ abs($cashVariance) > 0.02 ? 'text-danger fw-semibold' : 'text-success' }}">
                            {{ $audit['currency'] ?? 'RWF' }} {{ number_format($cashVariance, 2) }}
                        </div>
                    @else
                        <div class="form-control form-control-sm bg-light text-muted">Enter physical count</div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Audit notes (required if cash variance)</label>
                    <textarea class="form-control form-control-sm" rows="2" wire:model.blur="auditNotes" placeholder="Optional notes for management"></textarea>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                All payments received today: <strong>{{ $audit['currency'] ?? 'RWF' }} {{ number_format((float) ($pt['total'] ?? 0), 2) }}</strong>
                @if(($pt['advance_deposits'] ?? 0) > 0)
                    · Advances: {{ number_format((float) $pt['advance_deposits'], 2) }}
                @endif
            </p>
        </div>
    </div>

    @foreach($audit['sections'] ?? [] as $section)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-2">{{ $section['title'] }}</div>
            <ul class="list-group list-group-flush">
                @foreach($section['items'] ?? [] as $item)
                    @php
                        $sev = $item['severity'] ?? 'ok';
                        $badge = match($sev) {
                            'blocker' => 'danger',
                            'warning' => 'warning text-dark',
                            default => 'success',
                        };
                    @endphp
                    <li class="list-group-item py-2 d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <span class="badge bg-{{ $badge }} me-2">{{ ucfirst($sev) }}</span>
                            <span class="fw-medium">{{ $item['label'] }}</span>
                            @if(($item['count'] ?? 0) > 0 && !str_contains(strtolower($item['label']), 'advance'))
                                <span class="badge bg-secondary ms-1">{{ $item['count'] }}</span>
                            @endif
                            <div class="small text-muted mt-1">{{ $item['detail'] }}</div>
                        </div>
                        @if(!empty($item['href']))
                            <a href="{{ $item['href'] }}" class="btn btn-outline-primary btn-sm text-nowrap" wire:navigate>Review</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                @if($audit['business_day_closed'] ?? false)
                    <span class="badge bg-secondary">Business day already closed</span>
                @elseif(($s['blockers'] ?? 0) > 0)
                    <span class="text-danger small">Resolve blockers before closing the business day.</span>
                @else
                    <span class="text-success small">No blockers — you may close the business day when cash and shifts are verified.</span>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('front-office.daily-accommodation-report') }}?date_from={{ $audit['audit_date'] ?? $date }}&date_to={{ $audit['audit_date'] ?? $date }}" class="btn btn-outline-secondary btn-sm" wire:navigate>Rooms daily report</a>
                @if($canCloseBusinessDay)
                    <button type="button" class="btn btn-warning btn-sm" wire:click="closeBusinessDayFromAudit" wire:confirm="Close the business day for {{ $audit['audit_date_label'] ?? $date }}? This cannot be reopened.">
                        <i class="fa fa-lock me-1"></i>Close business day
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
