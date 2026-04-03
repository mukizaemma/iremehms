<div class="container-fluid py-4">
    <div class="bg-light rounded p-4">
        <h5 class="mb-2">Activity log</h5>
        @if($isManagement ?? false)
            <p class="text-muted small mb-3">
                <strong>Management view:</strong> filter by <strong>module</strong>, <strong>dates</strong>, and optionally <strong>user</strong>. Results are paginated (50 per page) and use a database index on hotel + module + date — prefer one module at a time for very large ranges.
            </p>
        @else
            <p class="text-muted small mb-3">
                <strong>Your activity:</strong> actions you performed in this hotel. Pick a <strong>module</strong> to see only Front office, POS, or Stock — smaller slices load faster than “All modules” over long date ranges.
            </p>
        @endif

        @if(!$hotel)
            <div class="alert alert-warning">Hotel context is required.</div>
        @else
            <div class="card mb-4">
                <div class="card-body">
                    <form wire:submit.prevent="applyFilters" class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small mb-0">From</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="date_from">
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small mb-0">To</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="date_to">
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="form-label small mb-0">Module</label>
                            <select class="form-select form-select-sm" wire:model.live="filter_module">
                                <option value="">All modules</option>
                                @foreach($moduleLabels as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($isManagement ?? false)
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label small mb-0">User</label>
                                <select class="form-select form-select-sm" wire:model.live="filter_user_id">
                                    <option value="">All users</option>
                                    @foreach($userOptions as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label small mb-0">Search</label>
                            <input type="search" class="form-control form-control-sm" wire:model.live.debounce.400ms="search" placeholder="Action, details, model…">
                        </div>
                        <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setToday">
                                <i class="fa fa-calendar-day me-1"></i>Today
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="setLast7Days">
                                <i class="fa fa-calendar-week me-1"></i>Last 7 days
                            </button>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-filter me-1"></i>Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">When</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th class="d-none d-md-table-cell">Model</th>
                            <th class="d-none d-lg-table-cell">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="text-muted small text-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td class="small">
                                    @if($log->module && isset($moduleLabels[$log->module]))
                                        <span class="badge bg-secondary bg-opacity-25 text-dark">{{ $moduleLabels[$log->module] }}</span>
                                    @elseif($log->module)
                                        <span class="badge bg-light text-muted">{{ $log->module }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><code class="small">{{ $log->action }}</code></td>
                                <td class="small">{{ \Illuminate\Support\Str::limit($log->description ?? '—', 200) }}</td>
                                <td class="small d-none d-md-table-cell text-muted">
                                    @if($log->model_type)
                                        {{ class_basename($log->model_type) }}
                                        @if($log->model_id)
                                            #{{ $log->model_id }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small d-none d-lg-table-cell text-muted">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">No activity found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
