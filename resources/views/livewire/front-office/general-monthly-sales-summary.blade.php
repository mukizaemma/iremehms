<div class="container-fluid py-4 general-report-print-root">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 no-print">
                    <div>
                        <h5 class="mb-1 text-uppercase fw-bold">Monthly sales summary</h5>
                        <div class="text-muted small mb-0">
                            Hotel: <strong>{{ \App\Models\Hotel::getHotel()?->name ?? '' }}</strong>
                        </div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="d-flex flex-column">
                            <label class="form-label small mb-1">Month</label>
                            <input type="month" class="form-control form-control-sm" wire:model="month">
                        </div>
                        <div class="d-flex align-items-end">
                            <button type="button" class="btn btn-primary btn-sm" wire:click="applyFilters">
                                Apply
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2 no-print" onclick="window.print()">
                                <i class="fa fa-print me-1"></i>Print
                            </button>
                            <a href="{{ route('front-office.general-report-settings') }}" class="btn btn-outline-primary btn-sm ms-2 no-print">
                                <i class="fa fa-columns me-1"></i>Columns
                            </a>
                        </div>
                    </div>
                </div>

                @php
                    $hotel = \App\Models\Hotel::getHotel();
                    $tz = $hotel?->getTimezone() ?? 'UTC';
                    $monthStart = \Carbon\Carbon::parse(($month ?: now()->format('Y-m')).'-01', $tz);
                    $monthTitle = $monthStart->format('F Y');
                @endphp
                @if($hotel)
                    <div class="mb-3">
                        <x-hotel-document-header :hotel="$hotel" :subtitle="'Monthly sales summary · '.$monthTitle" />
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0" style="border-color: #000 !important;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 110px;">DATES</th>
                                @foreach($columnKeys as $k)
                                    <th class="text-center" style="min-width: 140px;">{{ $columnLabels[$k] ?? strtoupper($k) }}</th>
                                @endforeach
                                <th style="min-width: 140px;" class="text-center">TOTAL REVENUES</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayLabels as $d)
                                @php
                                    $label = $d['label'] ?? '';
                                    $ymd = $d['ymd'] ?? '';
                                    $row = $rows[$ymd] ?? null;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $label }}</td>
                                    @foreach($columnKeys as $k)
                                        <td class="text-end">
                                            {{ \App\Helpers\CurrencyHelper::format((float) ($row[$k] ?? 0)) }}
                                        </td>
                                    @endforeach
                                    <td class="text-end fw-semibold">
                                        {{ \App\Helpers\CurrencyHelper::format((float) ($row['total'] ?? 0)) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>TOTAL</th>
                                @foreach($columnKeys as $k)
                                    <th class="text-end">
                                        {{ \App\Helpers\CurrencyHelper::format((float) ($totals[$k] ?? 0)) }}
                                    </th>
                                @endforeach
                                <th class="text-end">
                                    {{ \App\Helpers\CurrencyHelper::format((float) ($totals['total'] ?? 0)) }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

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

@push('styles')
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            .no-print, .no-print * { display: none !important; }
            .general-report-print-root.container-fluid { padding: 0 !important; }
            .general-report-print-root .bg-light {
                background: #fff !important;
                padding: 0 !important;
            }
            .general-report-print-root .table-responsive {
                overflow: visible !important;
                max-width: 100% !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .general-report-print-root table {
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
        /* Template-like styling to better match the owner screenshots */
        thead th {
            background: #f8e7ad !important;
            border-color: #000 !important;
        }
        tfoot th {
            background: #f4b183 !important;
            border-color: #000 !important;
            font-weight: 700;
        }
        tbody td, tbody th, thead th, tfoot th {
            border-color: #000 !important;
        }
    </style>
@endpush

