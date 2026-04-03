<?php

namespace App\Livewire\Public;

use App\Models\Hotel;
use App\Models\RoomType;
use Carbon\Carbon;
use Livewire\Component;

class RoomDetail extends Component
{
    public ?Hotel $hotel = null;
    public ?RoomType $roomType = null;
    public string $slug = '';
    public string $roomSlug = '';

    public string $tab = 'details'; // details | gallery | enquiry | map

    public string $enquiry_name = '';
    public string $enquiry_phone = '';
    public string $enquiry_email = '';
    public string $enquiry_check_in = '';
    public string $enquiry_check_out = '';
    public int $enquiry_adults = 2;
    public int $enquiry_children = 0;
    public int $enquiry_rooms = 1;
    public string $enquiry_message = '';
    public bool $enquiry_sent = false;

    public function mount(string $slug, string $roomSlug): void
    {
        $this->slug = $slug;
        $this->roomSlug = $roomSlug;
        $this->hotel = Hotel::where('public_slug', $slug)->first();
        if (! $this->hotel) {
            abort(404, 'Booking page not found.');
        }
        $this->roomType = RoomType::where('hotel_id', $this->hotel->id)
            ->where('is_active', true)
            ->where('slug', $roomSlug)
            ->with(['images', 'rates', 'amenities'])
            ->firstOrFail();
        $today = Carbon::now()->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $this->enquiry_check_in = $today;
        $this->enquiry_check_out = $tomorrow;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function getPricePerNightProperty(): ?float
    {
        $rate = $this->roomType->rates->first();
        return $rate ? (float) $rate->amount : null;
    }

    public function submitEnquiry(): void
    {
        $this->validate([
            'enquiry_name' => 'required|string|max:255',
            'enquiry_email' => 'required|email',
            'enquiry_phone' => 'nullable|string|max:50',
            'enquiry_check_in' => 'required|date',
            'enquiry_check_out' => 'required|date|after:enquiry_check_in',
            'enquiry_adults' => 'required|integer|min:1|max:20',
            'enquiry_children' => 'nullable|integer|min:0|max:10',
            'enquiry_rooms' => 'required|integer|min:1|max:10',
            'enquiry_message' => 'nullable|string|max:2000',
        ]);
        // Store or email (e.g. store in public_enquiries table or mail to hotel)
        $this->enquiry_sent = true;
        session()->flash('enquiry_message', 'Thank you. Your enquiry has been sent. We will get back to you shortly.');
    }

    public function getCurrencySymbolProperty(): string
    {
        return $this->hotel ? $this->hotel->getCurrencySymbol() : '$';
    }

    public function render()
    {
        return view('livewire.public.room-detail')
            ->layout('layouts.public', ['hotel' => $this->hotel]);
    }
}
