<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationBell extends Component
{
    protected $listeners = ['notificationSent' => '$refresh'];

    public function getUnreadCountProperty(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }
        return $user->unreadNotifications()->count();
    }

    public function getNotificationsProperty()
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }
        return $user->notifications()->limit(20)->get();
    }

    public function markAsRead(string $id): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $user->notifications()->where('id', $id)->update(['read_at' => now()]);
        $this->dispatch('$refresh');
    }

    public function markAsReadAndGo(string $id): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->update(['read_at' => now()]);
            $data = $notification->data;
            $url = $data['action_url'] ?? route('pos.orders');
            $this->redirect($url, navigate: true);
            return;
        }
        $this->redirect(route('pos.orders'), navigate: true);
    }

    public function markAllAsRead(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $user->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('$refresh');
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
