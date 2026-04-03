<?php

namespace App\Livewire\Public;

use App\Models\Hotel;
use App\Models\HotelReview;
use App\Models\RoomType;
use Carbon\Carbon;
use Livewire\Component;

class BookingHome extends Component
{
    public ?Hotel $hotel = null;
    public string $slug = '';

    public string $check_in = '';
    public string $check_out = '';
    public int $adults = 2;
    public int $children = 0;

    /** true = Price for whole stay, false = Price per night */
    public bool $showPriceWholeStay = true;

    /** @var array [ ['room_type_id' => int, 'name' => string, 'slug' => string, 'qty' => int, 'price_per_night' => float, 'adults' => int, 'children' => int ] ] */
    public array $selectedRooms = [];

    public string $reviewGuestName = '';
    public string $reviewGuestEmail = '';
    public int $reviewRating = 5;
    public string $reviewComment = '';
    public bool $reviewSubmitted = false;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->hotel = Hotel::where('public_slug', $slug)->first();
        if (! $this->hotel) {
            abort(404, 'Booking page not found.');
        }
        $today = Carbon::now()->format('Y-m-d');
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $this->check_in = $this->check_in ?: $today;
        $this->check_out = $this->check_out ?: $tomorrow;
    }

    public function getRoomTypesProperty()
    {
        if (! $this->hotel) {
            return collect();
        }
        return RoomType::where('hotel_id', $this->hotel->id)
            ->where('is_active', true)
            ->with(['images', 'rates', 'amenities'])
            ->withCount(['rooms' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();
    }

    /** Price per night for a room type (first rate or null). */
    public function getPricePerNight(RoomType $roomType): ?float
    {
        $rate = $roomType->rates->first();
        return $rate ? (float) $rate->amount : null;
    }

    public function getNightsProperty(): int
    {
        if (! $this->check_in || ! $this->check_out) {
            return 1;
        }
        $start = Carbon::parse($this->check_in);
        $end = Carbon::parse($this->check_out);
        return max(1, (int) $start->diffInDays($end));
    }

    public function addRoom(int $roomTypeId): void
    {
        $roomType = RoomType::with('rates')->find($roomTypeId);
        if (! $roomType || $roomType->hotel_id !== $this->hotel->id) {
            return;
        }
        $slug = $roomType->slug ?: \Illuminate\Support\Str::slug($roomType->name);
        $price = $this->getPricePerNight($roomType);
        $existing = collect($this->selectedRooms)->firstWhere('room_type_id', $roomTypeId);
        if ($existing) {
            $key = array_search($existing, $this->selectedRooms);
            $this->selectedRooms[$key]['qty'] = ($this->selectedRooms[$key]['qty'] ?? 1) + 1;
        } else {
            $this->selectedRooms[] = [
                'room_type_id' => $roomType->id,
                'name' => $roomType->name,
                'slug' => $slug,
                'qty' => 1,
                'price_per_night' => $price ?? 0,
                'adults' => $this->adults,
                'children' => $this->children,
            ];
        }
        $this->selectedRooms = array_values($this->selectedRooms);
    }

    public function removeRoom(int $index): void
    {
        $arr = $this->selectedRooms;
        unset($arr[$index]);
        $this->selectedRooms = array_values($arr);
    }

    public function getTotalPriceProperty(): float
    {
        $total = 0.0;
        foreach ($this->selectedRooms as $r) {
            $total += ($r['price_per_night'] ?? 0) * ($r['qty'] ?? 1) * $this->nights;
        }
        return round($total, 2);
    }

    public function getCurrencyProperty(): string
    {
        return $this->hotel ? $this->hotel->getCurrency() : 'USD';
    }

    public function getCurrencySymbolProperty(): string
    {
        return $this->hotel ? $this->hotel->getCurrencySymbol() : '$';
    }

    /** Display price for a room type (whole stay or per night). */
    public function getDisplayPrice(RoomType $roomType): ?float
    {
        $perNight = $this->getPricePerNight($roomType);
        if ($perNight === null) {
            return null;
        }
        return $this->showPriceWholeStay ? round($perNight * $this->nights, 2) : $perNight;
    }

    public function togglePriceDisplay(): void
    {
        $this->showPriceWholeStay = ! $this->showPriceWholeStay;
    }

    public function submitReview(): void
    {
        $this->validate([
            'reviewGuestName' => 'required|string|max:255',
            'reviewGuestEmail' => 'nullable|email',
            'reviewRating' => 'required|integer|min:1|max:5',
            'reviewComment' => 'nullable|string|max:2000',
        ]);
        HotelReview::create([
            'hotel_id' => $this->hotel->id,
            'guest_name' => $this->reviewGuestName,
            'guest_email' => $this->reviewGuestEmail ?: null,
            'rating' => $this->reviewRating,
            'comment' => $this->reviewComment ?: null,
            'is_approved' => false,
        ]);
        $this->reviewGuestName = '';
        $this->reviewGuestEmail = '';
        $this->reviewRating = 5;
        $this->reviewComment = '';
        $this->reviewSubmitted = true;
        session()->flash('review_message', 'Thank you for your review. It will appear on the site after approval.');
    }

    public function render()
    {
        return view('livewire.public.booking-home')
            ->layout('layouts.public', ['hotel' => $this->hotel]);
    }
}
