<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReservationDetails extends Component
{
    public ?Reservation $reservation = null;

    public function mount(string $reservation)
    {
        session(['selected_module' => 'front-office']);

        $hotel = Hotel::getHotel();
        $query = Reservation::where('hotel_id', $hotel->id);

        // Allow either numeric ID or reservation_number in the URL.
        if (is_numeric($reservation)) {
            $this->reservation = $query->where('id', (int) $reservation)->first();
        } else {
            $this->reservation = $query->where('reservation_number', $reservation)->first();
        }

        if (! $this->reservation) {
            abort(404, 'Reservation not found.');
        }
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $r = $this->reservation;
        $canCollectPayment = Auth::user()?->hasPermission('fo_collect_payment') ?? false;

        return view('livewire.front-office.reservation-details', [
            'hotel' => $hotel,
            'reservation' => $r,
            'canCollectPayment' => $canCollectPayment,
        ])->layout('livewire.layouts.app-layout');
    }
}

