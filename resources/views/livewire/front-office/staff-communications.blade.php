<div class="container-fluid py-0" wire:poll.30s>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
        <div class="small text-muted">
            Internal messages between hotel staff. Recipients get a notification in the bell when you send.
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
        <div class="col-md-4 col-lg-3 border-end" style="max-height: 560px; overflow-y: auto;">
            <div class="list-group list-group-flush">
                @forelse($this->peers as $p)
                    <button type="button"
                            class="list-group-item list-group-item-action d-flex flex-column align-items-start py-3 {{ $selectedPeerId === $p['id'] ? 'active' : '' }}"
                            wire:click="selectPeer({{ $p['id'] }})">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <strong class="mb-1">{{ $p['name'] }}</strong>
                            @if(!empty($p['last_at_label']))
                                <small class="opacity-75">{{ $p['last_at_label'] }}</small>
                            @endif
                        </div>
                        <small class="text-truncate w-100 mb-1 {{ $selectedPeerId === $p['id'] ? '' : 'text-muted' }}">{{ $p['email'] }}</small>
                        @if(!empty($p['last_preview']))
                            <span class="small text-truncate w-100">{{ $p['last_preview'] }}</span>
                        @endif
                        @if(($p['unread'] ?? 0) > 0)
                            <span class="badge bg-primary mt-1">{{ $p['unread'] }} new</span>
                        @endif
                    </button>
                @empty
                    <div class="list-group-item text-muted text-center py-4">
                        No other active staff in this hotel yet.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-md-8 col-lg-9 d-flex flex-column" style="max-height: 560px;">
            @if($selectedPeerId && $this->selectedPeer)
                <div class="p-3 border-bottom flex-shrink-0">
                    <h6 class="mb-0">{{ $this->selectedPeer->name }}</h6>
                    <small class="text-muted">{{ $this->selectedPeer->email }}</small>
                </div>
                <div class="flex-grow-1 overflow-auto p-3" style="min-height: 200px;">
                    @foreach($this->threadMessages as $msg)
                        @php $mine = $msg->sender_id === auth()->id(); @endphp
                        <div class="mb-3 {{ $mine ? 'text-end' : '' }}">
                            <div class="d-inline-block text-start {{ $mine ? 'bg-primary text-white' : 'bg-light border' }} rounded px-3 py-2" style="max-width: 92%;">
                                @if($msg->subject)
                                    <div class="fw-semibold small mb-1">{{ $msg->subject }}</div>
                                @endif
                                <div class="small" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                                <div class="small mt-1 opacity-75">
                                    {{ $mine ? __('You') : $msg->sender->name }} · {{ $msg->created_at->format('M j, g:i A') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="p-3 border-top bg-light flex-shrink-0">
                    <div class="mb-2">
                        <label class="form-label small mb-0">{{ __('Subject') }} <span class="text-muted">({{ __('optional') }})</span></label>
                        <input type="text" class="form-control form-control-sm" wire:model="composeSubject" placeholder="{{ __('Quick note') }}">
                        @error('composeSubject')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-0">{{ __('Message') }}</label>
                        <textarea class="form-control" rows="4" wire:model="composeMessage" placeholder="{{ __('Write your message…') }}"></textarea>
                        @error('composeMessage')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="sendMessage" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="sendMessage"><i class="fa fa-paper-plane me-1"></i>{{ __('Send') }}</span>
                        <span wire:loading wire:target="sendMessage"><span class="spinner-border spinner-border-sm me-1"></span>{{ __('Sending…') }}</span>
                    </button>
                </div>
            @else
                <div class="d-flex flex-column align-items-center justify-content-center text-muted flex-grow-1 py-5 px-4">
                    <i class="fa fa-comments fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">{{ __('Select a colleague to view the conversation') }}</p>
                    <p class="small mb-0">{{ __('They will be notified in the header bell when you send a message.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
