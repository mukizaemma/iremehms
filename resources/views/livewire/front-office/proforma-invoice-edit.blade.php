<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="{{ route('front-office.proforma-invoices') }}" class="small text-muted text-decoration-none"><i class="fa fa-arrow-left me-1"></i> All proformas</a>
            <h5 class="fw-bold mb-0 mt-1">{{ $proformaId ? 'Edit proforma' : 'New proforma' }} <code>{{ $proforma_number }}</code></h5>
            @if($proformaId)
                <span class="badge bg-secondary text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
            @endif
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ $defaultsSettingsUrl }}" class="btn btn-outline-secondary btn-sm">Default prices by type</a>
            @if($printUrl)
                <a href="{{ $printUrl }}?autoprint=1" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print me-1"></i> Print</a>
            @endif
        </div>
    </div>

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

    @if(!$canEdit && $proformaId)
        <div class="alert alert-info small">You can view this proforma. Only users with edit rights can change it.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-2 fw-semibold">Client & event</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Organization</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="client_organization" @disabled(!$canEdit)>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Contact name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="client_name" @disabled(!$canEdit)>
                            @error('client_name') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" class="form-control form-control-sm" wire:model.blur="client_email" @disabled(!$canEdit)>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Phone</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="client_phone" @disabled(!$canEdit)>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Event / package title</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="event_title" @disabled(!$canEdit)>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Service start</label>
                            <input type="date" class="form-control form-control-sm" wire:model.blur="service_start_date" @disabled(!$canEdit)>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Service end</label>
                            <input type="date" class="form-control form-control-sm" wire:model.blur="service_end_date" @disabled(!$canEdit)>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notes</label>
                            <textarea class="form-control form-control-sm" rows="2" wire:model.blur="notes" @disabled(!$canEdit)></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Payment terms & options</label>
                            <textarea class="form-control form-control-sm" rows="2" wire:model.blur="payment_terms" @disabled(!$canEdit)></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Line items</span>
                    @if($canEdit)
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addLine"><i class="fa fa-plus me-1"></i> Add line</button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:130px">Type</th>
                                    <th>Description</th>
                                    <th style="width:120px">Report column</th>
                                    <th style="width:64px">Qty</th>
                                    <th style="width:96px">Unit</th>
                                    <th style="width:56px">Disc %</th>
                                    <th style="width:88px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $index => $line)
                                    <tr wire:key="line-{{ $index }}">
                                        <td>
                                            <select class="form-select form-select-sm" wire:model.live="lines.{{ $index }}.line_type" @disabled(!$canEdit)>
                                                @foreach($lineTypes as $val => $lab)
                                                    <option value="{{ $val }}">{{ $lab }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.description" placeholder="Description" @disabled(!$canEdit)>
                                            @if(($line['line_type'] ?? '') === 'wellness')
                                                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                                    <select class="form-select form-select-sm" style="min-width:140px" wire:model.live="lines.{{ $index }}.wellness_service_id" @disabled(!$canEdit)>
                                                        <option value="">— Wellness service —</option>
                                                        @foreach($wellnessServices as $ws)
                                                            <option value="{{ $ws->id }}">{{ $ws->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select class="form-select form-select-sm" style="min-width:120px" wire:model.live="lines.{{ $index }}.wellness_pricing_mode" @disabled(!$canEdit)>
                                                        <option value="visit">Per visit</option>
                                                        <option value="daily">Per day</option>
                                                        <option value="monthly">Monthly</option>
                                                    </select>
                                                </div>
                                            @endif
                                            @error('lines.'.$index.'.description') <div class="text-danger small">{{ $message }}</div> @enderror
                                        </td>
                                        <td>
                                            @php $rb = (string) ($line['report_bucket_key'] ?? 'other'); @endphp
                                            <div class="small fw-medium">{{ $reportBucketOptions[$rb] ?? strtoupper($rb) }}</div>
                                            <code class="small text-muted">{{ $rb }}</code>
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.quantity" @disabled(!$canEdit)></td>
                                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.unit_price" @disabled(!$canEdit)></td>
                                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.discount_percent" @disabled(!$canEdit)></td>
                                        <td>
                                            @if($canEdit)
                                                <button type="button" class="btn btn-link text-success btn-sm p-0 me-2" wire:click="updateLine({{ $index }})" title="Update row"><i class="fa fa-check"></i></button>
                                                <button type="button" class="btn btn-link text-danger btn-sm p-0" wire:click="removeLine({{ $index }})" title="Remove"><i class="fa fa-times"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Currency</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="currency" style="width:88px" @disabled(!$canEdit)>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Extra discount</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="discount_amount" style="width:100px" @disabled(!$canEdit)>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Tax</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="tax_amount" style="width:100px" @disabled(!$canEdit)>
                        </div>
                        <div class="col text-end small text-muted">
                            Subtotal: <strong>{{ number_format($subtotalPreview, 2) }}</strong>
                            &nbsp;|&nbsp; Grand total: <strong>{{ number_format($grandPreview, 2) }} {{ $currency }}</strong>
                        </div>
                    </div>

                    @if($canEdit)
                        <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="saveDraft" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveDraft">Save draft</span>
                                <span wire:loading wire:target="saveDraft">Saving…</span>
                            </button>
                            @if($proformaId && in_array($status, ['draft', 'rejected'], true) && !$canVerify)
                                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="submitToManager" wire:loading.attr="disabled"
                                        wire:confirm="Submit this proforma to a manager for verification?">
                                    Submit to manager
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if($proformaId && $canVerify && $status === 'pending_manager')
                <div class="card border-0 shadow-sm mt-3 border-warning">
                    <div class="card-header bg-warning bg-opacity-10 py-2 fw-semibold">Manager verification</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Review the proforma, adjust lines if needed, then approve or return to staff.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-success btn-sm" wire:click="verifyByManager">Approve & verify</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="$set('showRejectForm', true)">Reject…</button>
                        </div>
                        @if($showRejectForm)
                            <div class="mt-2">
                                <label class="form-label small">Reason for rejection</label>
                                <textarea class="form-control form-control-sm" rows="2" wire:model.blur="manager_reject_note"></textarea>
                                @error('manager_reject_note') <div class="text-danger small">{{ $message }}</div> @enderror
                                <div class="mt-1">
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="rejectByManager">Confirm reject</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            @if($proformaId)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2 fw-semibold">Actions</div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @if($printUrl)
                            <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="fa fa-print me-1"></i> Print</a>
                        @endif
                        @if(in_array($status, ['verified', 'sent', 'accepted'], true))
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="sendEmail" wire:loading.attr="disabled"
                                    @if(trim($client_email) === '') disabled title="Set client email first" @endif>
                                <i class="fa fa-envelope me-1"></i> Send to email
                            </button>
                        @endif
                    </div>
                    <div class="card-body border-top pt-0 small text-muted">
                        Email uses the client address above. Verified proformas only.
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2 fw-semibold">After client confirmation</div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @if(in_array($status, ['verified', 'sent', 'accepted'], true))
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="markSent">Mark sent to client</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="markAccepted">Mark accepted</button>
                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="markInvoiced">Mark invoiced</button>
                        @endif
                        @if(! in_array($status, ['cancelled', 'invoiced'], true))
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="markCancelled" wire:confirm="Cancel this proforma?">Cancel</button>
                        @endif
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white py-2 fw-semibold">Record payment (after event)</div>
                    <div class="card-body">
                        <p class="small text-muted">Payments appear on the general report for the received date.</p>
                        <div class="mb-2">
                            <label class="form-label small">Amount</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="pay_amount">
                            @error('pay_amount') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Received at</label>
                            <input type="datetime-local" class="form-control form-control-sm" wire:model.blur="pay_received_at">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Method</label>
                            <select class="form-select form-select-sm" wire:model.blur="pay_method">
                                @foreach($paymentMethods as $val => $lab)
                                    <option value="{{ $val }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2 small text-muted">
                            <strong>Report column:</strong> {{ $autoPaymentBucketLabel }} <code>{{ $autoPaymentBucket }}</code><br>
                            Auto-selected from configured service type mapping.
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Reference</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="pay_reference">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Notes</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="pay_notes">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm w-100" wire:click="recordPayment">Record payment</button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-2 fw-semibold">Payments logged</div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush small">
                            @forelse($payments as $pay)
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-medium">{{ number_format((float) $pay->amount, 2) }}</div>
                                        <div class="text-muted">{{ $pay->received_at->format('Y-m-d H:i') }} · {{ $pay->payment_method }}</div>
                                        <div class="text-muted"><code>{{ $pay->report_bucket_key }}</code></div>
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">No payments yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @else
                <div class="alert alert-info small mb-0">
                    Use <strong>Save draft</strong> below the line items to store this proforma, then submit to a manager or print.
                </div>
            @endif
        </div>
    </div>
</div>
