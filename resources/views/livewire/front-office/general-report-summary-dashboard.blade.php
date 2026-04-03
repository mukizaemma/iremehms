<div class="general-report-print-root">
    @push('styles')
        <style>
            @media print {
                @page {
                    size: A4 landscape;
                    margin: 10mm;
                }
                .no-print, .no-print * { display: none !important; }
                .general-report-print-root .card {
                    border: 0 !important;
                    box-shadow: none !important;
                }
                .general-report-print-root .card-header {
                    display: none !important;
                }
                .general-report-print-root .table-responsive {
                    overflow: visible !important;
                    max-width: 100% !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .general-report-print-root table {
                    min-width: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    table-layout: fixed;
                    font-size: 6.5pt;
                    border-collapse: collapse;
                }
                .general-report-print-root table th,
                .general-report-print-root table td {
                    min-width: 0 !important;
                    padding: 2px 3px !important;
                    word-wrap: break-word;
                    overflow-wrap: anywhere;
                }
            }
            thead th {
                background: #f8e7ad !important;
                border-color: #000 !important;
            }
            tfoot th {
                background: #f4b183 !important;
                border-color: #000 !important;
                font-weight: 700;
            }
            tbody td, tbody th, thead th, tfoot th { border-color: #000 !important; }
        </style>
    @endpush

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span><i class="fa fa-file-invoice me-2 text-primary"></i><strong>General report summary</strong></span>
            <span class="text-muted small">
                {{ $dateFrom }} → {{ $dateTo }} (last {{ $days }} days)
            </span>
        </div>

        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 p-3 no-print">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="fa fa-print me-1"></i>Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="exportCsv">
                        <i class="fa fa-file-csv me-1"></i>Export CSV
                    </button>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('front-office.general-report-settings') }}">
                        <i class="fa fa-columns me-1"></i>Report columns
                    </a>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('general.monthly-sales-summary', ['month' => $month]) }}">
                        <i class="fa fa-calendar me-1"></i>Monthly report
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('general.daily-sales-summary', ['date' => $dateTo]) }}">
                        <i class="fa fa-calendar-day me-1"></i>Daily general report
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('pos.reports', ['group_by' => 'month', 'date_from' => $monthFrom, 'date_to' => $monthTo]) }}">
                        <i class="fa fa-chart-bar me-1"></i>POS General report
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('front-office.reports') }}">
                        <i class="fa fa-bed me-1"></i>Accommodation &amp; guest insights
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('stock.opening-closing-report', ['datePreset' => 'range', 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]) }}">
                        <i class="fa fa-box-open me-1"></i>Stock closing report
                    </a>
                </div>
            </div>

            @php $hotelDoc = \App\Models\Hotel::getHotel(); @endphp
            @if($hotelDoc)
                <div class="px-3 pt-2 pb-3 border-bottom">
                    <x-hotel-document-header
                        :hotel="$hotelDoc"
                        :subtitle="'General report summary · '.$dateFrom.' → '.$dateTo.' (last '.$days.' days)'"
                    />
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered mb-0" style="min-width: 900px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">DATES</th>
                            @foreach($columnKeys as $k)
                                <th>{{ $columnLabels[$k] ?? strtoupper($k) }}</th>
                            @endforeach
                            <th>TOTAL REVENUES</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['date_label'] }}</td>
                                @foreach($columnKeys as $k)
                                    <td>{{ number_format((float) ($row[$k] ?? 0), 2) }}</td>
                                @endforeach
                                <td class="fw-bold">{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>GRAND TOTAL</th>
                            @foreach($columnKeys as $k)
                                <th>{{ number_format((float) ($totals[$k] ?? 0), 2) }}</th>
                            @endforeach
                            <th class="fw-bold">{{ number_format((float) ($totals['total'] ?? 0), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="p-3">
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="small text-muted mb-1">Prepared by</div>
                        <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                            {{ Auth::user()->name }}
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0">
                        <div class="small text-muted mb-1">Verified by</div>
                        <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                            {{ $verified_by_name !== '' ? $verified_by_name : '________________________' }}
                        </div>
                    </div>
                    <div class="col-md-4 mt-3 mt-md-0">
                        <div class="small text-muted mb-1">Approved by</div>
                        <div class="fw-semibold pb-2" style="border-bottom: 1px solid #000; min-height: 34px;">
                            {{ $approved_by_name !== '' ? $approved_by_name : '________________________' }}
                        </div>
                    </div>
                </div>

                <div class="row mt-3 no-print">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Verified by (optional)</label>
                        <input type="text" class="form-control form-control-sm" wire:model.defer="verified_by_name" placeholder="Name for verification/signature">
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <label class="form-label small mb-1">Approved by (optional)</label>
                        <input type="text" class="form-control form-control-sm" wire:model.defer="approved_by_name" placeholder="Name for approval/signature">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

