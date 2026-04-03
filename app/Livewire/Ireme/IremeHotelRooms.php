<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IremeHotelRooms extends Component
{
    public $hotel;

    /** 'rooms' | 'categories' — Categories = room types (add/edit room types). */
    public $tab = 'rooms';

    public function mount($hotel)
    {
        if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can manage hotel rooms from Ireme.');
        }
        $this->hotel = $hotel instanceof Hotel ? $hotel : Hotel::find($hotel);
        if (! $this->hotel) {
            session()->flash('error', 'Hotel not found.');
            $this->redirect(route('ireme.hotels.index'));
            return;
        }
        session()->put('current_hotel_id', $this->hotel->id);
        $this->tab = request()->get('tab', 'rooms');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function render()
    {
        $title = $this->tab === 'categories' ? 'Room types — ' . $this->hotel->name : 'Rooms — ' . $this->hotel->name;
        return view('livewire.ireme.ireme-hotel-rooms', ['hotel' => $this->hotel])
            ->layout('livewire.layouts.ireme-layout', ['title' => $title]);
    }
}
