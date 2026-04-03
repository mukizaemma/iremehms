<?php

namespace App\Livewire\Public;

use App\Models\Hotel;
use App\Models\PreRegistration;
use App\Models\Reservation;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public pre-arrival registration. Supports individual or group, optional reservation/organization,
 * optional use of ID/passport details (upload + manual entry). Saves as PreRegistration(s).
 */
class SelfCheckIn extends Component
{
    use WithFileUploads;

    public ?Hotel $hotel = null;
    public string $registration_type = 'individual'; // individual | group
    public $group_size = 1;
    /** @var array<int, array{guest_name: string, guest_id_number: string, guest_country: string, guest_email: string, guest_phone: string, guest_profession: string, guest_stay_purpose: string, organization: string, private_notes: string, use_id_details: bool, id_document: mixed}> */
    public array $group_guests = [];
    public string $reservation_choice = 'none'; // none | select | type
    public string $reservation_reference = '';
    public string $guest_name = '';
    public string $guest_id_number = '';
    public string $guest_country = 'Rwanda';
    public string $guest_email = '';
    public string $guest_phone = '';
    public string $guest_profession = '';
    public string $guest_stay_purpose = '';
    public string $organization = '';
    public string $private_notes = '';
    public bool $use_id_details = false;
    public $id_document = null;
    public bool $submitted = false;
    public string $submitted_message = '';

    public function mount(): void
    {
        $hotelId = request('hotel');
        if ($hotelId && is_numeric($hotelId)) {
            $this->hotel = Hotel::find((int) $hotelId);
        }
        if (! $this->hotel) {
            $this->hotel = Hotel::first();
        }
        if (! $this->hotel) {
            abort(404, 'No hotel configured.');
        }
        $ref = (string) request('ref', '');
        if ($ref !== '') {
            $this->reservation_choice = 'type';
            $this->reservation_reference = $ref;
        }
        $this->syncGroupGuestsToSize();
    }

    public function addGroupGuest(): void
    {
        $this->group_size = max(1, (int) $this->group_size) + 1;
        $this->syncGroupGuestsToSize();
    }

    public function removeGroupGuest(int $index): void
    {
        if (count($this->group_guests) <= 1) {
            return;
        }
        array_splice($this->group_guests, $index, 1);
        $this->group_size = max(1, count($this->group_guests));
    }

    protected function makeEmptyGuest(): array
    {
        return [
            'guest_name' => '',
            'guest_id_number' => '',
            'guest_country' => 'Rwanda',
            'guest_email' => '',
            'guest_phone' => '',
            'guest_profession' => '',
            'guest_stay_purpose' => '',
            'organization' => '',
            'private_notes' => '',
            'use_id_details' => false,
            'id_document' => null,
        ];
    }

    protected function syncGroupGuestsToSize(): void
    {
        $size = max(1, (int) $this->group_size);
        $current = count($this->group_guests);
        if ($current < $size) {
            for ($i = $current; $i < $size; $i++) {
                $this->group_guests[] = $this->makeEmptyGuest();
            }
        } elseif ($current > $size) {
            $this->group_guests = array_slice($this->group_guests, 0, $size);
        }
    }

    public function updatedGroupSize(): void
    {
        $this->syncGroupGuestsToSize();
    }

    public function updatedRegistrationType(): void
    {
        if ($this->registration_type === 'group') {
            $this->syncGroupGuestsToSize();
        }
    }

    public function getReservationOptionsProperty(): array
    {
        if (! $this->hotel) {
            return [];
        }
        return Reservation::where('hotel_id', $this->hotel->id)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->where('check_in_date', '>=', now()->subDays(1)->format('Y-m-d'))
            ->where(function ($q) {
                $q->whereNotNull('group_name')->where('group_name', '!=', '');
            })
            ->orderByDesc('check_in_date')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->reservation_number,
                'label' => ($r->group_name ?: $r->reservation_number) . ' (' . $r->reservation_number . ')',
            ])
            ->toArray();
    }

    protected function resolveReservation(): ?int
    {
        $ref = $this->reservation_choice === 'select' || $this->reservation_choice === 'type'
            ? trim($this->reservation_reference)
            : '';
        if ($ref === '') {
            return null;
        }
        $res = Reservation::where('hotel_id', $this->hotel->id)
            ->where('reservation_number', $ref)
            ->first();
        return $res?->id;
    }

    protected function saveOneGuest(array $data, ?string $idPath, ?string $groupId): void
    {
        $reservationId = $this->resolveReservation();
        PreRegistration::create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $reservationId,
            'group_identifier' => $groupId,
            'reservation_reference' => trim($this->reservation_reference) ?: null,
            'guest_name' => $data['guest_name'],
            'guest_id_number' => $data['guest_id_number'] ?? null,
            'guest_country' => $data['guest_country'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_profession' => $data['guest_profession'] ?? null,
            'guest_stay_purpose' => $data['guest_stay_purpose'] ?? null,
            'organization' => $data['organization'] ?? null,
            'private_notes' => $data['private_notes'] ?? null,
            'id_document_path' => $idPath,
            'status' => PreRegistration::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    public function submit(): void
    {
        $hotel = $this->hotel;
        $reservationId = $this->resolveReservation();

        if ($this->registration_type === 'individual') {
            $this->validate([
                'guest_name' => 'required|string|min:2|max:255',
                'guest_id_number' => 'nullable|string|max:100',
                'guest_country' => 'nullable|string|max:100',
                'guest_email' => 'nullable|email|max:255',
                'guest_phone' => 'nullable|string|max:50',
                'guest_profession' => 'nullable|string|max:100',
                'guest_stay_purpose' => 'nullable|string|max:100',
                'organization' => 'nullable|string|max:200',
                'private_notes' => 'nullable|string|max:2000',
                'id_document' => 'nullable|image|max:5120',
            ], [], [
                'guest_name' => __('Full name'),
                'guest_id_number' => __('ID or passport number'),
                'guest_email' => __('Email address'),
                'guest_phone' => __('Phone number'),
            ]);
            $idPath = null;
            if ($this->id_document) {
                $idPath = $this->id_document->store('id_documents/' . $hotel->id, 'public');
            }
            $this->saveOneGuest([
                'guest_name' => $this->guest_name,
                'guest_id_number' => $this->guest_id_number,
                'guest_country' => $this->guest_country,
                'guest_email' => $this->guest_email,
                'guest_phone' => $this->guest_phone,
                'guest_profession' => $this->guest_profession,
                'guest_stay_purpose' => $this->guest_stay_purpose,
                'organization' => $this->organization,
                'private_notes' => $this->private_notes,
            ], $idPath, null);
        } else {
            $rules = [
                'group_guests' => 'required|array|min:1',
                'group_guests.*.guest_name' => 'required|string|min:2|max:255',
                'group_guests.*.guest_id_number' => 'nullable|string|max:100',
                'group_guests.*.guest_country' => 'nullable|string|max:100',
                'group_guests.*.guest_email' => 'nullable|email|max:255',
                'group_guests.*.guest_phone' => 'nullable|string|max:50',
                'group_guests.*.id_document' => 'nullable|image|max:5120',
            ];
            $this->validate($rules, [], [
                'group_guests.*.guest_name' => __('Full name'),
            ]);
            $groupId = (string) Str::uuid();
            foreach ($this->group_guests as $g) {
                $idPath = null;
                if (! empty($g['id_document']) && is_object($g['id_document'])) {
                    $idPath = $g['id_document']->store('id_documents/' . $hotel->id, 'public');
                }
                $this->saveOneGuest($g, $idPath, $groupId);
            }
        }

        $this->submitted = true;
        // Base reassurance message.
        $message = __('Your details have been saved securely. At reception we will only verify your ID or passport and confirm your room—if you registered ahead of time, your room may already be assigned. We use your contact information only for stay-related communication and do not share it with third parties.');
        // If guest tried to link to a reservation but we could not resolve it, add a soft note.
        if ($this->reservation_choice !== 'none' && ! $reservationId) {
            $message .= ' ' . __('We could not automatically match the reservation reference you provided, but the reception team will still assist you at check-in and confirm your booking or group.');
        }
        $this->submitted_message = $message;
        $this->reset(['guest_name', 'guest_id_number', 'guest_email', 'guest_phone', 'guest_profession', 'guest_stay_purpose', 'organization', 'private_notes', 'id_document', 'group_guests', 'group_size']);
        $this->group_size = 1;
        $this->group_guests = [];
        $this->syncGroupGuestsToSize();
    }

    public function render()
    {
        return view('livewire.public.self-check-in')->layout('layouts.guest');
    }
}
