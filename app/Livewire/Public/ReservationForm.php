<?php

namespace App\Livewire\Public;

use App\Models\Hotel;
use Carbon\Carbon;
use Livewire\Component;

class ReservationForm extends Component
{
    public ?Hotel $hotel = null;
    public string $slug = '';

    public array $selectedRooms = [];
    public string $check_in = '';
    public string $check_out = '';
    public int $nights = 1;

    public string $guest_name = '';
    public string $guest_email = '';
    public string $guest_phone = '';
    public string $guest_address = '';
    public string $guest_country = '';
    public string $guest_special_requests = '';
    public bool $accept_terms = false;
    public bool $submitted = false;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->hotel = Hotel::where('public_slug', $slug)->first();
        if (! $this->hotel) {
            abort(404, 'Booking page not found.');
        }
        $this->check_in = request('check_in', Carbon::now()->format('Y-m-d'));
        $this->check_out = request('check_out', Carbon::now()->addDay()->format('Y-m-d'));
        $roomsParam = request('rooms');
        if (is_string($roomsParam)) {
            $decoded = json_decode($roomsParam, true);
            $this->selectedRooms = is_array($decoded) ? $decoded : [];
        } else {
            $this->selectedRooms = [];
        }
        $start = Carbon::parse($this->check_in);
        $end = Carbon::parse($this->check_out);
        $this->nights = max(1, (int) $start->diffInDays($end));
    }

    public function getTotalPriceProperty(): float
    {
        $total = 0.0;
        foreach ($this->selectedRooms as $r) {
            $total += ($r['price_per_night'] ?? 0) * ($r['qty'] ?? 1) * $this->nights;
        }
        return round($total, 2);
    }

    public function getCurrencySymbolProperty(): string
    {
        return $this->hotel ? $this->hotel->getCurrencySymbol() : '$';
    }

    public function submitBookingRequest(): void
    {
        $this->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email',
            'guest_phone' => 'nullable|string|max:100',
            'guest_address' => 'nullable|string|max:500',
            'guest_country' => 'nullable|string|max:100',
            'guest_special_requests' => 'nullable|string|max:1000',
            'accept_terms' => 'accepted',
        ]);
        // TODO: store booking request and/or send email to hotel
        $this->submitted = true;
        session()->flash('booking_message', 'Thank you. Your booking request has been sent. We will confirm availability and contact you shortly.');
    }

    public function render()
    {
        return view('livewire.public.reservation-form')
            ->layout('layouts.public', ['hotel' => $this->hotel]);
    }
}
