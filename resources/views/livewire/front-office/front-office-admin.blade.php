<div class="frontoffice-admin">
    @if($editingReservation)
    {{-- Edit Stay / Folio Operations view --}}
    <div class="edit-stay-view">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
        <div class="mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeEditStay"><i class="fa fa-arrow-left me-1"></i> Back to calendar</button>
        </div>
        {{-- Header: Guest & stay overview --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa fa-bed text-primary"></i>
                        <span class="fw-semibold text-primary">{{ $editingReservation['guest_name'] ?? '—' }}</span>
                        <span class="badge bg-primary">1</span>
                        <span class="badge bg-secondary">0</span>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 small">
                        <span><strong>Arrival:</strong> {{ $editingReservation['arrival_datetime'] ?? '—' }} <i class="fa fa-pencil-alt text-muted ms-1"></i></span>
                        <span><strong>Departure:</strong> {{ $editingReservation['departure_datetime'] ?? '—' }} <i class="fa fa-pencil-alt text-muted ms-1"></i></span>
                        @php
                            $from = \Carbon\Carbon::parse($editingReservation['from'] ?? now());
                            $to = \Carbon\Carbon::parse($editingReservation['to'] ?? now());
                            $nights = $from->diffInDays($to);
                        @endphp
                        <span><strong>Nights:</strong> {{ $nights }}</span>
                        <span><strong>Room:</strong> {{ $editingReservation['room_number'] ?? '—' }} / {{ $editingReservation['room_type'] ?? '—' }}</span>
                        <span><strong>Res. No.:</strong> {{ $editingReservation['reservation_number'] ?? '—' }}</span>
                        <span class="badge bg-{{ $editingReservation['status_badge'] ?? 'success' }}">{{ $editingReservation['status'] ?? 'Reserved' }}</span>
                        @php
                            $todayEdit = \App\Models\Hotel::getTodayForHotel();
                            $fromEdit = $editingReservation['from'] ?? null;
                            $canConfirmCheckIn = isset($editingReservation['reservation_id'])
                                && ($editingReservation['status_raw'] ?? '') === 'confirmed'
                                && $fromEdit !== null
                                && $fromEdit <= $todayEdit;
                        @endphp
                        @if($canConfirmCheckIn)
                            <button type="button" class="btn btn-success btn-sm ms-2" wire:click="confirmCheckInAndClose">
                                <i class="fa fa-sign-in-alt me-1"></i> Confirm check-in
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'folio_operations' ? 'active' : '' }}" wire:click="setEditStayTab('folio_operations')">Folio Operations</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'booking_details' ? 'active' : '' }}" wire:click="setEditStayTab('booking_details')">Booking Details</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'guest_details' ? 'active' : '' }}" wire:click="setEditStayTab('guest_details')">Guest Details</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'room_charges' ? 'active' : '' }}" wire:click="setEditStayTab('room_charges')">Room Charges</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'credit_card' ? 'active' : '' }}" wire:click="setEditStayTab('credit_card')">Credit Card</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $editStayTab === 'audit_trail' ? 'active' : '' }}" wire:click="setEditStayTab('audit_trail')">Audit Trail</button>
            </li>
        </ul>
        {{-- Folio Operations content (when tab active) --}}
        @if($editStayTab === 'folio_operations')
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light"><strong>Room / Folio</strong></div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">{{ $editingReservation['room_type'] ?? '—' }}</div>
                        <div class="list-group-item list-group-item-primary d-flex justify-content-between align-items-center">
                            {{ $editingReservation['room_number'] ?? '—' }}
                            <i class="fa fa-chevron-up small"></i>
                        </div>
                        <div class="list-group-item ps-4">
                            <i class="fa fa-chevron-right small me-1"></i> {{ $editingReservation['reservation_number'] ?? '' }} - {{ $editingReservation['guest_name'] ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-primary btn-sm">Print/Send</button>
                                <span class="small"><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#fd7e14"></span> Unposted</span>
                                <span class="small"><span class="d-inline-block rounded me-1" style="width:12px;height:12px;background:#0d6efd"></span> Posted</span>
                                <button type="button" class="btn btn-link btn-sm p-0"><i class="fa fa-list"></i></button>
                                <button type="button" class="btn btn-link btn-sm p-0"><i class="fa fa-sync-alt"></i></button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i></button>
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openAddPaymentModal">Add Payment</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openAddChargeModal">Add Charges</button>
                            <button type="button" class="btn btn-outline-primary btn-sm">Apply Discount</button>
                            <button type="button" class="btn btn-outline-primary btn-sm">Folio Operations</button>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">More</button>
                                <ul class="dropdown-menu">
                                    <li><button type="button" class="dropdown-item" wire:click="moreAdjustment">Adjustment</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreRoomCharges">Room Charges</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreTransfer">Transfer</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreSplitFolio">Split Folio</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreUploadFiles">Upload files</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreMealPlan">Meal Plan</button></li>
                                    <li><button type="button" class="dropdown-item" wire:click="moreInclusion">Inclusion</button></li>
                                </ul>
                            </div>
                        </div>
                        @php $totals = $this->getFolioTotals(); $transactions = $this->getFolioTransactions(); $payments = $folioPayments ?? []; $charges = $folioCharges ?? []; @endphp
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:30px"><input type="checkbox" class="form-check-input"></th>
                                        <th>Day</th>
                                        <th>Ref No.</th>
                                        <th>Particulars</th>
                                        <th>Description</th>
                                        <th>User</th>
                                        <th class="text-end">Amount</th>
                                        <th style="width:100px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $t)
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input"></td>
                                        <td>{{ $t['day'] }}</td>
                                        <td>{{ $t['ref_no'] }}</td>
                                        <td>{{ $t['particulars'] }}</td>
                                        <td>{{ $t['description'] }}</td>
                                        <td>{{ $t['user'] }}</td>
                                        <td class="text-end">{{ $t['amount'] }}</td>
                                        <td></td>
                                    </tr>
                                    @endforeach
                                    @foreach($charges as $c)
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input"></td>
                                        <td>{{ $c['day'] }}</td>
                                        <td>{{ $c['ref_no'] }}</td>
                                        <td>{{ $c['particulars'] }}</td>
                                        <td>{{ $c['description'] }}{!! isset($c['tax_inclusive']) && $c['tax_inclusive'] ? ' <span class="badge bg-secondary">Tax incl.</span>' : '' !!}</td>
                                        <td>{{ $c['user'] }}</td>
                                        <td class="text-end">{{ $c['amount'] }}</td>
                                        <td></td>
                                    </tr>
                                    @endforeach
                                    @foreach($payments as $p)
                                    <tr class="table-success">
                                        <td><input type="checkbox" class="form-check-input"></td>
                                        <td>{{ $p['day'] }}</td>
                                        <td>{{ $p['ref_no'] }}</td>
                                        <td>{{ $p['particulars'] }}</td>
                                        <td>{{ ($p['method'] ?? '—') }} · <span class="fw-semibold">{{ $p['settlement_status'] ?? '—' }}</span></td>
                                        <td>{{ $p['user'] }}</td>
                                        <td class="text-end">-{{ $p['amount'] }}</td>
                                        <td>
                                            <button type="button" class="btn btn-link btn-sm p-0 me-1" wire:click="openEditPaymentModal('{{ $p['id'] }}')" title="Edit">Edit</button>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="openVoidModal('{{ $p['id'] }}')" title="Void">Void</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-center text-muted small my-2">End Of Data</p>
                        <div class="border-top pt-2 mt-2 d-flex justify-content-between">
                            <span><strong>Total</strong></span>
                            <span><strong>{{ $totals['currency'] }} {{ $totals['total'] }}</strong></span>
                        </div>
                        <div class="d-flex justify-content-between text-danger">
                            <span><strong>Balance</strong></span>
                            <span><strong>{{ $totals['currency'] }} {{ $totals['balance'] }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif($editStayTab === 'audit_trail')
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <h6 class="mb-0">Audit trail — this reservation</h6>
                        <p class="text-muted small mb-0">Check-in/out, payments, voids, charges, and cancellation requests. Each row shows who acted and when.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="loadAuditTrail"><i class="fa fa-sync-alt me-1"></i>Refresh</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">When</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th class="small">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditTrailRows as $row)
                            <tr>
                                <td class="text-nowrap small">{{ $row['at'] }}</td>
                                <td>{{ $row['user'] }}</td>
                                <td><code class="small">{{ $row['action'] }}</code></td>
                                <td class="small">{{ $row['description'] }}</td>
                                <td class="small text-muted">{{ $row['ip'] }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">No activity logged yet for this stay.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="text-muted mb-0">Content for {{ $editStayTab }} tab will be implemented here.</p>
            </div>
        </div>
        @endif

        {{-- Add Payment modal --}}
        @if($showAddPaymentModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content frontoffice-modal">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingPaymentId ? 'Edit Payment' : 'Add Payment' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeAddPaymentModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="submitAddPayment">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="text" class="form-control" wire:model="payment_date" placeholder="dd/mm/yyyy">
                                    @error('payment_date') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Folio</label>
                                    <input type="text" class="form-control bg-light" wire:model="payment_folio_display" readonly>
                                </div>
                                <div class="col-12">
                                    @php
                                        $balanceDue = (float) ($paymentCheckoutSummary['folio']['balance'] ?? 0);
                                        $totalDue = (float) ($paymentCheckoutSummary['folio']['total'] ?? 0);
                                        $paymentInvoices = $paymentCheckoutSummary['invoices'] ?? [];
                                    @endphp
                                    <div class="alert alert-info mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Balance due</span>
                                            <strong>{{ $paymentCheckoutSummary['folio']['currency'] ?? \App\Models\Hotel::getHotel()->currency ?? 'RWF' }} {{ number_format($balanceDue, 2, '.', '') }}</strong>
                                        </div>
                                        <small class="text-muted">Total: {{ number_format($totalDue, 2, '.', '') }}. You can record a partial payment.</small>
                                    </div>

                                    @if(count($paymentInvoices) > 0)
                                        <div class="mb-2">
                                            <div class="fw-semibold mb-1">Linked POS invoices</div>
                                            <div class="list-group">
                                                @foreach($paymentInvoices as $inv)
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-medium">{{ $inv['invoice_number'] }}</div>
                                                            <small class="text-muted">{{ $inv['payment_label'] }}</small>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-medium">{{ number_format((float)$inv['total_amount'], 2, '.', '') }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="mb-1">
                                        <div class="fw-semibold mb-1">Recorded payments (this reservation)</div>
                                        @if(count($folioPayments ?? []) > 0)
                                            <div class="list-group">
                                                @foreach($folioPayments as $p)
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-medium">{{ $p['ref_no'] ?? '—' }}</div>
                                                            <small class="text-muted">{{ $p['day'] ?? '' }}</small>
                                                            <div class="small text-muted">
                                                                <span class="fw-semibold">{{ $p['payment_display'] ?? (($p['method'] ?? '—') . ' · ' . ($p['settlement_status'] ?? '—')) }}</span>
                                                                @if(!empty($p['is_debt_settlement']))
                                                                    <span class="badge bg-warning text-dark ms-1">Debt settlement</span>
                                                                @endif
                                                                @if(!empty($p['revenue_attribution_date']))
                                                                    <span class="small d-block text-muted">Sales date: {{ $p['revenue_attribution_date'] }}</span>
                                                                @endif
                                                                <br>
                                                                Received by: {{ $p['user'] ?? '—' }}
                                                                · Balance: {{ $p['currency'] ?? \App\Models\Hotel::getHotel()->currency ?? 'RWF' }} {{ number_format((float) ($p['balance_after'] ?? 0), 2, '.', '') }}
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-medium">{{ $p['currency'] ?? ($paymentCheckoutSummary['folio']['currency'] ?? 'RWF') }} {{ number_format((float)($p['amount'] ?? 0), 2, '.', '') }}</div>
                                                            <div class="mt-2">
                                                                <a href="{{ route('front-office.reservation-payment-receipt', ['payment' => $p['id']]) }}"
                                                                   target="_blank"
                                                                   class="btn btn-sm btn-outline-secondary">
                                                                    Receipt
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <small class="text-muted">No recorded payments yet.</small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rec/Vou #</label>
                                    <input type="text" class="form-control" wire:model="payment_rec_vou_no" placeholder="New">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Payment type</label>
                                    <select class="form-select" wire:model.live="payment_unified">
                                        @foreach(\App\Support\PaymentCatalog::unifiedAccommodationOptions() as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_unified') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                @if($payment_unified === \App\Support\PaymentCatalog::METHOD_CASH)
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="folio_cash_later" wire:model="payment_cash_submit_later">
                                            <label class="form-check-label" for="folio_cash_later">Cash: submit at shift end (records as pending)</label>
                                        </div>
                                    </div>
                                @endif
                                @if(\App\Support\PaymentCatalog::unifiedChoiceRequiresClientDetails($payment_unified ?? ''))
                                    <div class="col-12">
                                        <label class="form-label">Client / account details <span class="text-danger">*</span></label>
                                        <textarea class="form-control" wire:model="payment_client_reference" rows="2" placeholder="Guest name, phone, company, or account reference"></textarea>
                                        @error('payment_client_reference') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="folio_debt_settle" wire:model.live="payment_is_debt_settlement">
                                        <label class="form-check-label" for="folio_debt_settle">Confirming outstanding balance (debt) payment</label>
                                    </div>
                                </div>
                                @if($payment_is_debt_settlement)
                                    <div class="col-md-6">
                                        <label class="form-label">Sales / revenue date for reports <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" wire:model="payment_revenue_attribution_date">
                                        @error('payment_revenue_attribution_date') <span class="text-danger small">{{ $message }}</span> @enderror
                                        <div class="form-text small">Counted in rooms sales on this date.</div>
                                    </div>
                                @endif
                                <div class="col-md-6">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" wire:model="payment_currency">
                                        <option value="INR">INR (Rs)</option>
                                        <option value="USD">USD ($)</option>
                                        <option value="RWF">RWF</option>
                                        <option value="EUR">EUR (€)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Amount</label>
                                    <input type="number" class="form-control" wire:model="payment_amount" step="0.01" min="0" placeholder="0.00">
                                    @error('payment_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Comment</label>
                                    <textarea class="form-control" wire:model="payment_comment" rows="3" placeholder="Optional comment"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            @if(!empty($editingReservation['reservation_id'] ?? null))
                                <a href="{{ route('front-office.reservation-payment-receipt.preview', ['reservation_id' => $editingReservation['reservation_id']]) }}?payment_amount={{ urlencode((string) ($payment_amount ?? '0')) }}"
                                   target="_blank"
                                   class="btn btn-outline-primary">
                                    Preview receipt
                                </a>
                            @endif
                            <button type="button" class="btn btn-secondary" wire:click="closeAddPaymentModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                @php
                                    $balanceNow = (float) ($paymentCheckoutSummary['folio']['balance'] ?? 0);
                                    $amountNow = (float) ($payment_amount ?? 0);
                                @endphp
                                {{ ($balanceNow > 0 && $amountNow < $balanceNow) ? 'Confirm Partial Payment' : 'Confirm Payment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Add Charge modal --}}
        @if($showAddChargeModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content frontoffice-modal">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Charge</h5>
                        <button type="button" class="btn-close" wire:click="closeAddChargeModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="submitAddCharge">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="text" class="form-control" wire:model="charge_date" placeholder="dd/mm/yyyy">
                                    @error('charge_date') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Folio</label>
                                    <input type="text" class="form-control bg-light" wire:model="charge_folio_display" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rec/Vou #</label>
                                    <input type="text" class="form-control" wire:model="charge_rec_vou_no" placeholder="New">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">When to charge</label>
                                    <select class="form-select" wire:model="charge_apply_when">
                                        @foreach(\App\Livewire\FrontOffice\FrontOfficeAdmin::CHARGE_APPLY_WHEN_OPTIONS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Charge</label>
                                    <select class="form-select" wire:model.live="charge_additional_charge_id">
                                        <option value="">— Select extra charge —</option>
                                        @foreach($additionalCharges ?? [] as $ac)
                                            <option value="{{ $ac->id }}">{{ $ac->name }} — {{ $ac->default_amount !== null ? number_format((float)$ac->default_amount, 2) : '0.00' }} {{ \App\Models\Hotel::getHotel()->currency ?? 'RWF' }}</option>
                                        @endforeach
                                    </select>
                                    @if(count($additionalCharges ?? []) === 0)
                                        <small class="text-muted">Add extra charges in Backend → Additional charges first.</small>
                                    @endif
                                    @error('charge_additional_charge_id') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Charge Rule</label>
                                    <select class="form-select" wire:model="charge_rule">
                                        <option value="">-Select-</option>
                                        @foreach(\App\Models\AdditionalCharge::CHARGE_RULES as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="chargeTaxInclusive" wire:model="charge_tax_inclusive">
                                        <label class="form-check-label" for="chargeTaxInclusive">Tax Inclusive</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="chargeAddAsInclusion" wire:model="charge_add_as_inclusion">
                                        <label class="form-check-label" for="chargeAddAsInclusion">Add as Inclusion</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Qty</label>
                                    <input type="number" class="form-control" wire:model="charge_qty" min="1" step="1">
                                    @error('charge_qty') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Amount <span class="text-muted small">(editable; default from charge)</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ \App\Models\Hotel::getHotel()->currency ?? 'RWF' }}</span>
                                        <input type="number" class="form-control" wire:model="charge_amount" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    @error('charge_amount') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Comment</label>
                                    <textarea class="form-control" wire:model="charge_comment" rows="2" placeholder="Optional"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeAddChargeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Void payment modal --}}
        @if($showVoidModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content frontoffice-modal">
                    <div class="modal-header">
                        <h5 class="modal-title">Void</h5>
                        <button type="button" class="btn-close" wire:click="closeVoidModal" aria-label="Close"></button>
                    </div>
                    <form wire:submit.prevent="submitVoid">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" wire:model="voidReason" placeholder="Enter reason for void">
                                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                                </div>
                                @error('voidReason') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="small text-muted mb-2">Suggestions:</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(\App\Livewire\FrontOffice\FrontOfficeAdmin::VOID_REASON_SUGGESTIONS as $suggestion)
                                    <button type="button" class="btn btn-sm {{ $voidReason === $suggestion ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="selectVoidReason('{{ $suggestion }}')">{{ $suggestion }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeVoidModal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Void</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
    @else
    {{-- Top bar: Quick Search + actions (matches screenshot) --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 text-dark">Frontoffice Admin</h5>
            <a href="{{ route('front-office.add-reservation') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i> Add Reservation</a>
            <div class="input-group ms-3" style="max-width: 220px;">
                <input type="text" class="form-control form-control-sm" placeholder="Quick Search" aria-label="Quick Search">
                <button class="btn btn-primary btn-sm" type="button"><i class="fa fa-search"></i></button>
            </div>
            <button class="btn btn-link btn-sm text-secondary p-0" type="button" title="Info"><i class="fa fa-info-circle"></i></button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">{{ \App\Models\Hotel::getHotel()->name }}</span>
            <span class="badge bg-light text-dark">ID</span>
        </div>
    </div>

    {{-- Date range bar --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 140px;">
                        <input type="date" class="form-control" wire:model.live="view_start_date" title="Start date for room view">
                        <span class="input-group-text"><i class="fa fa-calendar-alt"></i></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="previousPeriod" title="Previous period"><i class="fa fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="nextPeriod" title="Next period"><i class="fa fa-chevron-right"></i></button>
                    <div class="d-flex align-items-center gap-1 ms-1">
                        <span class="small text-muted">View:</span>
                        <button type="button" class="btn btn-sm {{ (int) $room_view_days === 7 ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewDays(7)">7 days</button>
                        <button type="button" class="btn btn-sm {{ (int) $room_view_days === 15 ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewDays(15)">15 days</button>
                        <button type="button" class="btn btn-sm {{ (int) $room_view_days === 30 ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewDays(30)">30 days</button>
                    </div>
                </div>
                <a href="{{ route('front-office.add-reservation') }}" class="btn btn-sm btn-primary"><i class="fa fa-bed"></i> <i class="fa fa-plus small"></i> Add Reservation</a>
            </div>
        </div>
    </div>

    {{-- Status filter: click to filter; table/cards update to show only matching rooms --}}
    @php
        $statusLabels = [
            'all' => 'All',
            'vacant' => 'Vacant',
            'occupied' => 'In-house',
            'reserved' => 'Reserved / Future',
            'due_out' => "Today's departure",
            'due_in' => "Today's arrival",
            'recent_bookings' => 'Recent bookings',
            'blocked' => 'Blocked',
            'dirty' => 'Dirty',
            'no_show' => 'No Show',
        ];
        $filteredCount = $this->getFilteredRoomsCount();
    @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
        <span class="small text-muted me-1">Status:</span>
        @foreach(['all' => ['All', 'secondary'], 'vacant' => ['Vacant', 'success'], 'occupied' => ['In-house', 'primary'], 'due_in' => ["Today's arrival", 'info'], 'due_out' => ["Today's departure", 'warning'], 'reserved' => ['Reserved / Future', 'info'], 'recent_bookings' => ['Recent bookings', 'secondary'], 'blocked' => ['Blocked', 'dark'], 'dirty' => ['Dirty', 'secondary'], 'no_show' => ['No Show', 'danger']] as $key => $labelColor)
            @php
                $label = $labelColor[0];
                $color = $labelColor[1];
                $count = $counts[$key] ?? 0;
                $active = $statusFilter === $key;
            @endphp
            <button type="button"
                    class="btn btn-sm {{ $active ? 'btn-' . $color : 'btn-outline-' . $color }} rounded-pill px-3 py-1 d-inline-flex align-items-center"
                    wire:click="setStatusFilter('{{ $key }}')"
                    @if($key === 'reserved') title="Room has a booking in the visible date range (booked)" @endif>
                {{ $label }} <span class="badge {{ $active ? 'bg-light text-body' : 'bg-' . $color }} ms-1">{{ $count }}</span>
            </button>
        @endforeach
    </div>
    <p class="small text-muted mb-2">
        <strong>Reserved / Future</strong> = room has at least one confirmed or in-house booking in this period (not cancelled/checked-out).
        <strong>In-house</strong> = guest actually checked in today (status checked-in and within stay dates).
        <strong>Today's arrival</strong> = confirmed bookings arriving today (not yet checked in).
        <strong>Today's departure</strong> = in-house stays checking out today.
    </p>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span class="small text-muted">
            @if($statusFilter === 'all')
                Showing all {{ $filteredCount }} rooms
            @else
                Showing {{ $filteredCount }} room(s) — {{ $statusLabels[$statusFilter] ?? $statusFilter }}
            @endif
        </span>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">View:</span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn {{ $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewMode('grid')"><i class="fa fa-th-list me-1"></i>Grid</button>
                <button type="button" class="btn {{ $viewMode === 'cards' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="setViewMode('cards')"><i class="fa fa-th-large me-1"></i>Cards</button>
            </div>
        </div>
    </div>

    @if($viewMode === 'cards')
    {{-- Card view: one card per room (filtered by status + type), clickable for operations --}}
    <div class="row g-3 mb-4" wire:key="cards-{{ $statusFilter }}-{{ $roomTypeFilter }}">
        @foreach($this->getRoomsForCurrentFilter() as $room)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0 fw-semibold">{{ $room['label'] }}</h6>
                            <span class="badge bg-{{ $room['status'] === 'occupied' ? 'primary' : ($room['status'] === 'vacant' ? 'success' : ($room['status'] === 'due_out' ? 'warning' : ($room['status'] === 'due_in' ? 'info' : ($room['status'] === 'no_show' ? 'danger' : 'info')))) }} small text-capitalize">{{ str_replace('_', ' ', $room['status']) }}</span>
                        </div>
                        @if($room['room_type'])
                            <p class="small text-muted mb-2">{{ $room['room_type'] }}</p>
                        @endif
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectRoom('{{ $room['id'] }}')"><i class="fa fa-eye me-1"></i>View</button>
                            <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $room['id']]) }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-plus me-1"></i>Add reservation</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if($filteredCount === 0)
        <p class="text-muted small mb-0">No rooms match the current filter.</p>
    @endif
    @else
    {{-- Grid view: room list is in the table rows; first column shows Room + Type clearly --}}
    <div class="card border-0 shadow-sm overflow-hidden" wire:key="grid-{{ $statusFilter }}-{{ $roomTypeFilter }}">
        <div class="table-responsive">
            <table class="table table-bordered mb-0 frontoffice-grid align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="room-type-col text-nowrap ps-3">
                            <label class="form-label small mb-0 text-muted d-block">Room / Type</label>
                            <select class="form-select form-select-sm" wire:model.live="roomTypeFilter" style="max-width: 180px;">
                                <option value="">All types</option>
                                @foreach($roomTypes as $slug => $rt)
                                    <option value="{{ $slug }}">{{ $rt['name'] }}</option>
                                @endforeach
                            </select>
                        </th>
                        @foreach($dates as $date)
                            <th class="date-col text-center py-2 {{ $this->isWeekend($date) ? 'bg-weekend' : '' }}">
                                <div class="small fw-semibold">{{ $this->getDateLabel($date) }}</div>
                                <div class="small text-muted">{{ $capacityByDate[$date] ?? '—' }}</div>
                                <div class="small text-muted">{{ $rateByDate[$date] ?? '—' }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($roomTypes as $slug => $roomType)
                        @if($roomTypeFilter && $roomTypeFilter !== $slug)
                            @continue
                        @endif
                        @foreach($roomType['beds'] as $bed)
                            @if(!$this->bedMatchesStatusFilter($bed['id']))
                                @continue
                            @endif
                            <tr class="room-row">
                                <td class="room-type-col ps-3">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="flex-grow-1 min-w-0">
                                            <button type="button"
                                                    class="btn btn-link btn-sm p-0 text-start text-dark text-decoration-none fw-medium d-block"
                                                    wire:click="selectRoom('{{ $bed['id'] }}')"
                                                    title="View room details and bookings for this room">
                                                {{ $bed['label'] }}
                                            </button>
                                            <div class="small text-muted text-truncate" title="{{ $roomType['name'] ?? '' }}">{{ $roomType['name'] ?? '—' }}</div>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" title="View room"
                                                    wire:click="selectRoom('{{ $bed['id'] }}')">
                                                <i class="fa fa-bed fa-fw small"></i>
                                            </button>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-muted" title="View housekeeping / room status"
                                                    wire:click="selectRoom('{{ $bed['id'] }}')">
                                                <i class="fa fa-broom fa-fw small"></i>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                @php
                                    $col = 0;
                                    $dateIndex = 0;
                                @endphp
                                @while($dateIndex < count($dates))
                                    @php
                                        $date = $dates[$dateIndex];
                                        $booking = $this->getBookingFor($bed['id'], $date);
                                        // Treat as start if the stay starts on this date OR
                                        // if the stay started before the visible window and this is the first visible day.
                                        $isStart = $booking && ($booking['from'] === $date || ($dateIndex === 0 && $booking['from'] < $date));
                                        $span = $isStart ? $this->getBookingSpan($bed['id'], $date) : 1;
                                    @endphp
                                    @if($isStart && $span > 0)
                                        <td class="booking-cell align-middle {{ $this->isWeekend($date) ? 'bg-weekend' : '' }}" colspan="{{ $span }}">
                                            <div class="booking-bar d-flex align-items-center justify-content-between px-2 py-1 cursor-pointer" role="button" wire:click="selectBooking('{{ $bed['id'] }}', '{{ $booking['from'] }}')">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-white text-start guest-name small fw-medium text-truncate" title="View reservation details for {{ $booking['guest_name'] }}">
                                                    {{ $booking['guest_name'] }}
                                                </button>
                                                @if(!empty($booking['is_paid']))
                                                    <i class="fa fa-dollar-sign small text-success"></i>
                                                @endif
                                            </div>
                                        </td>
                                        @php $dateIndex += $span; @endphp
                                    @else
                                        <td class="{{ $this->isWeekend($date) ? 'bg-weekend' : '' }}"></td>
                                        @php $dateIndex++; @endphp
                                    @endif
                                @endwhile
                            </tr>
                        @endforeach
                    @endforeach
                    {{-- Unassigned reservations (no room unit linked) --}}
                    @php $unassignedBookings = $statusFilter === 'all' ? $this->getUnassignedBookingsForGrid() : []; @endphp
                    @if(count($unassignedBookings) > 0)
                        @foreach($unassignedBookings as $ub)
                            <tr class="room-row table-warning">
                                <td class="room-type-col ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-medium">{{ $ub['label'] }}</span>
                                        <span class="badge bg-warning text-dark small">Unassigned</span>
                                    </div>
                                </td>
                    @php $dateIndex = 0; @endphp
                                @while($dateIndex < count($dates))
                                    @php
                                        $date = $dates[$dateIndex];
                                        $booking = $this->getBookingFor($ub['bed_key'], $date);
                                        $isStart = $booking && ($booking['from'] === $date || ($dateIndex === 0 && $booking['from'] < $date));
                                        $span = $isStart ? $this->getBookingSpan($ub['bed_key'], $date) : 1;
                                    @endphp
                                    @if($isStart && $span > 0)
                                        <td class="booking-cell align-middle {{ $this->isWeekend($date) ? 'bg-weekend' : '' }}" colspan="{{ $span }}">
                                            <div class="booking-bar d-flex align-items-center justify-content-between px-2 py-1 cursor-pointer" role="button" wire:click="selectBooking('{{ $ub['bed_key'] }}', '{{ $booking['from'] }}')">
                                                <button type="button" class="btn btn-link btn-sm p-0 text-white text-start guest-name small fw-medium text-truncate" title="View reservation details for {{ $booking['guest_name'] }}">
                                                    {{ $booking['guest_name'] }}
                                                </button>
                                                @if(!empty($booking['is_paid']))
                                                    <i class="fa fa-dollar-sign small text-success"></i>
                                                @endif
                                            </div>
                                        </td>
                                        @php $dateIndex += $span; @endphp
                                    @else
                                        <td class="{{ $this->isWeekend($date) ? 'bg-weekend' : '' }}"></td>
                                        @php $dateIndex++; @endphp
                                    @endif
                                @endwhile
                            </tr>
                        @endforeach
                    @endif
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td class="room-type-col ps-3">
                            <span class="small text-muted fw-medium" title="Occupancy % = (rooms booked on this date) ÷ (total rooms) × 100">Occupancy %</span>
                            <div class="small text-muted mt-0" style="font-size: 0.7rem;">booked ÷ total</div>
                        </td>
                        @foreach($dates as $date)
                            @php $detail = $occupancyDetailByDate[$date] ?? ['booked' => 0, 'total' => 0]; @endphp
                            <td class="date-col text-center small" title="{{ $detail['booked'] }} of {{ $detail['total'] }} rooms booked on this date">
                                {{ $occupancyByDate[$date] ?? 0 }}% <span class="text-muted">({{ $detail['booked'] }}/{{ $detail['total'] }})</span>
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Room detail sidebar: upcoming guests + Add reservation / Add guest (when a room is clicked) --}}
    @if($selectedRoomUnitId)
        <div class="reservation-sidebar-overlay" wire:click="closeRoomPanel"></div>
        <div class="reservation-sidebar">
            <div class="reservation-sidebar-inner">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="mb-0">Room — {{ $selectedRoomLabel }}</h6>
                    <button type="button" class="btn btn-link btn-sm text-secondary p-0" wire:click="closeRoomPanel" aria-label="Close"><i class="fa fa-times"></i></button>
                </div>
                <p class="small text-muted mb-3">Upcoming reservations for this room/unit.</p>
                @php $upcoming = $this->getUpcomingReservationsForSelectedRoom(); @endphp
                @if(count($upcoming) > 0)
                    <ul class="list-group list-group-flush mb-4">
                        @foreach($upcoming as $res)
                            <li class="list-group-item px-0">
                                <div class="fw-medium">{{ $res['guest_name'] }}</div>
                                <div class="small text-muted">
                                    Check-in {{ $res['check_in'] }} · Check-out {{ $res['check_out'] }}
                                </div>
                                <div class="small">Pax: {{ $res['pax_adults'] }} adult(s), {{ $res['pax_children'] }} child(ren)</div>
                                <div class="small mb-1">
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none"
                                            wire:click="openEditStayForReservation({{ $res['reservation_id'] }})"
                                            title="Open folio & payment details">
                                        <span class="badge bg-secondary">{{ $res['reservation_number'] }}</span>
                                        {{ $res['room_type'] }}
                                    </button>
                                </div>
                                <div class="small text-muted">
                                    Total: {{ $res['currency'] }} {{ number_format($res['total'], 2) }} ·
                                    Paid: {{ $res['currency'] }} {{ number_format($res['paid'], 2) }} ·
                                    Balance: {{ $res['currency'] }} {{ number_format(max(0, $res['total'] - $res['paid']), 2) }}
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <button type="button"
                                            class="btn btn-outline-success btn-sm"
                                            wire:click="openCheckoutModalForReservation({{ $res['reservation_id'] }})">
                                        Review payments & POS invoices
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="small text-muted mb-4">No upcoming reservations.</p>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $selectedRoomUnitId]) }}" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i> Add reservation</a>
                    <a href="{{ route('front-office.add-reservation', ['room_unit_id' => $selectedRoomUnitId]) }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-user-plus me-1"></i> Add guest</a>
                </div>
            </div>
        </div>
    @endif

    {{-- Reservation details sidebar (slides in from right when a booking is clicked) --}}
    @if($selectedBooking)
        <div class="reservation-sidebar-overlay" wire:click="closeSidebar"></div>
        <div class="reservation-sidebar">
            <div class="reservation-sidebar-inner">
                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show py-2 small mb-3" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show py-2 small mb-3" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="mb-0">Reservation details</h6>
                    <button type="button" class="btn btn-link btn-sm text-secondary p-0" wire:click="closeSidebar" aria-label="Close"><i class="fa fa-times"></i></button>
                </div>
                {{-- Guest info --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-user text-primary"></i>
                        </div>
                        <span class="fw-semibold">{{ $selectedBooking['guest_name'] }}</span>
                    </div>
                    <div class="small text-muted">
                        <div><i class="fa fa-map-marker-alt me-1"></i> {{ $selectedBooking['country'] ?? '—' }}</div>
                        <div><i class="fa fa-phone me-1"></i> {{ $selectedBooking['phone'] ?? '—' }}</div>
                        <div><i class="fa fa-envelope me-1"></i> {{ $selectedBooking['email'] ?? '—' }}</div>
                    </div>
                </div>
                {{-- Action buttons --}}
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @if($this->canShowSidebarCheckIn())
                        <button type="button" class="btn btn-success btn-sm" wire:click="openEditStayForCheckIn">
                            <i class="fa fa-sign-in-alt me-1"></i> Check-in
                        </button>
                    @endif
                    @if($this->canShowSidebarCheckOut())
                        <button type="button" class="btn btn-warning btn-sm" wire:click="openCheckoutModal">
                            <i class="fa fa-sign-out-alt me-1"></i> Check-out
                        </button>
                    @endif
                    @if($this->canShowSidebarRecordPayment())
                        <button type="button" class="btn btn-outline-success btn-sm" wire:click="openSidebarRecordPayment">
                            <i class="fa fa-money-bill-wave me-1"></i> Record payment
                        </button>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="openEditStay">Edit</button>
                    @if(isset($selectedBooking['reservation_id']))
                        <button type="button" class="btn btn-danger btn-sm" wire:click="cancelReservation({{ $selectedBooking['reservation_id'] }})" wire:confirm="Cancel this reservation? This cannot be undone.">Cancel reservation</button>
                    @endif
                    @php
                        $sidebarPrintUrl = $this->getSidebarPrintReceiptUrl();
                        $sidebarMailtoUrl = $this->getSidebarReceiptMailtoUrl();
                    @endphp
                    <div class="dropdown">
                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Print / Send</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                @if($sidebarPrintUrl)
                                    <a class="dropdown-item" href="{{ $sidebarPrintUrl }}" target="_blank" rel="noopener">Print receipt / folio</a>
                                @else
                                    <span class="dropdown-item text-muted disabled" tabindex="-1">Print unavailable</span>
                                @endif
                            </li>
                            <li>
                                @if($sidebarMailtoUrl)
                                    <a class="dropdown-item" href="{{ $sidebarMailtoUrl }}">Send by email</a>
                                @else
                                    <span class="dropdown-item text-muted small" title="Requires a valid guest email on this reservation">Send by email (add guest email)</span>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>
                {{-- Reservation summary (two columns) --}}
                <div class="reservation-summary mb-4">
                    <div class="row g-2 small">
                        <div class="col-6"><span class="text-muted">Reservation Number</span></div><div class="col-6 fw-medium">{{ $selectedBooking['reservation_number'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Status</span></div>
                        <div class="col-6">
                            <span class="badge bg-{{ $selectedBooking['status_badge'] ?? 'secondary' }}">{{ $selectedBooking['status'] ?? '—' }}</span>
                        </div>
                        <div class="col-6"><span class="text-muted">Arrival Date</span></div><div class="col-6">{{ $selectedBooking['arrival_datetime'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Departure Date</span></div><div class="col-6">{{ $selectedBooking['departure_datetime'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Booking Date</span></div><div class="col-6">{{ $selectedBooking['booking_datetime'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Room Type</span></div><div class="col-6">{{ $selectedBooking['room_type'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Room Number</span></div><div class="col-6">{{ $selectedBooking['room_number'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Rate Plan</span></div><div class="col-6">{{ $selectedBooking['rate_plan'] ?? '—' }}</div>
                        <div class="col-6"><span class="text-muted">Pax</span></div>
                        <div class="col-6">
                            <i class="fa fa-user me-1"></i> {{ $selectedBooking['pax_adults'] ?? 0 }}
                            <i class="fa fa-baby me-1 ms-2"></i> {{ $selectedBooking['pax_infants'] ?? 0 }}
                        </div>
                        <div class="col-6"><span class="text-muted">Avg. Daily Rate</span></div><div class="col-6">{{ $selectedBooking['currency'] ?? '' }} {{ $selectedBooking['avg_daily_rate'] ?? '—' }}</div>
                    </div>
                </div>
                @php $groupSummary = $this->getGroupSummaryForSelectedBooking(); @endphp
                @if($groupSummary)
                    <div class="mb-4">
                        <h6 class="small fw-semibold mb-2">Group guests</h6>
                        <p class="small mb-1">Group: <strong>{{ $groupSummary['group_name'] }}</strong></p>
                        <p class="small mb-1">
                            Expected: <strong>{{ $groupSummary['expected'] }}</strong> ·
                            Registered: <strong>{{ $groupSummary['registered'] }}</strong> ·
                            Assigned to rooms: <strong>{{ $groupSummary['assigned'] }}</strong> ·
                            Checked-in: <strong>{{ $groupSummary['checked_in'] }}</strong>
                        </p>
                        <p class="small text-muted mb-2">
                            Remaining guests not yet registered: <strong>{{ $groupSummary['remaining'] }}</strong>
                        </p>
                        <a href="{{ route('front-office.self-registered', ['reservation' => $groupSummary['reservation_id']]) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-users me-1"></i>Manage group arrivals
                        </a>
                    </div>
                @endif
                {{-- Financial summary --}}
                <div class="border-top pt-3">
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Total</div><div class="col-6 text-end">{{ $selectedBooking['currency'] ?? '' }} {{ $selectedBooking['total'] ?? '—' }}</div>
                        <div class="col-6 text-muted">Paid</div><div class="col-6 text-end">{{ $selectedBooking['currency'] ?? '' }} {{ $selectedBooking['paid'] ?? '—' }}</div>
                        <div class="col-6 text-muted">Balance</div><div class="col-6 text-end {{ (float)($selectedBooking['balance'] ?? 0) > 0 ? 'text-danger fw-medium' : '' }}">{{ $selectedBooking['currency'] ?? '' }} {{ $selectedBooking['balance'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Record payment modal (sidebar) --}}
        @if($showSidebarRecordPaymentModal)
            <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.3); z-index: 1060;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">Record payment</h6>
                            <button type="button" class="btn-close" wire:click="closeSidebarRecordPayment" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if (session()->has('error'))
                                <div class="alert alert-danger py-2 mb-2">{{ session('error') }}</div>
                            @endif
                            <p class="small text-muted mb-2">Reservation: {{ $selectedBooking['reservation_number'] ?? '' }} · {{ $selectedBooking['guest_name'] ?? '' }}</p>
                            <p class="small mb-3">Balance due: {{ $selectedBooking['currency'] ?? 'RWF' }} {{ $selectedBooking['balance'] ?? '0.00' }}</p>
                            <div class="mb-2">
                                <label class="form-label small">Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="sidebarPaymentAmount" placeholder="0.00">
                                @error('sidebarPaymentAmount') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Payment type</label>
                                <select class="form-select form-select-sm" wire:model.live="sidebarPaymentUnified">
                                    @foreach(\App\Support\PaymentCatalog::unifiedAccommodationOptions() as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($sidebarPaymentUnified === \App\Support\PaymentCatalog::METHOD_CASH)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="sidebar_cash_later" wire:model="sidebarCashSubmitLater">
                                    <label class="form-check-label small" for="sidebar_cash_later">Submit cash at shift end (pending)</label>
                                </div>
                            @endif
                            @if(\App\Support\PaymentCatalog::unifiedChoiceRequiresClientDetails($sidebarPaymentUnified ?? ''))
                                <div class="mb-2">
                                    <label class="form-label small">Client / account details <span class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-sm" wire:model="sidebarPaymentClientReference" rows="2" placeholder="Required for pending / on account"></textarea>
                                    @error('sidebarPaymentClientReference') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="sidebar_debt_settle" wire:model.live="sidebarDebtSettlement">
                                <label class="form-check-label small" for="sidebar_debt_settle">Confirming outstanding balance (debt) payment</label>
                            </div>
                            @if($sidebarDebtSettlement)
                                <div class="mb-2">
                                    <label class="form-label small">Sales / revenue date for reports <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm" wire:model="sidebarRevenueAttributionDate">
                                    @error('sidebarRevenueAttributionDate') <span class="text-danger small">{{ $message }}</span> @enderror
                                    <div class="form-text small">Payment is counted in rooms sales on this date (e.g. last night of stay or checkout).</div>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeSidebarRecordPayment">Cancel</button>
                            <button type="button" class="btn btn-success btn-sm" wire:click="submitSidebarRecordPayment">Record payment</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Check-out confirmation: receipt details + linked invoices; confirm only when all settled --}}
    @if($showCheckoutModal && $checkoutReservationId)
        @php $checkout = $this->getCheckoutSummary(); @endphp
        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.35); z-index: 1065;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">Check-out — Review receipt & charges</h6>
                        <button type="button" class="btn-close" wire:click="closeCheckoutModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if (session()->has('error'))
                            <div class="alert alert-danger py-2 mb-3">{{ session('error') }}</div>
                        @endif
                        @if($checkout['reservation'])
                            <p class="fw-semibold mb-2">{{ $checkout['reservation']['guest_name'] }} · {{ $checkout['reservation']['reservation_number'] }} · Room {{ $checkout['reservation']['room_number'] }}</p>
                        @endif
                        <h6 class="mb-2">Reservation folio</h6>
                        @if($checkout['folio'])
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered">
                                    <tbody>
                                        <tr><td class="text-muted">Total</td><td class="text-end">{{ $checkout['folio']['currency'] }} {{ number_format($checkout['folio']['total'], 2) }}</td></tr>
                                        <tr><td class="text-muted">Paid</td><td class="text-end">{{ $checkout['folio']['currency'] }} {{ number_format($checkout['folio']['paid'], 2) }}</td></tr>
                                        <tr><td class="text-muted">Balance</td><td class="text-end {{ $checkout['folio']['balance'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $checkout['folio']['currency'] }} {{ number_format($checkout['folio']['balance'], 2) }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif
                        <h6 class="mb-2">Restaurant / POS invoices linked to this reservation</h6>
                        @if(!empty($checkout['invoices']))
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light"><tr><th>Invoice</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach($checkout['invoices'] as $inv)
                                            <tr>
                                                <td>{{ $inv['invoice_number'] }}</td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($inv['total_amount']) }}</td>
                                                <td>
                                                    @if($inv['is_settled'])
                                                        <span class="badge bg-success">{{ $inv['payment_label'] }}</span>
                                                    @else
                                                        <span class="badge bg-warning text-dark">{{ $inv['payment_label'] }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('pos.payment', ['invoice' => $inv['id']]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" wire:navigate>View / Settle</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-3">No restaurant or POS invoices linked to this reservation.</p>
                        @endif

                        <h6 class="mb-2 mt-3">Payments received (hotel)</h6>
                        @if(!empty($checkout['payments']))
                            <div class="table-responsive mb-0">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Receipt</th>
                                            <th>Payment type</th>
                                            <th class="text-end">Amount</th>
                                            <th>Received by</th>
                                            <th>After balance</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($checkout['payments'] as $p)
                                            <tr>
                                                <td>{{ $p['receipt_number'] ?? '—' }}</td>
                                                <td>{{ \App\Support\PaymentCatalog::formatPaymentLineForReport($p['payment_method'] ?? '', $p['payment_status'] ?? '') }}</td>
                                                <td class="text-end">{{ $p['currency'] ?? (\App\Models\Hotel::getHotel()->currency ?? 'RWF') }} {{ number_format((float) ($p['amount'] ?? 0), 2, '.', '') }}</td>
                                                <td>{{ $p['received_by'] ?? '—' }}</td>
                                                <td>{{ $p['currency'] ?? (\App\Models\Hotel::getHotel()->currency ?? 'RWF') }} {{ number_format((float) ($p['balance_after'] ?? 0), 2, '.', '') }}</td>
                                                <td>
                                                    <a href="{{ route('front-office.reservation-payment-receipt', ['payment' => $p['id']]) }}"
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-secondary">
                                                        Print
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0">No hotel payments recorded yet.</p>
                        @endif
                        @if(!$checkout['can_confirm'])
                            <div class="alert alert-warning py-2 mb-0">
                                <small>Confirm checkout only when the reservation balance is paid and all linked invoices are paid or assigned (room / hotel covered / credit).</small>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="closeCheckoutModal">Cancel</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="$refresh" title="Refresh after settling invoices">Refresh</button>
                        <button type="button"
                                class="btn btn-warning btn-sm"
                                wire:click="doConfirmCheckout"
                                @if(!$checkout['can_confirm']) disabled @endif
                                title="{{ $checkout['can_confirm'] ? 'Confirm checkout' : 'Pay balance and settle all invoices first' }}">
                            <i class="fa fa-sign-out-alt me-1"></i> Confirm checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @endif

    <style>
    .frontoffice-admin .frontoffice-grid { table-layout: fixed; font-size: 0.875rem; }
    .frontoffice-admin .room-type-col { width: 200px; min-width: 200px; vertical-align: middle !important; }
    .frontoffice-admin .date-col { width: 72px; min-width: 72px; }
    .frontoffice-admin .bg-weekend { background-color: #f8f9ea !important; }
    .frontoffice-admin .booking-bar { background: #198754; color: #fff; border-radius: 4px; min-height: 32px; font-size: 0.8rem; }
    .frontoffice-admin .booking-cell { padding: 3px 4px !important; vertical-align: middle !important; }
    .frontoffice-admin thead th { font-weight: 600; white-space: nowrap; border-bottom-width: 1px; }
    .frontoffice-admin tbody td { height: 40px; border-color: #e9ecef; }
    .frontoffice-admin tbody tr.room-row:hover { background-color: #f8f9fa; }
    .frontoffice-admin .cursor-pointer { cursor: pointer; }
    .frontoffice-admin .room-type-col .form-select { border: 1px solid #dee2e6; }
    /* Reservation sidebar */
    .reservation-sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.2); z-index: 1040; }
    .reservation-sidebar { position: fixed; top: 0; right: 0; bottom: 0; width: 380px; max-width: 100%; background: #fff; box-shadow: -2px 0 12px rgba(0,0,0,0.1); z-index: 1050; overflow-y: auto; }
    .reservation-sidebar-inner { padding: 1.25rem; }
    .frontoffice-admin .modal-dialog-scrollable .modal-dialog { max-height: calc(100vh - 2rem); }
    .frontoffice-admin .frontoffice-modal { max-height: 100%; display: flex; flex-direction: column; }
    .frontoffice-admin .frontoffice-modal .modal-header { flex-shrink: 0; }
    .frontoffice-admin .frontoffice-modal .modal-body { overflow-y: auto; flex: 1 1 auto; min-height: 0; }
    .frontoffice-admin .frontoffice-modal .modal-footer {
        flex-shrink: 0;
        position: sticky;
        bottom: 0;
        background: #fff;
        z-index: 20;
    }
    </style>
</div>
