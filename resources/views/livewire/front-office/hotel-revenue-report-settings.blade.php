<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 col-lg-10 mx-auto">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h5 class="mb-1 fw-bold">General report — revenue columns</h5>
                    <p class="text-muted small mb-0">
                        Choose which revenue lines appear on the <strong>daily</strong>, <strong>monthly</strong>, and <strong>summary</strong> general reports.
                        Turn off services your property does not offer (e.g. sauna). Disabled amounts are rolled into <strong>OTHER</strong> if that column is enabled.
                    </p>
                </div>
                <a href="{{ route('general.monthly-sales-summary') }}" class="btn btn-outline-secondary btn-sm">Back to reports</a>
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

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 48px;">On</th>
                                    <th style="width: 160px;">Bucket</th>
                                    <th>Column title (printed / PDF)</th>
                                    <th style="width: 110px;">Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lines as $index => $line)
                                    <tr wire:key="rrl-{{ $line['id'] ?? $index }}">
                                        <td>
                                            {{-- "other" must not use wire:model on a disabled checkbox: Livewire cannot sync disabled inputs, which clears the bound value on hydrate/reload. --}}
                                            @if(($line['bucket_key'] ?? '') === 'other')
                                                <input type="hidden" wire:model="lines.{{ $index }}.is_active" wire:key="rrl-other-val-{{ $line['id'] ?? $index }}">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" checked disabled aria-readonly="true">
                                                </div>
                                                <small class="text-muted">Required</small>
                                            @else
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" wire:key="rrl-on-{{ $line['id'] ?? $index }}"
                                                           wire:model="lines.{{ $index }}.is_active">
                                                </div>
                                            @endif
                                        </td>
                                        <td><code class="small">{{ $line['bucket_key'] ?? '' }}</code></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.label">
                                            @error('lines.'.$index.'.label')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" wire:model.blur="lines.{{ $index }}.sort_order" min="0" max="65000">
                                            @error('lines.'.$index.'.sort_order')
                                                <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p class="small text-muted mb-3">
                        <i class="fa fa-info-circle me-1"></i>
                        Map POS menu categories to these buckets under <strong>Menu → Categories</strong> (General report bucket). New buckets: <code>garden</code>, <code>outside_catering</code>.
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        <button type="button" class="btn btn-outline-danger" wire:click="resetToDefaults"
                                wire:confirm="Reset all columns to defaults and re-enable every line?">
                            Reset to defaults
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
