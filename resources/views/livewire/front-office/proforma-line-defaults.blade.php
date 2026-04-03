<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="{{ route('front-office.proforma-invoices') }}" class="small text-muted text-decoration-none"><i class="fa fa-arrow-left me-1"></i> Proforma invoices</a>
            <h5 class="fw-bold mb-0 mt-1">Proforma — type defaults</h5>
            <p class="text-muted small mb-0">Managers configure default unit price and report column per type. Staff should not choose columns on each proforma.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" wire:click="save">Save</button>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Line type</th>
                            <th style="width:180px">Default unit price</th>
                            <th style="width:220px">Default report column</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lineTypes as $key => $label)
                            <tr wire:key="def-{{ $key }}">
                                <td>{{ $label }} <code class="small text-muted">{{ $key }}</code></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="prices.{{ $key }}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" wire:model.blur="buckets.{{ $key }}">
                                        @foreach($reportBucketOptions as $val => $lab)
                                            <option value="{{ $val }}">{{ $lab }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
