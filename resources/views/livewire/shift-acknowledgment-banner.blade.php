<div wire:poll.120s="loadRows">
    @if(count($scopeStatusRows) > 0)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header py-2 bg-light border-bottom">
                <span class="fw-semibold small text-uppercase text-muted">
                    <i class="fa fa-business-time me-1"></i> Operational shift status (today)
                </span>
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    @foreach($scopeStatusRows as $row)
                        <div class="col-12 col-md-6 col-xl-4" wire:key="scope-status-{{ $row['scope'] }}">
                            @if(!empty($row['is_open']))
                                <div class="border rounded p-2 h-100 bg-success bg-opacity-10 border-success">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="badge bg-success">Open</span>
                                            <span class="fw-semibold ms-1">{{ $row['scope_label'] }}</span>
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Ref. {{ $row['reference_date'] ?? '—' }}
                                        @if(!empty($row['opened_at']))
                                            · {{ $row['opened_at'] }}
                                        @endif
                                        @if(isset($row['hours_open']))
                                            · ~{{ $row['hours_open'] }} h
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="border rounded p-2 h-100 bg-warning bg-opacity-10 border-warning">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                        <div>
                                            <span class="badge bg-danger">No shift open</span>
                                            <span class="fw-semibold ms-1">{{ $row['scope_label'] }}</span>
                                        </div>
                                        @if(!empty($row['can_open_now']))
                                            <button type="button"
                                                    class="btn btn-sm btn-success"
                                                    wire:click="openOpenNowModal('{{ $row['scope'] }}')"
                                                    wire:loading.attr="disabled">
                                                Open shift now
                                            </button>
                                        @elseif(!empty($row['my_pending_request']))
                                            <span class="badge bg-secondary">Request sent — awaiting supervisor</span>
                                        @elseif(\App\Services\OperationalShiftOpenRequestService::isEnabled())
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    wire:click="openRequestModal('{{ $row['scope'] }}')"
                                                    wire:loading.attr="disabled">
                                                Request shift open
                                            </button>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Sales, stock entries, and some reports require an open shift for this area.
                                        @if(($row['pending_total'] ?? 0) > 0)
                                            <span class="d-block text-dark mt-1">
                                                <i class="fa fa-inbox me-1"></i>{{ (int) $row['pending_total'] }} open request(s) waiting for approval.
                                            </span>
                                            @if(!empty($row['can_resolve_requests']))
                                                <a href="{{ route('shift.management') }}" class="small">Review and confirm requests</a>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if(count($shiftRows) > 0)
        @foreach($shiftRows as $row)
            <div class="alert {{ ($row['is_long'] ?? false) ? 'alert-warning border-warning' : 'alert-primary border-primary' }} shadow-sm mb-3 d-flex flex-column flex-md-row align-items-start justify-content-between gap-3"
                 wire:key="shift-banner-{{ $row['id'] }}">
                <div class="flex-grow-1">
                    <div class="fw-semibold mb-1">
                        <i class="fa fa-clock me-1"></i>Open operational shift — {{ $row['scope_label'] ?? $row['scope'] }}
                    </div>
                    <div class="small text-muted mb-1">
                        Reference date: {{ $row['reference_date'] ?? '—' }}
                        · Opened: {{ $row['opened_at'] ?? '—' }}
                        · Duration: ~{{ $row['hours_open'] ?? '?' }} h
                    </div>
                    @if(!empty($row['is_long']))
                        <div class="small text-dark mt-1">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            This shift has been open over 24 hours. Choose <strong>Continue with this shift</strong> to keep selling and recording operations, or close it in Shift management.
                        </div>
                    @else
                        <div class="small text-dark mt-1">
                            Confirm you are continuing with this shift or close it when handover is complete. You can snooze this reminder for {{ \App\Services\OperationalShiftActionGate::PROMPT_SNOOZE_HOURS }} hours.
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button"
                            class="btn btn-sm btn-success"
                            wire:click="continueWithShift({{ (int) $row['id'] }})"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="continueWithShift">Continue with shift</span>
                        <span wire:loading wire:target="continueWithShift">…</span>
                    </button>
                    <a href="{{ route('shift.management') }}" class="btn btn-sm btn-outline-secondary">
                        Close shift
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            wire:click="remindInTenHours({{ (int) $row['id'] }})"
                            wire:loading.attr="disabled">
                        Remind in {{ \App\Services\OperationalShiftActionGate::PROMPT_SNOOZE_HOURS }}h
                    </button>
                </div>
            </div>
        @endforeach
    @endif

    @if($showRequestModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1070;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Request shift to be opened</h5>
                        <button type="button" class="btn-close" wire:click="cancelRequestModal"></button>
                    </div>
                    <form wire:submit.prevent="submitShiftOpenRequest">
                        <div class="modal-body">
                            <p class="small text-muted">
                                Supervisors and users with shift permissions will see this in <strong>Shift management</strong> and can open the shift for you.
                            </p>
                            <div class="mb-2">
                                <span class="fw-semibold">{{ \App\Services\OperationalShiftActionGate::labelForScope($requestScope) }}</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Note for supervisors <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" rows="3" wire:model="requestNote" placeholder="e.g. Need to start breakfast service / check-ins waiting"></textarea>
                                @error('requestNote') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="cancelRequestModal">Cancel</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="submitShiftOpenRequest">Send request</span>
                                <span wire:loading wire:target="submitShiftOpenRequest">Sending…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showOpenNowModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1070;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Open shift now</h5>
                        <button type="button" class="btn-close" wire:click="cancelOpenNowModal"></button>
                    </div>
                    <form wire:submit.prevent="openShiftNow">
                        <div class="modal-body">
                            <div class="small text-muted mb-2">
                                You can open this shift directly without sending a request.
                            </div>
                            <div class="mb-2">
                                <span class="fw-semibold">{{ \App\Services\OperationalShiftActionGate::labelForScope($openNowScope) }}</span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Open note <span class="text-muted">(optional)</span></label>
                                <textarea class="form-control" rows="3" wire:model="openNowNote" placeholder="e.g. Shift opened after staff handover confirmation."></textarea>
                                @error('openNowNote') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="cancelOpenNowModal">Cancel</button>
                            <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="openShiftNow">Open shift</span>
                                <span wire:loading wire:target="openShiftNow">Opening…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
