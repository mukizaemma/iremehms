<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IremeHotelMenuItems extends Component
{
    public $hotel;

    public function mount($hotel)
    {
        if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can manage hotel menu items from Ireme.');
        }
        $this->hotel = $hotel instanceof Hotel ? $hotel : Hotel::find($hotel);
        if (! $this->hotel) {
            session()->flash('error', 'Hotel not found.');
            $this->redirect(route('ireme.hotels.index'));
            return;
        }
        session()->put('current_hotel_id', $this->hotel->id);
    }

    public function render()
    {
        return view('livewire.ireme.ireme-hotel-menu-items', ['hotel' => $this->hotel])
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Menu items — ' . $this->hotel->name]);
    }
}
