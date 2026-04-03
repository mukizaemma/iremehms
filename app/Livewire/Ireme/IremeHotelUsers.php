<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class IremeHotelUsers extends Component
{
    public $hotel;
    public $showForm = false;
    public $name = '';
    public $email = '';
    public $password = '';
    public $role_id = '';

    public function mount($hotel)
    {
        $this->hotel = $hotel instanceof Hotel ? $hotel : Hotel::find($hotel);
        if (!$this->hotel) {
            session()->flash('error', 'Hotel not found.');
            $this->redirect(route('ireme.hotels.index'));
        }
    }

    public function addUser()
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            session()->flash('error', 'Only Ireme Super Admin can add or modify hotel users.');
            return;
        }
        $this->validate([
            'name' => 'required|string|min:2',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);
        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'hotel_id' => $this->hotel->id,
            'role_id' => $this->role_id,
            'is_active' => true,
        ]);
        $this->reset(['name', 'email', 'password', 'role_id', 'showForm']);
        session()->flash('message', 'User added.');
    }

    public function render()
    {
        $users = User::where('hotel_id', $this->hotel->id)->with('role')->orderBy('name')->get();
        $roles = Role::whereIn('slug', ['hotel-admin', 'director', 'general-manager', 'manager', 'receptionist', 'waiter', 'cashier', 'restaurant-manager', 'store-keeper', 'accountant', 'controller'])->orderBy('name')->get();
        return view('livewire.ireme.ireme-hotel-users', ['users' => $users, 'roles' => $roles])
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Hotel Users']);
    }
}
