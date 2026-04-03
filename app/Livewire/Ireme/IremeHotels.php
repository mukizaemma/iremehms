<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use Livewire\Component;
use Livewire\WithPagination;

class IremeHotels extends Component
{
    use WithPagination;

    public $search = '';

    public function render()
    {
        $query = Hotel::query()->orderBy('hotel_code');
        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('hotel_code', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }
        $hotels = $query->paginate(15);
        return view('livewire.ireme.ireme-hotels', ['hotels' => $hotels])
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Hotels']);
    }
}
