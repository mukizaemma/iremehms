<div>
    <h5 class="mb-4">Support requests from hotels</h5>

    @if($filterHotel)
        <div class="alert alert-info py-2 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>Showing requests for <strong>{{ $filterHotel->name }}</strong></span>
            <a href="{{ route('ireme.requests.index') }}" class="btn btn-sm btn-outline-primary">Show all</a>
        </div>
    @endif

    @if($selected)
        <div class="mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="backToList"><i class="fa fa-arrow-left me-1"></i>Back to list</button>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><strong>{{ $selected->subject }}</strong> · {{ $selected->hotel->name ?? '—' }}</span>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $selected->status === 'resolved' ? 'success' : ($selected->status === 'in_progress' ? 'info' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $selected->status)) }}</span>
                    <div class="btn-group btn-group-sm">
                        @if($selected->status !== 'open')
                            <button type="button" class="btn btn-outline-secondary" wire:click="updateStatus('open')">Open</button>
                        @endif
                        @if($selected->status !== 'in_progress')
                            <button type="button" class="btn btn-outline-secondary" wire:click="updateStatus('in_progress')">In progress</button>
                        @endif
                        @if($selected->status !== 'resolved')
                            <button type="button" class="btn btn-outline-secondary" wire:click="updateStatus('resolved')">Resolved</button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">{{ $selected->user->name ?? 'Hotel' }} ({{ $selected->hotel->name }}) · {{ $selected->created_at->format('d M Y H:i') }}</p>
                <p class="mb-0">{{ $selected->message }}</p>
            </div>
        </div>

        @if($selected->responses->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-muted mb-2">Responses</h6>
                @foreach($selected->responses as $resp)
                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body py-2">
                            <p class="mb-0">{{ $resp->message }}</p>
                            <small class="text-muted">{{ $resp->user->name ?? 'Ireme' }} · {{ $resp->created_at->format('d M Y H:i') }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">Reply</div>
            <div class="card-body">
                <form wire:submit.prevent="sendReply">
                    <div class="mb-3">
                        <textarea class="form-control" wire:model="reply_message" rows="3" placeholder="Your response to the hotel..."></textarea>
                        @error('reply_message') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Send reply</button>
                </form>
            </div>
        </div>
        @return
    @endif

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted">Filter:</span>
        <button type="button" class="btn btn-sm {{ $status === '' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('status', '')">All</button>
        <button type="button" class="btn btn-sm {{ $status === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('status', 'open')">Open</button>
        <button type="button" class="btn btn-sm {{ $status === 'in_progress' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('status', 'in_progress')">In progress</button>
        <button type="button" class="btn btn-sm {{ $status === 'resolved' ? 'btn-primary' : 'btn-outline-secondary' }}" wire:click="$set('status', 'resolved')">Resolved</button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($requests->isEmpty())
                <p class="text-muted p-4 mb-0">No support requests yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Hotel</th>
                                <th>Subject</th>
                                <th>From</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $req)
                                <tr>
                                    <td>{{ $req->hotel->name ?? '—' }}</td>
                                    <td>{{ $req->subject }}</td>
                                    <td>{{ $req->user->name ?? '—' }}</td>
                                    <td>{{ $req->created_at->format('d M Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $req->status === 'resolved' ? 'success' : ($req->status === 'in_progress' ? 'info' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectRequest({{ $req->id }})">View & reply</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-2">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
