<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\StaffMessage;
use App\Models\User;
use App\Notifications\StaffMessageNotification;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class StaffCommunications extends Component
{
    use ChecksModuleStatus;

    #[Url(as: 'with')]
    public ?int $selectedPeerId = null;

    public string $composeSubject = '';

    public string $composeMessage = '';

    public bool $sending = false;

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $user = Auth::user();
        $canCommunicate = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();
        if (! $canCommunicate) {
            abort(403, 'You do not have permission to use staff messaging.');
        }
    }

    public function selectPeer(int $userId): void
    {
        $this->selectedPeerId = $userId;
        $this->markThreadRead();
    }

    public function updatedSelectedPeerId(?int $value): void
    {
        if ($value && ! $this->selectedPeer) {
            $this->selectedPeerId = null;

            return;
        }
        if ($value) {
            $this->markThreadRead();
        }
    }

    protected function markThreadRead(): void
    {
        $hotel = Hotel::getHotel();
        $me = Auth::user();
        if (! $hotel || ! $me || ! $this->selectedPeerId) {
            return;
        }

        $ids = StaffMessage::query()
            ->where('hotel_id', $hotel->id)
            ->where('sender_id', $this->selectedPeerId)
            ->where('recipient_id', $me->id)
            ->whereNull('read_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        StaffMessage::query()->whereIn('id', $ids)->update(['read_at' => now()]);

        $me->unreadNotifications()
            ->where('type', StaffMessageNotification::class)
            ->get()
            ->each(function ($n) use ($ids) {
                $mid = $n->data['staff_message_id'] ?? null;
                if ($mid && $ids->contains($mid)) {
                    $n->markAsRead();
                }
            });

        $this->dispatch('notificationSent');
    }

    public function getPeersProperty(): array
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return [];
        }
        $me = Auth::id();
        $users = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('id', '!=', $me)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $rows = [];
        foreach ($users as $u) {
            $last = StaffMessage::query()
                ->where('hotel_id', $hotel->id)
                ->where(function ($q) use ($me, $u) {
                    $q->where(function ($q2) use ($me, $u) {
                        $q2->where('sender_id', $me)->where('recipient_id', $u->id);
                    })->orWhere(function ($q2) use ($me, $u) {
                        $q2->where('sender_id', $u->id)->where('recipient_id', $me);
                    });
                })
                ->orderByDesc('created_at')
                ->first();

            $unread = StaffMessage::query()
                ->where('hotel_id', $hotel->id)
                ->where('sender_id', $u->id)
                ->where('recipient_id', $me)
                ->whereNull('read_at')
                ->count();

            $rows[] = [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'last_preview' => $last ? ($last->subject ?: Str::limit(strip_tags($last->body), 48)) : null,
                'last_at_ts' => $last?->created_at?->timestamp ?? 0,
                'last_at_label' => $last?->created_at?->format('M j, g:i A'),
                'unread' => $unread,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a['unread'] !== $b['unread']) {
                return $b['unread'] <=> $a['unread'];
            }

            return $b['last_at_ts'] <=> $a['last_at_ts'];
        });

        return $rows;
    }

    public function getThreadMessagesProperty(): \Illuminate\Support\Collection
    {
        $hotel = Hotel::getHotel();
        $me = Auth::id();
        if (! $hotel || ! $me || ! $this->selectedPeerId) {
            return collect();
        }

        return StaffMessage::query()
            ->where('hotel_id', $hotel->id)
            ->where(function ($q) use ($me) {
                $q->where(function ($q2) use ($me) {
                    $q2->where('sender_id', $me)->where('recipient_id', $this->selectedPeerId);
                })->orWhere(function ($q2) use ($me) {
                    $q2->where('sender_id', $this->selectedPeerId)->where('recipient_id', $me);
                });
            })
            ->orderBy('created_at')
            ->with(['sender:id,name', 'recipient:id,name'])
            ->get();
    }

    public function getSelectedPeerProperty(): ?User
    {
        if (! $this->selectedPeerId) {
            return null;
        }
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return null;
        }

        return User::query()
            ->where('hotel_id', $hotel->id)
            ->where('id', $this->selectedPeerId)
            ->first();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'composeSubject' => 'nullable|string|max:255',
            'composeMessage' => 'required|string|max:10000',
            'selectedPeerId' => 'required|integer',
        ]);

        $hotel = Hotel::getHotel();
        $me = Auth::id();
        if (! $hotel || ! $me) {
            session()->flash('error', 'Hotel context required.');

            return;
        }

        $recipient = User::query()
            ->where('hotel_id', $hotel->id)
            ->where('id', $this->selectedPeerId)
            ->where('id', '!=', $me)
            ->where('is_active', true)
            ->first();
        if (! $recipient) {
            session()->flash('error', 'Invalid recipient.');

            return;
        }

        $this->sending = true;

        $message = StaffMessage::create([
            'hotel_id' => $hotel->id,
            'sender_id' => $me,
            'recipient_id' => $recipient->id,
            'subject' => $this->composeSubject !== '' ? $this->composeSubject : null,
            'body' => $this->composeMessage,
            'read_at' => null,
        ]);

        $recipient->notify(new StaffMessageNotification($message));

        $this->composeSubject = '';
        $this->composeMessage = '';
        $this->sending = false;

        session()->flash('message', __('Message sent.'));
        $this->dispatch('notificationSent');
    }

    public function render()
    {
        return view('livewire.front-office.staff-communications');
    }
}
