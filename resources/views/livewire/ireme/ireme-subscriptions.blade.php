<div>
    <h5 class="mb-4">Subscriptions</h5>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hotel</th>
                        <th>Start date</th>
                        <th>Subscription type</th>
                        <th>Next due date</th>
                        <th>Amount</th>
                        <th>Remaining / Past due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hotels as $h)
                        @php
                            $nextDue = $h->next_due_date ? \Carbon\Carbon::parse($h->next_due_date) : null;
                            $today = \Carbon\Carbon::today();
                            $daysText = '—';
                            if ($nextDue) {
                                if ($nextDue->gte($today)) {
                                    $days = $today->diffInDays($nextDue, false);
                                    $daysText = $days . ' day' . ($days !== 1 ? 's' : '') . ' remaining';
                                } else {
                                    $days = $nextDue->diffInDays($today, false);
                                    $daysText = $days . ' day' . ($days !== 1 ? 's' : '') . ' past due';
                                }
                            }
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('ireme.subscriptions.show', $h) }}" class="text-primary fw-medium text-decoration-none">{{ $h->name }}</a>
                                @if($h->hotel_code)
                                    <br><small class="text-muted">#{{ $h->hotel_code }}</small>
                                @endif
                            </td>
                            <td>{{ $h->subscription_start_date ? $h->subscription_start_date->format('d M Y') : '—' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $h->subscription_type ?? '—')) }}</td>
                            <td>{{ $h->next_due_date ? $h->next_due_date->format('d M Y') : '—' }}</td>
                            <td>{{ $h->subscription_amount !== null ? number_format((float) $h->subscription_amount, 2) . ' ' . ($h->currency ?? 'RWF') : '—' }}</td>
                            <td>
                                @if($nextDue && $nextDue->lt($today))
                                    <span class="text-danger">{{ $daysText }}</span>
                                @else
                                    <span class="text-muted">{{ $daysText }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ ($h->subscription_status ?? 'active') === 'active' ? 'bg-success' : (($h->subscription_status ?? '') === 'past_due' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $h->subscription_status ?? 'active' }}</span>
                            </td>
                            <td>
                                <a href="{{ route('ireme.subscriptions.show', $h) }}" class="btn btn-sm btn-outline-primary">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No hotels.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $hotels->links() }}</div>
    </div>
</div>
