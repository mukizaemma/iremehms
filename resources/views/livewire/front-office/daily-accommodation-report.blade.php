<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3">
                    <h5 class="mb-2">Rooms daily report</h5>
                    @include('livewire.front-office.partials.front-office-quick-nav')
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <form wire:submit.prevent="applyReportFilters">
                            <div class="row g-3 align-items-end">
                                <div class="col-sm-6 col-md-6 col-lg-2">
                                    <label class="form-label small">From</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="date_from" name="date_from">
                                </div>
                                <div class="col-sm-6 col-md-6 col-lg-2">
                                    <label class="form-label small">To</label>
                                    <input type="date" class="form-control form-control-sm" wire:model="date_to" name="date_to">
                                </div>
                                @if($canPickStaff)
                                <div class="col-md-12 col-lg">
                                    <label class="form-label small">Room payments attributed to</label>
                                    <select class="form-select form-select-sm" wire:model.live="staffScope" name="staff_scope">
                                        <option value="self">Self (my payments)</option>
                                        <option value="all">All staff</option>
                                        <option value="user">Specific user</option>
                                    </select>
                                    @if($staffScope === 'user')
                                        <div class="mt-2">
                                            <select class="form-select form-select-sm" wire:model="staffId" name="staff_id">
                                                @foreach($staffOptions as $id => $name)
                                                    <option value="{{ $id }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                                @endif
                                <div class="col-12 col-lg-auto">
                                    <label class="form-label small d-block">&nbsp;</label>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        @if($canPickStaff)
                                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                                <span wire:loading.remove wire:target="applyReportFilters"><i class="fa fa-filter me-1"></i>Apply filters</span>
                                                <span wire:loading wire:target="applyReportFilters"><i class="fa fa-spinner fa-spin me-1"></i>Loading…</span>
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" title="Load report for selected dates (your payments only)">
                                                <span wire:loading.remove wire:target="applyReportFilters"><i class="fa fa-filter me-1"></i>Filter</span>
                                                <span wire:loading wire:target="applyReportFilters"><i class="fa fa-spinner fa-spin me-1"></i>Loading…</span>
                                            </button>
                                        @endif
                                        <a
                                            href="{{ route('front-office.daily-accommodation-report.print', array_filter(['date_from' => $date_from, 'date_to' => $date_to, 'date' => ($date_from === $date_to ? $date_from : null), 'staff_scope' => $staffScope, 'staff_id' => $staffId])) }}"
                                            target="_blank"
                                            class="btn btn-outline-primary btn-sm"
                                        >
                                            <i class="fa fa-print me-1"></i>Print (A4)
                                        </a>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-auto ms-lg-auto d-flex flex-wrap align-items-end gap-3 justify-content-lg-end">
                                    <div class="text-lg-end">
                                        <div class="text-muted small">Currency</div>
                                        <div class="fw-semibold">{{ $currency ?: '—' }}</div>
                                    </div>
                                    <div>
                                        <a
                                            href="{{ route('general.monthly-sales-summary', ['month' => $date_from ? substr($date_from, 0, 7) : now()->format('Y-m')]) }}"
                                            class="btn btn-outline-primary btn-sm"
                                        >
                                            <i class="fa fa-calendar me-1"></i>Monthly report
                                        </a>
                                    </div>
                                </div>
                            </div>

                            @if($reports_show_vat)
                                <div class="alert alert-info py-2 small mt-3 mb-0">VAT is shown on this report (hotel setting). Turn off under <strong>System configuration</strong> if you only need amounts including VAT without a separate column.</div>
                            @endif

                            <p class="small text-muted mb-0 mt-3">
                                <i class="fa fa-info-circle me-1"></i>
                                @if($canPickStaff)
                                    Set dates and staff, then <strong>Apply filters</strong> — the rooms report does not auto-refresh while you type.
                                @else
                                    Set dates, then click <strong>Filter</strong> — the rooms report does not auto-refresh while you type (shows your payments only).
                                @endif
                            </p>
                        </form>
                    </div>
                </div>

                @if($currency !== '')
                    <div class="mb-3">
                        <p class="small text-muted mb-2">
                            @if(($date_from ?? '') === ($date_to ?? ''))
                                <strong>Total sales</strong> default to <strong>today</strong> (hotel date) until you change the range. Showing: <strong>{{ $date_from }}</strong>.
                            @else
                                Showing selected range: <strong>{{ $date_from }}</strong> — <strong>{{ $date_to }}</strong>.
                            @endif
                            Paid amount uses reservation payments in this range ({{ $staffScopeLabel ?? 'filtered' }}).
                        </p>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-white rounded border h-100">
                                    <div class="text-muted small">Total sales</div>
                                    <div class="fw-semibold fs-5">{{ $currency }} {{ number_format((float) $total_gross, 2, '.', '') }}</div>
                                    <div class="small text-muted mt-1">In-house accommodation for each night in the range (day-share of folio totals).</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-white rounded border h-100">
                                    <div class="text-muted small">Paid amount</div>
                                    <div class="fw-semibold fs-5">{{ $currency }} {{ number_format((float) $summary_paid_period, 2, '.', '') }}</div>
                                    <div class="small text-muted mt-1">All payment lines recorded in the period (by type below).</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="p-3 bg-white rounded border h-100">
                                    <div class="text-muted small">Balance</div>
                                    <div class="fw-semibold fs-5 {{ ((float) $total_balance_due) > 0 ? 'text-danger' : '' }}">{{ $currency }} {{ number_format((float) $total_balance_due, 2, '.', '') }}</div>
                                    <div class="small text-muted mt-1">Outstanding folio balances; each guest counted once (not × nights).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($reports_show_vat)
                        <div class="alert alert-light border py-2 small mb-3">
                            <strong>VAT (period):</strong> {{ $currency }} {{ number_format((float) $total_tax, 2, '.', '') }} —
                            Detail column shown in the table when enabled in System configuration.
                        </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header py-2 small fw-semibold">Room / folio payments by type (period, {{ $staffScopeLabel ?? 'filtered' }})</div>
                                <div class="card-body py-2">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @php $ptypeLabels = \App\Support\PaymentCatalog::accommodationReportBucketLabels(); @endphp
                                            @foreach($payments_by_type ?? [] as $key => $amt)
                                                <tr>
                                                    <td>{{ $ptypeLabels[$key] ?? $key }}</td>
                                                    <td class="text-end">{{ $currency }} {{ number_format((float) $amt, 2, '.', '') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @php
                    $colCount = $reports_show_vat ? 14 : 13;
                    $totalColspanBeforeRate = 7;
                @endphp

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="white-space: nowrap;">Date</th>
                                <th>Guest</th>
                                <th>Address</th>
                                <th>ID</th>
                                <th>Phone</th>
                                <th>Room number</th>
                                <th class="text-end">Nights</th>
                                <th class="text-end">Room rate</th>
                                <th style="white-space: nowrap;">Currency</th>
                                <th>Payment mode (day)</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance due</th>
                                @if($reports_show_vat)
                                    <th class="text-end">Tax (VAT) day</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @if(count($rows) > 0)
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="small text-muted">{{ $row['date'] }}</td>
                                        <td class="fw-semibold">{{ $row['guest_name'] }}</td>
                                        <td>{{ $row['guest_address'] }}</td>
                                        <td>{{ $row['guest_id_number'] }}</td>
                                        <td>{{ $row['guest_phone'] }}</td>
                                        <td>{{ $row['room_number'] }}</td>
                                        <td class="text-end">{{ (int) ($row['nights'] ?? 0) }}</td>
                                        <td class="text-end">{{ $row['room_rate'] }}</td>
                                        <td class="small">{{ $row['currency'] }}</td>
                                        <td class="small">{{ $row['payment_mode'] }}</td>
                                        <td class="text-end">{{ $row['paid_today'] }}</td>
                                        <td class="text-end">{{ $row['credit_today'] }}</td>
                                        <td class="text-end {{ ((float)$row['balance_due'] ?? 0) > 0 ? 'text-danger fw-semibold' : '' }}">{{ $row['balance_due'] }}</td>
                                        @if($reports_show_vat)
                                            <td class="text-end">{{ $row['tax_for_row'] }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="{{ $colCount }}" class="text-muted">No in-house accommodations for the selected date range.</td>
                                </tr>
                            @endif
                        </tbody>
                        @if(count($rows) > 0)
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="{{ $totalColspanBeforeRate }}" class="text-end">Totals</th>
                                    <th class="text-end">{{ number_format((float)$total_gross, 2, '.', '') }}</th>
                                    <th></th>
                                    <th></th>
                                    <th class="text-end">{{ number_format((float)$total_paid_today, 2, '.', '') }}</th>
                                    <th class="text-end">{{ number_format((float)$total_credit_today, 2, '.', '') }}</th>
                                    <th class="text-end {{ ((float)$total_balance_due ?? 0) > 0 ? 'text-danger fw-semibold' : '' }}">{{ number_format((float)$total_balance_due, 2, '.', '') }}</th>
                                    @if($reports_show_vat)
                                        <th class="text-end">{{ number_format((float)$total_tax, 2, '.', '') }}</th>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                @if(count($rows) > 0)
                    <p class="small text-muted mt-2 mb-3">Detail rows: payments in <strong>Paid</strong> / <strong>Credit</strong> columns are per guest-night; summary cards above match the period totals.</p>
                @endif

                @if($currency !== '')
                    <div class="mt-4 pt-4 border-top">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded border h-100">
                                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Prepared by</h6>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Name</label>
                                        <div class="border-bottom border-secondary" style="min-height: 2rem;"></div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small text-muted mb-1">Signature</label>
                                        <div class="border-bottom border-secondary" style="min-height: 2.25rem;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded border h-100">
                                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Verified by</h6>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted mb-1">Name</label>
                                        <div class="border-bottom border-secondary" style="min-height: 2rem;"></div>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label small text-muted mb-1">Signature</label>
                                        <div class="border-bottom border-secondary" style="min-height: 2.25rem;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">For printed copy: use <strong>Print (A4)</strong> — signature lines are included on the printout.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
