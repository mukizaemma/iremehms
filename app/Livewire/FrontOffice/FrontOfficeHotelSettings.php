<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class FrontOfficeHotelSettings extends Component
{
    public $tab = 'contacts';

    public $reservation_contacts = '';
    public $public_slug = '';
    /** Base URL/domain for the public booking page (e.g. https://hotel.example.com). If empty, app URL is used. */
    public $public_booking_domain = '';
    public $public_url = '';
    /** Charge by: 'room_type' (default) or 'room' */
    public $charge_level = 'room_type';

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        if (! Auth::user()->isSuperAdmin() && ! Auth::user()->isManager()) {
            abort(403, 'Unauthorized.');
        }
        $tab = request()->get('tab', 'contacts');
        $this->tab = in_array($tab, ['contacts', 'pricing', 'public-urls'], true) ? $tab : 'contacts';
        $this->loadHotel();
    }

    protected function loadHotel(): void
    {
        $hotel = Hotel::getHotel();
        $this->reservation_contacts = $hotel->reservation_contacts ?? '';
        $this->public_slug = $hotel->public_slug ?? '';
        $this->public_booking_domain = $hotel->public_booking_domain ?? '';
        $this->charge_level = $hotel->charge_level ?? 'room_type';
        $this->public_url = $this->buildPublicBookingUrl();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function saveContacts(): void
    {
        Hotel::getHotel()->update(['reservation_contacts' => $this->reservation_contacts ?: null]);
        session()->flash('message', 'Reservation contacts saved.');
    }

    public function saveChargeLevel(): void
    {
        $this->validate(['charge_level' => 'required|in:room_type,room']);
        Hotel::getHotel()->update(['charge_level' => $this->charge_level]);
        session()->flash('message', 'Pricing level saved. ' . ($this->charge_level === 'room_type' ? 'Rates are set per room type.' : 'Rates are set per room.'));
    }

    /**
     * Build the full public booking URL: [domain]/booking/[hotelid]
     */
    protected function buildPublicBookingUrl(): string
    {
        if (! $this->public_slug) {
            return '';
        }
        $domain = trim($this->public_booking_domain ?? '');
        if ($domain && ! preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . $domain;
        }
        $base = $domain
            ? rtrim($domain, '/')
            : rtrim(config('app.url'), '/');
        return $base . '/booking/' . $this->public_slug;
    }

    public function savePublicUrlSettings(): void
    {
        $this->validate([
            'public_booking_domain' => 'nullable|string|max:512',
        ]);
        $domain = $this->public_booking_domain ? trim($this->public_booking_domain) : null;
        if ($domain !== null) {
            $domain = rtrim($domain, '/');
            if ($domain && ! preg_match('#^https?://#i', $domain)) {
                $domain = 'https://' . $domain;
            }
        }
        Hotel::getHotel()->update(['public_booking_domain' => $domain]);
        $this->public_booking_domain = $domain ?? '';
        $this->public_url = $this->buildPublicBookingUrl();
        session()->flash('message', 'Public booking URL settings saved.');
    }

    public function updatedPublicBookingDomain(): void
    {
        $this->public_url = $this->buildPublicBookingUrl();
    }

    public function generatePublicSlug(): void
    {
        $hotel = Hotel::getHotel();
        $slug = Str::lower(Str::random(12));
        while (Hotel::where('public_slug', $slug)->where('id', '!=', $hotel->id)->exists()) {
            $slug = Str::lower(Str::random(12));
        }
        $hotel->update(['public_slug' => $slug]);
        $this->public_slug = $slug;
        $this->public_url = $this->buildPublicBookingUrl();
        session()->flash('message', 'Public URL generated. Format: your domain/booking/hotel-id. You can set or change the domain above.');
    }

    public function render()
    {
        return view('livewire.front-office.front-office-hotel-settings')
            ->layout('livewire.layouts.app-layout');
    }
}
