<div class="bg-light rounded p-4">
    <h5 class="mb-4">Guest reviews – Approvals</h5>
    <p class="text-muted small mb-4">Approve or reject reviews submitted from the public page. Approved reviews appear on the site.</p>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    <tr>
                        <td>
                            <strong>{{ $review->guest_name }}</strong>
                            @if($review->guest_email)<br><small class="text-muted">{{ $review->guest_email }}</small>@endif
                        </td>
                        <td>{{ $review->rating }}/5</td>
                        <td class="small" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($review->comment, 80) }}</td>
                        <td class="small">{{ $review->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($review->is_approved)
                                <span class="badge bg-success">Approved</span>
                            @else
                                <span class="badge bg-secondary">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if(!$review->is_approved)
                                <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $review->id }})">Approve</button>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="reject({{ $review->id }})">Hide</button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="delete({{ $review->id }})" wire:confirm="Delete this review?">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No reviews yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
