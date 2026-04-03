<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use Livewire\Component;

class IremeDashboard extends Component
{
    public function render()
    {
        $hotelsCount = Hotel::count();
        $activeCount = Hotel::where('subscription_status', 'active')->count();
        return view('livewire.ireme.ireme-dashboard', [
            'hotelsCount' => $hotelsCount,
            'activeCount' => $activeCount,
        ])->layout('livewire.layouts.ireme-layout', ['title' => 'Dashboard']);
    }
}
