<div class="@unless($embedded)container-fluid py-4 @else py-0 @endunless">
    <div class="row">
        <div class="col-12">
            <div class="bg-white {{ $embedded ? 'border-0 rounded-0 shadow-none' : 'rounded shadow-sm border' }}">
                {{-- Header: New Email, date filter, sent/failed --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm" wire:click="openCompose">
                            <i class="fa fa-plus me-1"></i> New Email
                        </button>
                        @if($selectedGuestId)
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openCompose({{ $selectedGuestId }})">
                                <i class="fa fa-reply me-1"></i> Reply to Guest
                            </button>
                        @endif
                        <div class="d-flex align-items-center gap-2 ms-2">
                            <span class="badge bg-success">{{ $this->sentCount }} Sent</span>
                            <span class="badge bg-danger">{{ $this->failedCount }} Failed</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label small mb-0">From</label>
                        <input type="date" class="form-control form-control-sm" style="width: 140px;" wire:model.live="date_from">
                        <label class="form-label small mb-0">To</label>
                        <input type="date" class="form-control form-control-sm" style="width: 140px;" wire:model.live="date_to">
                    </div>
                </div>

                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show m-3 mb-0" role="alert">
                        {{ session('message') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show m-3 mb-0" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="row g-0" style="min-height: 480px;">
                    {{-- Left: Guest list (inbox) --}}
                    <div class="col-md-4 col-lg-3 border-end" style="max-height: 520px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            @forelse($guests as $g)
                                <button type="button"
                                        class="list-group-item list-group-item-action d-flex flex-column align-items-start py-3 {{ $selectedGuestId === $g['id'] ? 'active' : '' }}"
                                        wire:click="selectGuest({{ $g['id'] }})">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <strong class="mb-1">{{ $g['guest_name'] }}</strong>
                                        @if($g['last_sent_at'])
                                            <small class="opacity-75">{{ $g['last_sent_at'] }}</small>
                                        @endif
                                    </div>
                                    <small class="text-muted text-truncate w-100 mb-1">{{ $g['guest_email'] ?: 'No email' }}</small>
                                    @if($g['last_subject'])
                                        <span class="small text-truncate w-100">{{ $g['last_subject'] }}</span>
                                    @endif
                                    <div class="d-flex gap-1 mt-1">
                                        @if(($g['sent_count'] ?? 0) > 0)
                                            <span class="badge bg-success">{{ $g['sent_count'] }} sent</span>
                                        @endif
                                        @if(($g['failed_count'] ?? 0) > 0)
                                            <span class="badge bg-danger">{{ $g['failed_count'] }} failed</span>
                                        @endif
                                    </div>
                                </button>
                            @empty
                                <div class="list-group-item text-muted text-center py-4">
                                    No guests found for the selected date range.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Right: Guest detail + messages OR Compose --}}
                    <div class="col-md-8 col-lg-9" style="max-height: 520px; overflow-y: auto;">
                        @if($viewMode === 'compose')
                            {{-- Compose panel --}}
                            <div class="p-4">
                                <h6 class="mb-3"><i class="fa fa-paper-plane me-2"></i>New Message</h6>
                                <div class="mb-3">
                                    <label class="form-label small">To</label>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <label class="form-check form-check-inline">
                                            <input type="checkbox" class="form-check-input" wire:model.live="composeToAll">
                                            <span class="form-check-label">All guests with email</span>
                                        </label>
                                    </div>
                                    @if(!$composeToAll)
                                        <div class="border rounded p-2" style="max-height: 120px; overflow-y: auto;">
                                            @foreach($guests as $g)
                                                @if(!empty($g['guest_email']))
                                                    <label class="form-check d-block mb-1">
                                                        <input type="checkbox"
                                                               class="form-check-input"
                                                               wire:model.live="composeRecipients.{{ $g['id'] }}">
                                                        <span class="form-check-label">{{ $g['guest_name'] }} &lt;{{ $g['guest_email'] }}&gt;</span>
                                                    </label>
                                                @endif
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm p-0 mt-1" wire:click="toggleComposeSelectAll">
                                            Select all / Clear
                                        </button>
                                    @endif
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Subject</label>
                                    <input type="text" class="form-control" wire:model="composeSubject" placeholder="e.g. Thank you for staying with us">
                                    @error('composeSubject')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Message</label>
                                    <textarea class="form-control" rows="6" wire:model="composeMessage" placeholder="Write your message..."></textarea>
                                    @error('composeMessage')
                                        <span class="text-danger small">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary" wire:click="sendMessages" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="sendMessages"><i class="fa fa-paper-plane me-1"></i>Send</span>
                                        <span wire:loading wire:target="sendMessages"><span class="spinner-border spinner-border-sm me-1"></span>Sending...</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="cancelCompose">Cancel</button>
                                </div>
                            </div>
                        @elseif($selectedGuestId && $this->selectedGuest)
                            {{-- Guest detail + message thread --}}
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom">
                                    <div>
                                        <h6 class="mb-1">{{ $this->selectedGuest['guest_name'] }}</h6>
                                        <small class="text-muted">{{ $this->selectedGuest['guest_email'] }}</small>
                                        <div class="small mt-1">
                                            Check-in: {{ $this->selectedGuest['check_in_date'] }} · {{ $this->selectedGuest['nights'] }} night(s)
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="openCompose({{ $selectedGuestId }})">
                                        <i class="fa fa-reply me-1"></i> Send Message
                                    </button>
                                </div>

                                <h6 class="text-muted small text-uppercase mb-2">Communication history</h6>
                                @forelse($this->selectedGuestMessages as $msg)
                                    <div class="card mb-2 {{ $msg->status === 'failed' ? 'border-danger' : 'border' }}">
                                        <div class="card-body py-2 px-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="small">{{ $msg->subject }}</strong>
                                                <div class="d-flex align-items-center gap-1">
                                                    @if($msg->status === 'sent')
                                                        <span class="badge bg-success">Sent</span>
                                                    @else
                                                        <span class="badge bg-danger">Failed</span>
                                                    @endif
                                                    <small class="text-muted">{{ $msg->sent_at->format('M j, Y g:i A') }}</small>
                                                </div>
                                            </div>
                                            @if($msg->sender)
                                                <small class="text-muted d-block mb-1">By {{ $msg->sender->name }}</small>
                                            @endif
                                            @if($msg->status === 'failed' && $msg->error_message)
                                                <div class="alert alert-danger py-1 px-2 small mb-1">{{ $msg->error_message }}</div>
                                            @endif
                                            <div class="small text-break" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted small">No messages sent to this guest yet.</p>
                                @endforelse
                            </div>
                        @else
                            {{-- Empty state --}}
                            <div class="d-flex flex-column align-items-center justify-content-center text-muted py-5 px-4">
                                <i class="fa fa-envelope-open fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">Select a guest to view their communication history</p>
                                <p class="small mb-0">or click <strong>New Email</strong> to send a message</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
