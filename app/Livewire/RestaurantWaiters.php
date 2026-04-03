<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RestaurantWaiters extends Component
{
    public $waiters = [];
    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $canAccess = $user->isSuperAdmin() || $user->isManager()
            || ($user->getEffectiveRole() && $user->getEffectiveRole()->slug === 'restaurant-manager');
        if (!$canAccess) {
            abort(403, 'Unauthorized. Waiters list is for restaurant management.');
        }
        $this->loadWaiters();
    }

    public function loadWaiters()
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $this->waiters = collect();

            return;
        }

        $query = User::with('role')->activeInHotelWithRoleSlug($hotel->id, 'waiter');
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }
        $this->waiters = $query->orderBy('name')->get();
    }

    public function updatedSearch()
    {
        $this->loadWaiters();
    }

    public function render()
    {
        return view('livewire.restaurant-waiters')
            ->layout('livewire.layouts.app-layout');
    }
}
