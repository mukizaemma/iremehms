<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Receive Payment</h5>
                        <p class="text-muted small mb-0">Invoice {{ $invoice->invoice_number ?? '' }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('pos.receipt', ['order' => $invoice->order_id]) }}" class="btn btn-outline-primary" target="_blank"><i class="fa fa-print me-1"></i>Print invoice</a>
                        <a href="{{ route('pos.orders') }}" class="btn btn-outline-secondary">Back to Orders</a>
                    </div>
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

                @if($invoice)
                    <div class="row align-items-stretch">
                        <div class="col-lg-6 d-flex mb-3 mb-lg-0">
                            <div class="card w-100">
                                <div class="card-header">Invoice summary</div>
                                <div class="card-body">
                                    @php
                                        $invTotal = (float) $invoice->total_amount;
                                        $vatBreakdown = \App\Helpers\VatHelper::fromInclusive($invTotal);
                                    @endphp
                                    <ul class="list-unstyled small text-muted mb-3">
                                        @foreach($invoice->order->orderItems ?? [] as $oi)
                                            <li>{{ (int) $oi->quantity }} × {{ $oi->menuItem->name ?? 'N/A' }} — {{ \App\Helpers\CurrencyHelper::format($oi->line_total) }}</li>
                                        @endforeach
                                    </ul>
                                    <hr class="my-2">
                                    <div class="row small mb-2">
                                        <div class="col-4 text-muted">Net (excl. VAT)</div>
                                        <div class="col-8">{{ \App\Helpers\CurrencyHelper::format($vatBreakdown['net']) }}</div>
                                    </div>
                                    <div class="row small mb-2">
                                        <div class="col-4 text-muted">VAT ({{ (int)\App\Helpers\VatHelper::getVatRate() }}% included)</div>
                                        <div class="col-8">{{ \App\Helpers\CurrencyHelper::format($vatBreakdown['vat']) }}</div>
                                    </div>
                                    <div class="row small mb-2">
                                        <div class="col-4 text-muted">Total (amount to pay)</div>
                                        <div class="col-8">{{ \App\Helpers\CurrencyHelper::format($invTotal) }}</div>
                                    </div>
                                    <div class="row small mb-2">
                                        <div class="col-4 text-muted">Paid</div>
                                        <div class="col-8">{{ \App\Helpers\CurrencyHelper::format($invoice->total_paid) }}</div>
                                    </div>
                                    <div class="row small mb-2">
                                        <div class="col-4 text-muted">Balance</div>
                                        <div class="col-8 fw-bold">{{ \App\Helpers\CurrencyHelper::format($invoice->balance) }}</div>
                                    </div>
                                    @if($invoice->charge_type === \App\Models\Invoice::CHARGE_TYPE_ROOM && $invoice->reservation)
                                        <hr class="my-2">
                                        <p class="small mb-0"><strong>Assigned to room:</strong> {{ $invoice->room?->room_number ?? '—' }} · {{ $invoice->reservation->guest_name }} · Checkout {{ $invoice->reservation->check_out_date?->format('d M Y') }} {{ $invoice->reservation->check_out_time ? \Carbon\Carbon::parse($invoice->reservation->check_out_time)->format('H:i') : '' }}</p>
                                    @elseif($invoice->charge_type === \App\Models\Invoice::CHARGE_TYPE_HOTEL_COVERED)
                                        <hr class="my-2">
                                        <p class="small mb-0"><strong>Hotel covered:</strong> {{ $invoice->hotel_covered_names ?: '—' }} · {{ $invoice->hotel_covered_reason ?: '—' }}</p>
                                        @if($invoice->postedBy)
                                            <p class="small text-muted mb-0">Assigned by {{ $invoice->postedBy->name }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex">
                            @if($invoice->isModificationLocked() && !$canModifyReceipt)
                                <div class="card w-100">
                                    <div class="card-header">Receipt confirmed</div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">This receipt has been confirmed (paid, credit, assigned to room, or covered by hotel) and cannot be modified unless a manager or General Manager approves a modification request.</p>
                                        @if($canRequestModification && !$hasPendingModificationRequest)
                                            <p class="small mb-2">As the order creator, you can request permission to modify:</p>
                                            <div class="mb-2">
                                                <label class="form-label small mb-0">Reason for modification</label>
                                                <textarea class="form-control form-control-sm" rows="2" wire:model.defer="modification_request_reason" placeholder="e.g. Wrong amount, wrong room..."></textarea>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="requestReceiptModification">Request modification</button>
                                        @elseif($hasPendingModificationRequest)
                                            <p class="text-warning mb-0"><i class="fa fa-clock me-1"></i>Your modification request is pending approval. A manager or General Manager can approve it from <a href="{{ route('pos.receipt-modification-requests') }}">Receipt modification requests</a>.</p>
                                        @else
                                            <p class="small mb-0">Only the user who created or was assigned this order can request modification. Ask your manager or GM to modify if needed.</p>
                                        @endif
                                    </div>
                                </div>
                            @elseif($invoice->balance > 0 && $canReceivePayment)
                                <div class="card w-100">
                                    <div class="card-header d-flex flex-wrap align-items-center gap-2 gap-md-3">
                                        <span>Add payment</span>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="radio" name="assignment_type" id="assign_room" value="room" wire:model.live="assignment_type">
                                                <label class="form-check-label small" for="assign_room">Assign to Room</label>
                                            </div>
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="radio" name="assignment_type" id="assign_hotel" value="hotel_covered" wire:model.live="assignment_type">
                                                <label class="form-check-label small" for="assign_hotel">Covered by Hotel</label>
                                            </div>
                                            <div class="form-check form-check-inline mb-0">
                                                <input class="form-check-input" type="radio" name="assignment_type" id="assign_meeting" value="meeting_room" disabled>
                                                <label class="form-check-label small text-muted" for="assign_meeting" title="Coming soon">Meeting room</label>
                                                <span class="badge bg-secondary align-middle">Soon</span>
                                            </div>
                                            @if($assignment_type !== 'none')
                                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" wire:click="$set('assignment_type', 'none')">Direct payment</button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        @if($assignment_type === 'none')
                                            <form wire:submit.prevent="addPayment" class="d-flex flex-column flex-grow-1">
                                                <div class="row g-2 mb-2">
                                                    <div class="col-12">
                                                        <label class="form-label small mb-0">Payment type</label>
                                                        <select class="form-select form-select-sm" wire:model.live="payment_unified">
                                                            @foreach(\App\Support\PaymentCatalog::unifiedPosOptions() as $val => $label)
                                                                <option value="{{ $val }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('payment_unified') <div class="text-danger small">{{ $message }}</div> @enderror
                                                    </div>
                                                    @if(\App\Support\PaymentCatalog::unifiedChoiceRequiresClientDetails($payment_unified ?? ''))
                                                        <div class="col-12">
                                                            <label class="form-label small mb-0">Client / account details <span class="text-danger">*</span></label>
                                                            <textarea class="form-control form-control-sm" rows="2" wire:model.defer="payment_client_reference" placeholder="Guest name, company, or reference"></textarea>
                                                            @error('payment_client_reference') <div class="text-danger small">{{ $message }}</div> @enderror
                                                        </div>
                                                    @endif
                                                    <div class="col-12">
                                                        <label class="form-label small mb-0">Amount</label>
                                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" wire:model.defer="amount" placeholder="0.00" required>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mb-2 small">
                                                    <div class="col-12">
                                                        <span class="text-muted">Split helpers:</span>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs ms-1" wire:click="suggestSplitAmount(2)">
                                                            Split in 2
                                                        </button>
                                                        <span class="ms-2">or</span>
                                                        <input type="number" min="1" class="form-control form-control-sm d-inline-block ms-1" style="width: 70px;"
                                                               wire:model.defer="split_parts">
                                                        <button type="button" class="btn btn-outline-secondary btn-xs ms-1" wire:click="suggestSplitAmount">
                                                            Apply
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-6">
                                                        <label class="form-label small mb-0">Tip amount</label>
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.defer="tip_amount" placeholder="0">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label small mb-0">Tip to</label>
                                                        <select class="form-select form-select-sm" wire:model.defer="tip_handling">
                                                            <option value="HOTEL">Hotel</option>
                                                            <option value="WAITER">Waiter</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                @if($payment_unified === \App\Support\PaymentCatalog::METHOD_CASH)
                                                    <div class="form-check form-check-inline mb-2">
                                                        <input type="checkbox" class="form-check-input" id="cash_submit_later" wire:model.defer="cash_submit_later">
                                                        <label class="form-check-label small" for="cash_submit_later">Submit at shift end</label>
                                                    </div>
                                                @endif
                                                @php
                                                    $paySplitBase = (float) $invoice->balance;
                                                    $payParts = max(1, (int) $split_parts);
                                                    $payShare = $payParts > 1 ? round($paySplitBase / $payParts, 2) : $paySplitBase;
                                                    $payAmounts = [];
                                                    for ($p = 1; $p < $payParts; $p++) { $payAmounts[$p] = $payShare; }
                                                    $payAmounts[$payParts] = $payParts > 1 ? round($paySplitBase - $payShare * ($payParts - 1), 2) : $paySplitBase;
                                                @endphp
                                                <div class="mb-2 pt-2 border-top">
                                                    <span class="text-muted small">Print split receipts:</span>
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @for($p = 1; $p <= $payParts; $p++)
                                                            <a href="{{ route('pos.receipt', ['order' => $invoice->order_id]) }}?{{ http_build_query(['split_part' => $p, 'split_parts' => $payParts, 'split_amount' => number_format($payAmounts[$p], 2, '.', '')]) }}"
                                                                   target="_blank"
                                                                   class="btn btn-outline-secondary btn-sm">
                                                                <i class="fa fa-print me-1"></i>Part {{ $p }}
                                                            </a>
                                                        @endfor
                                                    </div>
                                                </div>
                                                <div class="mt-auto pt-2 d-flex flex-wrap gap-2">
                                                    <button type="submit" class="btn btn-primary btn-sm">Record payment</button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" wire:click="markAsPayLater" wire:confirm="Set as Pay later / Credit? Table will be freed.">Pay later</button>
                                                </div>
                                            </form>
                                        @elseif($assignment_type === 'room')
                                            <div class="d-flex flex-column flex-grow-1">
                                                <p class="text-muted small mb-2">Charge to guest room. No payment needed here.</p>
                                                <div class="mb-2">
                                                    <label class="form-label small mb-1">Search by room number or guest name</label>
                                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="room_search" placeholder="Room number or guest name...">
                                                </div>
                                                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                                    <table class="table table-sm table-hover mb-0">
                                                        <thead class="table-light sticky-top">
                                                            <tr>
                                                                <th>Room</th>
                                                                <th>Guest</th>
                                                                <th>Checkout</th>
                                                                <th></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @forelse($inHouseReservations ?? [] as $res)
                                                                @php
                                                                    $roomNumbers = $res->roomUnits->map(fn($ru) => $ru->room?->room_number)->filter()->unique()->values()->join(', ');
                                                                    $checkout = $res->check_out_date?->format('d M Y') . ($res->check_out_time ? ' ' . \Carbon\Carbon::parse($res->check_out_time)->format('H:i') : '');
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $roomNumbers ?: '—' }}</td>
                                                                    <td>{{ $res->guest_name }}</td>
                                                                    <td>{{ $checkout ?: '—' }}</td>
                                                                    <td>
                                                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="$set('room_reservation_id', {{ $res->id }})">
                                                                            {{ $room_reservation_id === $res->id ? 'Selected' : 'Select' }}
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr><td colspan="4" class="text-muted small text-center py-2">No in-house guests. Search by room or guest name.</td></tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                </div>
                                                @if($room_reservation_id)
                                                    @php $sel = collect($inHouseReservations ?? [])->firstWhere('id', $room_reservation_id); @endphp
                                                    @if($sel)
                                                        <p class="small text-success mb-2 mt-2"><i class="fa fa-check me-1"></i>Assigned to {{ $sel->guest_name }} · Room(s) {{ $sel->roomUnits->map(fn($ru) => $ru->room?->room_number)->filter()->unique()->values()->join(', ') }}</p>
                                                    @endif
                                                @endif
                                                <div class="mt-auto pt-2">
                                                    <button type="button" class="btn btn-primary btn-sm" wire:click="assignToRoom"
                                                            wire:loading.attr="disabled">
                                                        Assign to room
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            {{-- Covered by Hotel --}}
                                            <div class="d-flex flex-column flex-grow-1">
                                                <p class="text-muted small mb-2">Mark as covered by the hotel. No payment needed here.</p>
                                                <div class="row g-2 mb-2">
                                                    <div class="col-12">
                                                        <label class="form-label small mb-0">Names (who authorised / for whom)</label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.defer="hotel_covered_names" placeholder="e.g. Manager name, guest name">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small mb-0">Reason <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.defer="hotel_covered_reason" placeholder="e.g. Complimentary, staff meal">
                                                    </div>
                                                </div>
                                                <div class="mt-auto pt-2">
                                                    <button type="button" class="btn btn-primary btn-sm" wire:click="assignAsHotelCovered"
                                                            wire:loading.attr="disabled">
                                                        Set as hotel covered
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @elseif($canSplitBill)
                                @php
                                    $splitBase = $invoice->balance > 0 ? $invoice->balance : (float) $invoice->total_amount;
                                @endphp
                                <div class="card w-100">
                                    <div class="card-header">Split helper</div>
                                    <div class="card-body small">
                                        <p class="text-muted mb-2">
                                            Use this helper to agree split amounts with guests.
                                            @if(!$canReceivePayment)
                                                Only cashier/manager can record payments.
                                            @endif
                                        </p>
                                        <div class="mb-2">
                                            <label class="form-label small mb-0">Proposed amount</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" wire:model.defer="amount" placeholder="0.00">
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-muted">Split helpers:</span>
                                            <button type="button" class="btn btn-outline-secondary btn-xs ms-1" wire:click="suggestSplitAmount(2)">
                                                Split in 2
                                            </button>
                                            <span class="ms-2">or</span>
                                            <input type="number" min="1" class="form-control form-control-sm d-inline-block ms-1" style="width: 70px;"
                                                   wire:model.defer="split_parts">
                                            <button type="button" class="btn btn-outline-secondary btn-xs ms-1" wire:click="suggestSplitAmount">
                                                Apply
                                            </button>
                                        </div>
                                        <p class="small text-muted mb-0">
                                            Amount to split: <strong>{{ \App\Helpers\CurrencyHelper::format($splitBase) }}</strong>.
                                            Share suggestion shows how much each person should pay, even if the invoice is already paid.
                                        </p>
                                        @php
                                            $parts = max(1, (int) $split_parts);
                                            $share = $parts > 1 ? round($splitBase / $parts, 2) : $splitBase;
                                            $amounts = [];
                                            for ($p = 1; $p < $parts; $p++) {
                                                $amounts[$p] = $share;
                                            }
                                            $amounts[$parts] = $parts > 1 ? round($splitBase - $share * ($parts - 1), 2) : $splitBase;
                                        @endphp
                                        <div class="mt-2 pt-2 border-top">
                                            <span class="text-muted small">Print split receipts:</span>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @for($p = 1; $p <= $parts; $p++)
                                                    <a href="{{ route('pos.receipt', ['order' => $invoice->order_id]) }}?{{ http_build_query(['split_part' => $p, 'split_parts' => $parts, 'split_amount' => number_format($amounts[$p], 2, '.', '')]) }}"
                                                       target="_blank"
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fa fa-print me-1"></i>Part {{ $p }}
                                                    </a>
                                                @endfor
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="card w-100"><div class="card-body align-self-center"><span class="text-success">Invoice fully paid.</span></div></div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
