<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use Livewire\Component;
use Livewire\WithPagination;

class IremeSubscriptions extends Component
{
    use WithPagination;

    public function render()
    {
        $hotels = Hotel::orderBy('hotel_code')->paginate(20);
        return view('livewire.ireme.ireme-subscriptions', ['hotels' => $hotels])
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Subscriptions']);
    }
}
