<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h5 class="mb-0">{{ ($isSellingLocationReport ?? false) ? 'Selling location stock (daily)' : 'Stock by category (daily)' }}</h5>
            <p class="text-muted small mb-0">
                @if($isSellingLocationReport ?? false)
                    Sub-location (active selling) stock only — choose bar/kitchen store etc. Audit workflow is tracked separately from the main warehouse daily report.
                @else
                    Summary matches classic stock sheets: opening and closing use current unit cost × quantity; received and issued use movement values for the selected day.
                @endif
            </p>
            <p class="small mb-0 mt-1 print-hide">
                @if($isSellingLocationReport ?? false)
                    <a href="{{ route('stock.daily-by-category') }}" class="fw-semibold">Main warehouse daily report</a>
                @else
                    <a href="{{ route('stock.daily-selling-location') }}" class="fw-semibold">Selling location daily report</a>
                @endif
                <span class="text-muted"> · </span>
                <a href="{{ route('activity-log') }}" class="text-muted">Activity log</a>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap print-hide">
            <a href="{{ route('stock.reports') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i>Stock reports
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-print me-1"></i>Print
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item" onclick="window.stockDailyPrintMode('all')">Print full report</button></li>
                    <li><button type="button" class="dropdown-item" onclick="window.stockDailyPrintMode('summary')">Print summary only</button></li>
                    <li><button type="button" class="dropdown-item" onclick="window.stockDailyPrintMode('detail')">Print selected category detail</button></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card mb-3 print-hide">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="fw-semibold">Daily report audit workflow</div>
                    <div class="text-muted small">Optional: submit, verify, approve, and comment inside the system.</div>
                </div>
                @if($canManageAuditConfig)
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="auditToggle" wire:change="toggleAuditEnabled($event.target.checked)" {{ $auditEnabled ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="auditToggle">Enable audit workflow</label>
                    </div>
                @else
                    <span class="badge {{ $auditEnabled ? 'bg-success' : 'bg-secondary' }}">{{ $auditEnabled ? 'Enabled' : 'Disabled' }}</span>
                @endif
            </div>
        </div>
    </div>

    @if($auditEnabled)
        <div class="card mb-4 print-hide">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <span class="fw-semibold">Status:</span>
                            <span class="badge bg-{{ ($auditReport['status'] ?? 'draft') === 'approved' ? 'success' : ((($auditReport['status'] ?? 'draft') === 'rejected') ? 'danger' : 'warning text-dark') }}">
                                {{ strtoupper($auditReport['status'] ?? 'draft') }}
                            </span>
                            @if(!empty($auditReport['submitted_by']))
                                <span class="text-muted small">Submitted by {{ $auditReport['submitted_by'] }} {{ $auditReport['submitted_at'] ? '('.$auditReport['submitted_at'].')' : '' }}</span>
                            @endif
                            @if(!empty($auditReport['verified_by']))
                                <span class="text-muted small">| Verified by {{ $auditReport['verified_by'] }} {{ $auditReport['verified_at'] ? '('.$auditReport['verified_at'].')' : '' }}</span>
                            @endif
                            @if(!empty($auditReport['approved_by']))
                                <span class="text-muted small">| Approved by {{ $auditReport['approved_by'] }} {{ $auditReport['approved_at'] ? '('.$auditReport['approved_at'].')' : '' }}</span>
                            @endif
                        </div>
                        @if(!empty($auditReport['rejection_reason']))
                            <div class="alert alert-danger py-2 mb-2">
                                <strong>Rejection reason:</strong> {{ $auditReport['rejection_reason'] }}
                            </div>
                        @endif
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            @if($canSubmitAudit)
                                <button type="button" class="btn btn-primary btn-sm" wire:click="submitForVerification">Submit for verification</button>
                            @endif
                            @if($canVerifyAudit)
                                <button type="button" class="btn btn-warning btn-sm" wire:click="verifyReport">Verify</button>
                            @endif
                            @if($canApproveAudit)
                                <button type="button" class="btn btn-success btn-sm" wire:click="approveReport">Approve</button>
                            @endif
                        </div>
                        @if($canVerifyAudit || $canApproveAudit)
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" wire:model.live="rejectionReason" placeholder="Reason to reject / send back">
                                <button class="btn btn-outline-danger" type="button" wire:click="rejectReport">Reject</button>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label mb-1">Comment</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" wire:model.live="auditComment" placeholder="Add context, discrepancy, or clarification">
                            <button type="button" class="btn btn-outline-primary" wire:click="addAuditComment">Post</button>
                        </div>
                        <div class="mt-2 border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                            @forelse($auditComments as $c)
                                <div class="small mb-2">
                                    <span class="fw-semibold">{{ $c['user'] }}</span>
                                    <span class="text-muted">[{{ $c['stage'] }}] {{ $c['created_at'] }}</span><br>
                                    {{ $c['body'] }}
                                </div>
                            @empty
                                <div class="small text-muted">No comments yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-4 print-hide">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="form-floating">
                        <input type="date" class="form-control" id="reportDate" wire:model.live="reportDate">
                        <label for="reportDate">Report date</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating">
                        <select class="form-select" id="stockLocationId" wire:model.live="stockLocationId">
                            <option value="">{{ ($isSellingLocationReport ?? false) ? 'Select selling location…' : 'All stock locations' }}</option>
                            @foreach($stockLocations as $loc)
                                <option value="{{ $loc['id'] }}">{{ $loc['name'] }}</option>
                            @endforeach
                        </select>
                        <label for="stockLocationId">{{ ($isSellingLocationReport ?? false) ? 'Selling location (required)' : 'Stock location' }}</label>
                    </div>
                </div>
                <div class="col-md-5">
                    <span class="text-muted small">Click a category row below to show item-level lines (like a category worksheet).</span>
                </div>
            </div>
        </div>
    </div>

    @if(($isSellingLocationReport ?? false) && !$stockLocationId)
        <div class="alert alert-warning print-hide">
            Select a <strong>selling location</strong> (sub-stock) above to load figures. This report is separate from the main warehouse sheet so each area can submit its own verified daily report.
        </div>
    @endif

    <div class="text-center mb-4">
        <div class="fw-semibold fs-5">{{ $hotelName }}</div>
        <div class="text-uppercase small text-muted">Stock summary — {{ \Carbon\Carbon::parse($reportDate)->format('l, F j, Y') }}</div>
    </div>

    <div class="card mb-4 report-summary-block">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Stock summary by inventory category</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Opening value</th>
                            <th class="text-end">Received value</th>
                            <th class="text-end">Issued value</th>
                            <th class="text-end">Closing value</th>
                            <th class="text-center print-hide">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summaryRows as $row)
                            <tr class="{{ $detailCategory === $row['category'] ? 'table-primary' : '' }}" style="cursor: pointer;" wire:click="selectCategory('{{ $row['category'] }}')">
                                <td>{{ $row['label'] }}</td>
                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['opening_value']) }}</td>
                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['received_value']) }}</td>
                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['issued_value']) }}</td>
                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($row['closing_value']) }}</td>
                                <td class="text-center print-hide">
                                    @if($detailCategory === $row['category'])
                                        <span class="badge bg-primary">Showing</span>
                                    @else
                                        <span class="text-muted small">View</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($detailCategory && isset($categoryLabels[$detailCategory]))
        <div class="card mb-4 report-detail-block">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong>STOCK MANAGEMENT / {{ strtoupper($categoryLabels[$detailCategory]) }}</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary print-hide" wire:click="selectCategory(null)">Close detail</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-secondary">
                            <tr>
                                <th rowspan="2">Item</th>
                                <th rowspan="2">Unit</th>
                                <th colspan="3" class="text-center">Opening</th>
                                <th colspan="3" class="text-center bg-light">Received</th>
                                <th colspan="3" class="text-center">Issued</th>
                                <th colspan="3" class="text-center bg-light">Closing</th>
                            </tr>
                            <tr>
                                <th class="text-end small">Qty</th>
                                <th class="text-end small">Unit cost</th>
                                <th class="text-end small">Value</th>
                                <th class="text-end small bg-light">Qty</th>
                                <th class="text-end small bg-light">—</th>
                                <th class="text-end small bg-light">Value</th>
                                <th class="text-end small">Qty</th>
                                <th class="text-end small">—</th>
                                <th class="text-end small">Value</th>
                                <th class="text-end small bg-light">Qty</th>
                                <th class="text-end small bg-light">Unit cost</th>
                                <th class="text-end small bg-light">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($detailLines as $line)
                                @php
                                    $negOpen = ($line['opening_qty'] ?? 0) < 0;
                                    $negClose = ($line['closing_qty'] ?? 0) < 0;
                                    $negBalance = $negOpen || $negClose;
                                @endphp
                                <tr class="{{ $negBalance ? 'table-warning' : '' }}">
                                    <td>
                                        {{ $line['name'] }}
                                        @if($negBalance)
                                            <span class="badge bg-warning text-dark ms-1 align-middle" title="Ledger shows negative on-hand (oversell, adjustment, or missing receipt). Review movements and stock quantity.">
                                                <i class="fa fa-exclamation-triangle me-1"></i>Negative balance
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $line['qty_unit'] ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($line['opening_qty'], 4) }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($line['unit_cost']) }}</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($line['opening_value']) }}</td>
                                    <td class="text-end bg-light">{{ number_format($line['received_qty'], 4) }}</td>
                                    <td class="text-end bg-light">—</td>
                                    <td class="text-end bg-light">{{ \App\Helpers\CurrencyHelper::format($line['received_value']) }}</td>
                                    <td class="text-end">{{ number_format($line['issued_qty'], 4) }}</td>
                                    <td class="text-end">—</td>
                                    <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($line['issued_value']) }}</td>
                                    <td class="text-end bg-light">{{ number_format($line['closing_qty'], 4) }}</td>
                                    <td class="text-end bg-light">{{ \App\Helpers\CurrencyHelper::format($line['unit_cost']) }}</td>
                                    <td class="text-end bg-light">{{ \App\Helpers\CurrencyHelper::format($line['closing_value']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center text-muted py-4">No stock lines in this category (or no movements for this location).</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($detailLines) > 0)
                            <tfoot class="table-warning">
                                @php
                                    $tOpen = collect($detailLines)->sum('opening_value');
                                    $tRec = collect($detailLines)->sum('received_value');
                                    $tIss = collect($detailLines)->sum('issued_value');
                                    $tCls = collect($detailLines)->sum('closing_value');
                                @endphp
                                <tr>
                                    <th colspan="4" class="text-end">Totals</th>
                                    <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($tOpen) }}</th>
                                    <th colspan="2" class="bg-light"></th>
                                    <th class="text-end bg-light">{{ \App\Helpers\CurrencyHelper::format($tRec) }}</th>
                                    <th colspan="2"></th>
                                    <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($tIss) }}</th>
                                    <th colspan="2" class="bg-light"></th>
                                    <th class="text-end bg-light">{{ \App\Helpers\CurrencyHelper::format($tCls) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($auditEnabled && $recentApprovedReports->count() > 0)
        <div class="card mb-4 print-hide">
            <div class="card-header"><strong>Recent approved daily reports</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Scope</th>
                            <th>Verified by</th>
                            <th>Approved by</th>
                            <th>Approved at</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($recentApprovedReports as $r)
                            <tr>
                                <td>{{ optional($r->report_date)->format('Y-m-d') }}</td>
                                <td>{{ $r->stockLocation?->name ?? 'All stock locations' }}</td>
                                <td>{{ $r->verifiedBy?->name ?? '—' }}</td>
                                <td>{{ $r->approvedBy?->name ?? '—' }}</td>
                                <td>{{ optional($r->approved_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card print-signature-block">
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">Prepared by (Storekeeper)</div>
                        <input type="text" class="form-control form-control-sm mb-2 print-hide" wire:model.live="prepared_by_name" placeholder="Name">
                        <div class="d-none print-only">{{ $prepared_by_name }}</div>
                        <div class="text-muted">Signature: __________________ &nbsp; Date: __________</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">Checked by (Accountant)</div>
                        <input type="text" class="form-control form-control-sm mb-2 print-hide" wire:model.live="checked_by_name" placeholder="Name">
                        <div class="d-none print-only">{{ $checked_by_name }}</div>
                        <div class="text-muted">Signature: __________________ &nbsp; Date: __________</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">Received by (Dept Head)</div>
                        <input type="text" class="form-control form-control-sm mb-2 print-hide" wire:model.live="received_by_name" placeholder="Name">
                        <div class="d-none print-only">{{ $received_by_name }}</div>
                        <div class="text-muted">Signature: __________________ &nbsp; Date: __________</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">Approved by (Hotel Manager)</div>
                        <input type="text" class="form-control form-control-sm mb-2 print-hide" wire:model.live="approved_by_name" placeholder="Name">
                        <div class="d-none print-only">{{ $approved_by_name }}</div>
                        <div class="text-muted">Signature: __________________ &nbsp; Date: __________</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .print-hide { display: none !important; }
            .print-only { display: block !important; }
            .content { margin-left: 0 !important; }
            .sidebar { display: none !important; }
            .navbar { display: none !important; }
            body.print-summary-mode .report-detail-block,
            body.print-summary-mode .print-signature-block { display: none !important; }
            body.print-detail-mode .report-summary-block { display: none !important; }
        }
        .print-only { display: none; }
    </style>
    <script>
        window.stockDailyPrintMode = function(mode) {
            document.body.classList.remove('print-summary-mode', 'print-detail-mode');
            if (mode === 'summary') document.body.classList.add('print-summary-mode');
            if (mode === 'detail') document.body.classList.add('print-detail-mode');
            window.print();
            setTimeout(() => {
                document.body.classList.remove('print-summary-mode', 'print-detail-mode');
            }, 600);
        };
    </script>
</div>
