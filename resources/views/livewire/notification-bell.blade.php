<div class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle position-relative" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-bell fa-lg"></i>
        @if($this->unreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </a>
    <div class="dropdown-menu dropdown-menu-end shadow border-0 py-0" style="min-width: 320px; max-width: 360px;">
        <div class="dropdown-header d-flex justify-content-between align-items-center py-2 px-3 bg-light">
            <span class="fw-semibold">Notifications</span>
            @if($this->unreadCount > 0)
                <button type="button" class="btn btn-link btn-sm p-0 text-primary text-decoration-none" wire:click="markAllAsRead">
                    Mark all read
                </button>
            @endif
        </div>
        <div class="dropdown-divider my-0"></div>
        <div style="max-height: 320px; overflow-y: auto;" wire:poll.15s>
            @forelse($this->notifications as $notification)
                @php
                    $data = $notification->data;
                    $title = $data['title'] ?? 'Notification';
                    $message = $data['message'] ?? '';
                    $actionUrl = $data['action_url'] ?? null;
                    $isUnread = $notification->read_at === null;
                @endphp
                <a href="#"
                   class="dropdown-item py-3 {{ $isUnread ? 'bg-primary bg-opacity-10' : '' }}"
                   wire:click="markAsReadAndGo('{{ $notification->id }}')">
                    <div class="d-flex w-100">
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">{{ $title }}</div>
                            <div class="small text-muted text-break">{{ $message }}</div>
                            <div class="small text-muted mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                        @if($isUnread)
                            <span class="badge bg-primary rounded-pill align-self-center ms-2">New</span>
                        @endif
                    </div>
                </a>
                <div class="dropdown-divider my-0"></div>
            @empty
                <div class="dropdown-item py-4 text-center text-muted small">No notifications</div>
            @endforelse
        </div>
    </div>
</div>
