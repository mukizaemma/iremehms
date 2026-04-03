<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @php
                    $sigQuery = [
                        'date_from' => $date_from,
                        'date_to' => $date_to,
                        'prepared_by' => $prepared_by_name,
                        'verified_by' => $verified_by_name,
                        'approved_by' => $approved_by_name,
                    ];
                @endphp

                <div class="mb-3 no-print">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h5 class="mb-0">Guests report</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('front-office.guests-report.export', $sigQuery) }}" class="btn btn-success btn-sm"><i class="fa fa-file-excel me-1"></i>Export to Excel</a>
                            <a href="{{ route('front-office.guests-report.print', $sigQuery) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fa fa-print me-1"></i>Print (A4)</a>
                        </div>
                    </div>
                    @include('livewire.front-office.partials.front-office-quick-nav')
                </div>

                <div class="card mb-3 no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small">From date</label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="date_from">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">To date</label>
                                <input type="date" class="form-control form-control-sm" wire:model.live="date_to">
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Default: today (in-house guests). Use a range to include all guests with a stay in that period.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 no-print border-primary border-opacity-25">
                    <div class="card-header bg-white py-2">
                        <h6 class="mb-0 small fw-semibold">Names on printed &amp; exported report</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            Defaults come from your hotel (if set by a manager) or your account name for <strong>Prepared by</strong>.
                            Change any field before printing or exporting for migration office or other official use.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Prepared by</label>
                                <input type="text" class="form-control form-control-sm" wire:model.live="prepared_by_name" maxlength="255" placeholder="Name" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Verified by</label>
                                <input type="text" class="form-control form-control-sm" wire:model.live="verified_by_name" maxlength="255" placeholder="Name" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-0">Approved by</label>
                                <input type="text" class="form-control form-control-sm" wire:model.live="approved_by_name" maxlength="255" placeholder="Name" autocomplete="off">
                            </div>
                        </div>
                        @if(Auth::user()->canNavigateModules() || Auth::user()->isSuperAdmin())
                            <div class="mt-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="saveSignatureDefaults" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveSignatureDefaults">Save as hotel defaults</span>
                                    <span wire:loading wire:target="saveSignatureDefaults">Saving…</span>
                                </button>
                                <span class="text-muted small ms-2">Pre-fills these three names for all staff on this report.</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        @if(count($guests) > 0)
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Guest Name</th>
                                            <th>Phone / Email</th>
                                            <th>ID/Passport Number</th>
                                            <th>Country</th>
                                            <th>Profession</th>
                                            <th>Stay Purpose</th>
                                            <th>Check-in Date</th>
                                            <th class="text-end">Number of Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($guests as $g)
                                            <tr>
                                                <td>{{ $g['guest_name'] }}</td>
                                                <td class="small text-break">{!! nl2br(e($g['phone_email'] ?? '—')) !!}</td>
                                                <td>{{ $g['guest_id_number'] }}</td>
                                                <td>{{ $g['guest_country'] }}</td>
                                                <td>{{ $g['guest_profession'] }}</td>
                                                <td>{{ $g['guest_stay_purpose'] }}</td>
                                                <td>{{ $g['check_in_date'] }}</td>
                                                <td class="text-end">{{ $g['nights'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted small mb-0 p-4">No guests found for the selected date range.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
