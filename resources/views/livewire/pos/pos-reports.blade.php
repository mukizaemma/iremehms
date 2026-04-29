@php
    $es = $executiveSummary ?? [];
    $hotel = \App\Models\Hotel::getHotel();
@endphp

<div class="container-fluid py-4 pos-sales-report-page">
    @push('styles')
    <style>
        @media print {
            .pos-sales-report-page .no-print,
            .sidebar,
            .navbar.bg-light.sticky-top,
            .back-to-top { display: none !important; }
            .pos-sales-report-page .print-hidden { display: none !important; }
            .content { margin-left: 0 !important; width: 100% !important; }
            .pos-sales-report-page .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        }
    </style>
    @endpush

    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-3 no-print">
                    <div>
                        <h5 class="mb-1">POS sales report</h5>
                        <p class="text-muted small mb-0">
                            @if($this->isWaiterLikeReportLayout())
                                Summary totals at a glance, then every line item sold (compact list-friendly view).
                            @elseif($this->isStaffSalesReportUser() && !$this->canFilterByWaiter())
                                Your paid orders in the selected period (same breakdown style as front office payment reports).
                            @elseif($this->canFilterByWaiter())
                                Filter by waiter or view all. Export or print includes <strong>Prepared by</strong> and <strong>Verified by</strong>.
                            @else
                                Management report — read only.
                            @endif
                        </p>
                    </div>
                    @include('livewire.pos.partials.pos-quick-links', ['active' => 'reports'])
                </div>

                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-2 col-lg-2">
                                <label class="form-label small">From</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_from">
                            </div>
                            <div class="col-md-2 col-lg-2">
                                <label class="form-label small">To</label>
                                <input type="date" class="form-control form-control-sm" wire:model.defer="date_to">
                            </div>
                            @if($this->canFilterByWaiter())
                                <div class="col-md-3 col-lg-3">
                                    <label class="form-label small">Waiter</label>
                                    <select class="form-select form-select-sm" wire:model.live="waiter_filter">
                                        <option value="all">All waiters</option>
                                        @foreach($waitersForFilter as $w)
                                            <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if(!$this->isWaiterLikeReportLayout())
                            <div class="col-md-2 col-lg-2">
                                <label class="form-label small">Group by</label>
                                <select class="form-select form-select-sm" wire:model="group_by">
                                    <option value="day">Day</option>
                                    <option value="week">Week</option>
                                    <option value="month">Month</option>
                                    <option value="year">Year</option>
                                </select>
                            </div>
                            @endif
                            <div class="col-12 col-lg">
                                <label class="form-label small d-block">&nbsp;</label>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="applyFilter">Apply</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setToday">Today</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="exportCsv">
                                        <i class="fa fa-download me-1"></i>Export CSV
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                                        <i class="fa fa-print me-1"></i>Print
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mt-2 no-print">
                            <div class="col-12">
                                <label class="form-label small mb-0">Verified by <span class="text-muted">(optional, shown on print &amp; CSV)</span></label>
                                <input type="text" class="form-control form-control-sm" wire:model="verified_by_name" placeholder="Name for verification / sign-off">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label small mb-0">Approved by <span class="text-muted">(optional, shown on print &amp; CSV)</span></label>
                                <input type="text" class="form-control form-control-sm" wire:model="approved_by_name" placeholder="Name for approval/signature">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 border-success">
                    <div class="card-header fw-semibold">Food &amp; beverage sales <span class="text-muted fw-normal small">(from menu item sales category)</span></div>
                    <div class="card-body">
                        @if(count($salesByCategory ?? []) > 0)
                            <div class="row g-3">
                                @foreach($salesByCategory as $row)
                                    @php $lbl = ($row['sales_category'] ?? 'food') === 'beverage' ? 'Beverage' : 'Food'; @endphp
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 h-100 bg-light">
                                            <div class="text-muted small">{{ $lbl }}</div>
                                            <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format((float) ($row['total'] ?? 0)) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-muted small mb-0 mt-2">Assign each menu item to Food or Beverage under <strong>Menu items</strong> (Restaurant).</p>
                        @else
                            <p class="text-muted small mb-0">No paid order lines in this period.</p>
                        @endif
                    </div>
                </div>

                {{-- Print header (visible when printing) --}}
                <div class="mb-3 d-none d-print-block">
                    @if($hotel)
                        <x-hotel-document-header :hotel="$hotel" :subtitle="'POS sales report · '.$date_from.' to '.$date_to" />
                    @endif
                    @if($this->canFilterByWaiter() && $waiter_filter !== '' && $waiter_filter !== 'all')
                        @php
                            $wn = collect($waitersForFilter)->firstWhere('id', (int) $waiter_filter);
                        @endphp
                        @if($wn)
                            <div class="small">Waiter: <strong>{{ $wn['name'] }}</strong></div>
                        @endif
                    @elseif($this->isStaffSalesReportUser())
                        <div class="small">Waiter: <strong>{{ Auth::user()->name }}</strong></div>
                    @endif
                </div>

                @if($this->isWaiterLikeReportLayout())
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Sold items <span class="text-muted fw-normal small">(each line; date is when the order was marked paid)</span></div>
                    <div class="card-body p-0">
                        @if(count($soldLineItems) > 0)
                            @php
                                $linesTotal = collect($soldLineItems)->sum(fn ($r) => (float) ($r['line_total'] ?? 0));
                            @endphp
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Order #</th>
                                            <th>Item</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Unit price</th>
                                            <th class="text-end">Line total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($soldLineItems as $line)
                                            <tr>
                                                <td>{{ $line['sale_date'] }}</td>
                                                <td>{{ $line['order_id'] }}</td>
                                                <td>{{ $line['item_name'] }}</td>
                                                <td class="text-end">{{ $line['qty'] }}</td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($line['unit_price'] ?? 0) }}</td>
                                                <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($line['line_total'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light fw-semibold">
                                        <tr>
                                            <td colspan="5" class="text-end">Total</td>
                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($linesTotal) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0 p-3">No line items in this period.</p>
                        @endif
                    </div>
                </div>

                <div class="card border-info mb-4">
                    <div class="card-header bg-info text-white fw-semibold">Summary</div>
                    <div class="card-body">
                        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5">
                            <div class="col">
                                <div class="text-muted small">Orders (paid)</div>
                                <div class="fs-5 fw-bold">{{ $es['orders_count'] ?? 0 }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Total sales (incl. VAT)</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['total_sales'] ?? 0) }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Payments recorded</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['amount_received_total'] ?? 0) }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Assigned to rooms</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['assigned_room'] ?? 0) }}</div>
                            </div>
                            <div class="col">
                                <div class="text-muted small">Assigned to hotel</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['assigned_hotel_meeting'] ?? 0) }}</div>
                            </div>
                        </div>

                        <h6 class="text-muted small text-uppercase mb-2 mt-3">Payment methods</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Cash</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['cash'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">MoMo</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['momo'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">POS / Card</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['pos_card'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Bank</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['bank'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Pending</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['pending'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Debit / on account</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['debits'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6 mt-1">
                                <div class="border rounded p-2 h-100 bg-light">
                                    <div class="text-muted small">Offer (complimentary)</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['offer'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <p class="small text-muted mb-0">Room = guest folio; Hotel = internal / meeting coverage.</p>
                    </div>
                </div>

                @if($this->isCashierOnlyReportLayout())
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">By waiter</div>
                                <div class="card-body">
                                    @if(count($byWaiter) > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr><th>Waiter</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($byWaiter as $r)
                                                        <tr>
                                                            <td>{{ $r['waiter_name'] }}</td>
                                                            <td class="text-end">{{ $r['orders_count'] }}</td>
                                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total']) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted mb-0">No data.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">Sales by menu item</div>
                                <div class="card-body">
                                    @if(count($byMenuItem) > 0)
                                        @php
                                            $menuTotalAmount = collect($byMenuItem)->sum(fn ($r) => (float) ($r['total'] ?? 0));
                                        @endphp
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Menu item</th>
                                                        <th class="text-end">Sale price</th>
                                                        <th class="text-end">Qty sold</th>
                                                        <th class="text-end">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($byMenuItem as $r)
                                                        <tr>
                                                            <td>{{ $r['item_name'] }}</td>
                                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['sale_price'] ?? 0) }}</td>
                                                            <td class="text-end">{{ $r['qty'] }}</td>
                                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total'] ?? 0) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="3" class="text-end">Total amount</th>
                                                        <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($menuTotalAmount) }}</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted mb-0">No data.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @endif

                {{-- Executive summary (FO-style) — cashiers & management --}}
                @if(!$this->isWaiterLikeReportLayout())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-2 fw-semibold">Summary</div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="text-muted small">Orders (paid)</div>
                                <div class="fs-5 fw-bold">{{ $es['orders_count'] ?? 0 }}</div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="text-muted small">Total sales</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['total_sales'] ?? 0) }}</div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="text-muted small">Paid amount</div>
                                <div class="fs-5 fw-bold text-success">{{ \App\Helpers\CurrencyHelper::format($es['paid_amount'] ?? 0) }}</div>
                                <div class="small text-muted">Cash + MoMo + POS + Bank</div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="text-muted small">Not paid</div>
                                <div class="fs-5 fw-bold text-warning">{{ \App\Helpers\CurrencyHelper::format($es['not_paid_amount'] ?? 0) }}</div>
                                <div class="small text-muted">Pending + debit / on account</div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="text-muted small">Payments recorded</div>
                                <div class="fs-5 fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['amount_received_total'] ?? 0) }}</div>
                            </div>
                        </div>

                        <h6 class="text-muted small text-uppercase mb-2">By payment method</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Cash</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['cash'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">MoMo</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['momo'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">POS / Card</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['pos_card'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Bank</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['bank'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Pending</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['pending'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Debit / on account</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['debits'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">Offer</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['offer'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <h6 class="text-muted small text-uppercase mb-2">Assigned charges</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-4">
                                <div class="border rounded p-2 h-100 bg-light">
                                    <div class="text-muted small">Assigned to room (guest folio)</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['assigned_room'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="border rounded p-2 h-100 bg-light">
                                    <div class="text-muted small">Hotel / meeting covered</div>
                                    <div class="fw-bold">{{ \App\Helpers\CurrencyHelper::format($es['assigned_hotel_meeting'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Sign-off (print + CSV) — below detail sections for waiters --}}
                @if(!$this->isWaiterLikeReportLayout())
                <div class="card border mb-4">
                    <div class="card-body py-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="small text-muted mb-1">Prepared by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ Auth::user()->name }}</div>
                            </div>
                            <div class="col-md-4 mt-3 mt-md-0">
                                <div class="small text-muted mb-1">Verified by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ $verified_by_name !== '' ? $verified_by_name : '________________________' }}</div>
                            </div>
                            <div class="col-md-4 mt-3 mt-md-0">
                                <div class="small text-muted mb-1">Approved by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ $approved_by_name !== '' ? $approved_by_name : '________________________' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(!$this->isStaffSalesReportUser())
                <div class="row mb-4 no-print">
                    <div class="col-12">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">VAT (Rwanda 18%) — RRA remittance</div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">Sales are VAT-inclusive. Below is the VAT to remit to RRA for the selected period.</p>
                                @if($vatSummary)
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <strong>Total sales (incl. VAT)</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($vatSummary['total_sales']) }}</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Subtotal (net)</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($vatSummary['total_net']) }}</span>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>VAT ({{ (int)\App\Helpers\VatHelper::getVatRate() }}%) to remit to RRA</strong><br>
                                            <span class="fs-5 text-success">{{ \App\Helpers\CurrencyHelper::format($vatSummary['total_vat']) }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if(count($vatByMonth) > 0)
                                    <h6 class="mb-2">By month (for monthly filing)</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th class="text-end">Sales (incl. VAT)</th>
                                                    <th class="text-end">Net</th>
                                                    <th class="text-end">VAT to remit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($vatByMonth as $r)
                                                    <tr>
                                                        <td>{{ $r['month'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total_sales']) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total_net']) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total_vat']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0 small">No paid sales in this period.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(!$this->isWaiterLikeReportLayout())
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">Sales &amp; assignment (detail)</div>
                            <div class="card-body">
                                @if($assignmentSummary)
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <strong>Total sales (incl. VAT)</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($assignmentSummary['total_sales']) }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Amount received</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($assignmentSummary['amount_received']) }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Assigned to rooms</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($assignmentSummary['amount_assigned_to_rooms']) }}</span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Assigned to hotel</strong><br>
                                            <span class="fs-5">{{ \App\Helpers\CurrencyHelper::format($assignmentSummary['amount_assigned_to_hotel']) }}</span>
                                        </div>
                                    </div>
                                    <p class="small text-muted mb-0">Room = guest folio; Hotel = internal / meeting coverage.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(!$this->isWaiterLikeReportLayout())
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">Assignment details</div>
                            <div class="card-body p-0">
                                @if(count($assignmentDetails) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Invoice</th>
                                                    <th class="text-end">Amount</th>
                                                    <th>Type</th>
                                                    <th>Guest / Room / Checkout <span class="text-muted">or</span> Hotel (names &amp; reason)</th>
                                                    <th>Assigned by</th>
                                                    <th>Assigned at</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($assignmentDetails as $d)
                                                    <tr>
                                                        <td>{{ $d['invoice_number'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($d['total_amount']) }}</td>
                                                        <td>
                                                            @if($d['charge_type'] === 'room')
                                                                <span class="badge bg-primary">Room</span>
                                                            @else
                                                                <span class="badge bg-secondary">Hotel covered</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($d['charge_type'] === 'room')
                                                                {{ $d['guest_name'] }} · Room {{ $d['room_number'] }} · Checkout {{ $d['checkout'] }}
                                                            @else
                                                                {{ $d['hotel_covered_names'] }} · {{ $d['hotel_covered_reason'] }}
                                                            @endif
                                                        </td>
                                                        <td>{{ $d['assigned_by'] }}</td>
                                                        <td>{{ $d['assigned_at'] ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted small text-center py-3 mb-0">No invoices assigned to room or hotel in this period.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if(!$this->isWaiterLikeReportLayout())
                <div class="row">
                    <div class="col-lg-6">
                        @if(!$this->isWaiterLikeReportLayout())
                        <div class="card mb-4">
                            <div class="card-header">Sales over time</div>
                            <div class="card-body">
                                @if(count($dailySummary) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr><th>Period</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($dailySummary as $r)
                                                    <tr>
                                                        <td>{{ $r['period'] }}</td>
                                                        <td class="text-end">{{ $r['orders_count'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No data.</p>
                                @endif
                            </div>
                        </div>
                        @endif
                        <div class="card mb-4">
                            <div class="card-header">By waiter</div>
                            <div class="card-body">
                                @if(count($byWaiter) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr><th>Waiter</th><th class="text-end">Orders</th><th class="text-end">Total</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($byWaiter as $r)
                                                    <tr>
                                                        <td>{{ $r['waiter_name'] }}</td>
                                                        <td class="text-end">{{ $r['orders_count'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No data.</p>
                                @endif
                            </div>
                        </div>
                        @if(!$this->isStaffSalesReportUser())
                        <div class="card mb-4">
                            <div class="card-header">Profit summary (sales vs COGS)</div>
                            <div class="card-body">
                                @if($profitSummary)
                                    <p class="small text-muted">
                                        Based on sales for this period and cost of goods from stock movements.
                                    </p>
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <strong>Sales (incl. VAT)</strong><br>
                                            {{ \App\Helpers\CurrencyHelper::format($profitSummary['total_sales']) }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>COGS</strong><br>
                                            {{ \App\Helpers\CurrencyHelper::format($profitSummary['cogs']) }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Gross profit</strong><br>
                                            {{ \App\Helpers\CurrencyHelper::format($profitSummary['gross_profit']) }}
                                        </div>
                                    </div>
                                    <div>
                                        <strong>Gross margin</strong><br>
                                        {{ number_format(($profitSummary['gross_margin'] ?? 0) * 100, 1) }}%
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No data.</p>
                                @endif
                            </div>
                        </div>
                        @endif
                        <div class="card mb-4">
                            <div class="card-header">Payment type breakdown (all lines)</div>
                            <div class="card-body">
                                @if(count($byPaymentType ?? []) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr><th>Type</th><th class="text-end">Total</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($byPaymentType as $r)
                                                    <tr>
                                                        <td>{{ $r['label'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total']) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No data.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">Sales by menu item</div>
                            <div class="card-body">
                                @if(count($byMenuItem) > 0)
                                    @php
                                        $menuTotalAmount = collect($byMenuItem)->sum(fn ($r) => (float) ($r['total'] ?? 0));
                                    @endphp
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Menu item</th>
                                                    <th class="text-end">Sale price</th>
                                                    <th class="text-end">Qty sold</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($byMenuItem as $r)
                                                    <tr>
                                                        <td>{{ $r['item_name'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['sale_price'] ?? 0) }}</td>
                                                        <td class="text-end">{{ $r['qty'] }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['total'] ?? 0) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="3" class="text-end">Total amount</th>
                                                    <th class="text-end">{{ \App\Helpers\CurrencyHelper::format($menuTotalAmount) }}</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No data.</p>
                                @endif
                            </div>
                        </div>
                        @if(!$this->isStaffSalesReportUser())
                        <div class="card mb-4">
                            <div class="card-header">Stock impact from sales</div>
                            <div class="card-body">
                                @if(count($stockImpact) > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr><th>Stock item</th><th class="text-end">Qty out</th><th class="text-end">Cost value</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach($stockImpact as $r)
                                                    <tr>
                                                        <td>{{ $r['stock_name'] }}</td>
                                                        <td class="text-end">{{ number_format($r['qty_out'], 2) }}</td>
                                                        <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($r['cost_value'] ?? 0) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No stock movements from sales in this period.</p>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                @if($this->isWaiterLikeReportLayout())
                <div class="card border mb-4">
                    <div class="card-body py-3">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="small text-muted mb-1">Prepared by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ Auth::user()->name }}</div>
                            </div>
                            <div class="col-md-4 mt-3 mt-md-0">
                                <div class="small text-muted mb-1">Verified by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ $verified_by_name !== '' ? $verified_by_name : '________________________' }}</div>
                            </div>
                            <div class="col-md-4 mt-3 mt-md-0">
                                <div class="small text-muted mb-1">Approved by</div>
                                <div class="fw-semibold border-bottom pb-2">{{ $approved_by_name !== '' ? $approved_by_name : '________________________' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
