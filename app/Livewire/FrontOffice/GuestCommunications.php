<?php

namespace App\Livewire\FrontOffice;

use App\Models\GuestCommunication;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class GuestCommunications extends Component
{
    use ChecksModuleStatus;

    public string $date_from = '';
    public string $date_to = '';
    public array $guests = [];
    /** @var array<int,bool> */
    public array $selected = [];
    public ?int $selectedGuestId = null;
    public string $viewMode = 'inbox'; // inbox | compose
    public string $composeSubject = '';
    public string $composeMessage = '';
    /** @var array<int,bool> compose recipients - when in compose, which guests to send to */
    public array $composeRecipients = [];
    public bool $composeToAll = false;
    public bool $sending = false;

    /** When true, rendered inside Front Office Communications hub (no outer layout). */
    public bool $embedded = false;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! Auth::user()->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        $user = Auth::user();
        $canCommunicate = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();
        if (! $canCommunicate) {
            abort(403, 'You do not have permission to send guest communications.');
        }

        $hotel = Hotel::getHotel();
        $today = Carbon::now($hotel->getTimezone())->format('Y-m-d');
        $this->date_from = $today;
        $this->date_to = $today;
        $this->loadGuests();
    }

    public function updatedDateFrom(): void
    {
        if ($this->date_to && $this->date_from > $this->date_to) {
            $this->date_to = $this->date_from;
        }
        $this->loadGuests();
    }

    public function updatedDateTo(): void
    {
        if ($this->date_from && $this->date_to < $this->date_from) {
            $this->date_from = $this->date_to;
        }
        $this->loadGuests();
    }

    public function loadGuests(): void
    {
        $hotel = Hotel::getHotel();
        $from = $this->date_from ?: Carbon::now($hotel->getTimezone())->format('Y-m-d');
        $to = $this->date_to ?: $from;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
            $this->date_from = $from;
            $this->date_to = $to;
        }

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->with(['guestCommunications' => fn ($q) => $q->orderByDesc('sent_at')])
            ->orderBy('check_in_date')
            ->get();

        $this->guests = $reservations->map(function (Reservation $r) {
            $comms = $r->guestCommunications;
            $lastComm = $comms->first();
            $sentCount = $comms->where('status', GuestCommunication::STATUS_SENT)->count();
            $failedCount = $comms->where('status', GuestCommunication::STATUS_FAILED)->count();
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            return [
                'id' => $r->id,
                'guest_name' => $r->guest_name ?? '—',
                'guest_email' => $r->guest_email ?? '',
                'check_in_date' => $r->check_in_date?->format('Y-m-d') ?? '—',
                'nights' => $nights,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'last_subject' => $lastComm?->subject ?? null,
                'last_sent_at' => $lastComm?->sent_at?->format('M j, g:i A') ?? null,
                'last_status' => $lastComm?->status ?? null,
            ];
        })->toArray();

        $this->selected = [];
        if ($this->selectedGuestId && ! collect($this->guests)->contains('id', $this->selectedGuestId)) {
            $this->selectedGuestId = null;
        }
    }

    public function selectGuest(int $reservationId): void
    {
        $this->selectedGuestId = $reservationId;
        $this->viewMode = 'inbox';
    }

    public function openCompose(?int $toReservationId = null): void
    {
        $this->viewMode = 'compose';
        $this->composeSubject = '';
        $this->composeMessage = '';
        $this->composeRecipients = [];
        $this->composeToAll = false;

        foreach ($this->guests as $g) {
            if (! empty($g['guest_email'])) {
                $this->composeRecipients[$g['id']] = $toReservationId ? ($g['id'] === $toReservationId) : false;
            }
        }
    }

    public function toggleComposeRecipient(int $id): void
    {
        $this->composeRecipients[$id] = ! ($this->composeRecipients[$id] ?? false);
    }

    public function toggleComposeSelectAll(): void
    {
        $withEmail = collect($this->guests)->filter(fn ($g) => ! empty($g['guest_email']));
        $allSelected = $withEmail->every(fn ($g) => ($this->composeRecipients[$g['id']] ?? false));
        foreach ($withEmail as $g) {
            $this->composeRecipients[$g['id']] = ! $allSelected;
        }
    }

    public function cancelCompose(): void
    {
        $this->viewMode = 'inbox';
    }

    public function sendMessages(): void
    {
        $this->validate([
            'composeSubject' => 'required|string|max:255',
            'composeMessage' => 'required|string|max:5000',
        ]);

        $hotel = Hotel::getHotel();
        $recipientIds = $this->composeToAll
            ? collect($this->guests)->filter(fn ($g) => ! empty($g['guest_email']))->pluck('id')->all()
            : array_keys(array_filter($this->composeRecipients));

        if (empty($recipientIds)) {
            session()->flash('error', 'Select at least one guest to send a message.');
            return;
        }

        $recipients = collect($this->guests)
            ->whereIn('id', $recipientIds)
            ->filter(fn ($g) => ! empty($g['guest_email']))
            ->values();

        if ($recipients->isEmpty()) {
            session()->flash('error', 'Selected guests have no email addresses.');
            return;
        }

        $this->sending = true;
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $g) {
            $reservation = Reservation::find($g['id']);
            if (! $reservation) {
                continue;
            }
            try {
                Mail::raw($this->composeMessage, function ($mail) use ($g, $hotel) {
                    $mail->to($g['guest_email'], $g['guest_name'])
                        ->subject($this->composeSubject)
                        ->from($hotel->email ?? config('mail.from.address'), $hotel->name ?? config('mail.from.name'));
                });

                GuestCommunication::create([
                    'hotel_id' => $hotel->id,
                    'reservation_id' => $reservation->id,
                    'guest_email' => $g['guest_email'],
                    'guest_name' => $g['guest_name'],
                    'subject' => $this->composeSubject,
                    'body' => $this->composeMessage,
                    'status' => GuestCommunication::STATUS_SENT,
                    'sent_by' => Auth::id(),
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (\Throwable $e) {
                GuestCommunication::create([
                    'hotel_id' => $hotel->id,
                    'reservation_id' => $reservation->id,
                    'guest_email' => $g['guest_email'],
                    'guest_name' => $g['guest_name'],
                    'subject' => $this->composeSubject,
                    'body' => $this->composeMessage,
                    'status' => GuestCommunication::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                    'sent_by' => Auth::id(),
                    'sent_at' => now(),
                ]);
                $failed++;
            }
        }

        $this->sending = false;
        $this->viewMode = 'inbox';
        $this->composeSubject = '';
        $this->composeMessage = '';
        $this->loadGuests();

        $msg = [];
        if ($sent > 0) {
            $msg[] = "Sent to {$sent} guest(s)";
        }
        if ($failed > 0) {
            $msg[] = "{$failed} failed";
        }
        session()->flash('message', implode('. ', $msg) ?: 'No messages sent.');
    }

    public function getSelectedGuestMessagesProperty(): \Illuminate\Support\Collection
    {
        if (! $this->selectedGuestId) {
            return collect();
        }
        return GuestCommunication::where('reservation_id', $this->selectedGuestId)
            ->where('hotel_id', Hotel::getHotel()?->id)
            ->orderByDesc('sent_at')
            ->with('sender')
            ->get();
    }

    public function getSelectedGuestProperty(): ?array
    {
        if (! $this->selectedGuestId) {
            return null;
        }
        return collect($this->guests)->firstWhere('id', $this->selectedGuestId);
    }

    public function getSentCountProperty(): int
    {
        $ids = collect($this->guests)->pluck('id')->all();
        if (empty($ids)) {
            return 0;
        }
        return GuestCommunication::whereIn('reservation_id', $ids)
            ->where('status', GuestCommunication::STATUS_SENT)
            ->count();
    }

    public function getFailedCountProperty(): int
    {
        $ids = collect($this->guests)->pluck('id')->all();
        if (empty($ids)) {
            return 0;
        }
        return GuestCommunication::whereIn('reservation_id', $ids)
            ->where('status', GuestCommunication::STATUS_FAILED)
            ->count();
    }

    public function render()
    {
        $view = view('livewire.front-office.guest-communications');
        if ($this->embedded) {
            return $view;
        }

        return $view->layout('livewire.layouts.app-layout');
    }
}
