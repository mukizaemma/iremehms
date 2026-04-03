<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();
        $modules = Auth::user()->getAccessibleModules();
        if (! $modules->contains('slug', 'store')) {
            $this->redirect(route('dashboard'));
        }
        session(['selected_module' => 'store']);
    }

    public function render()
    {
        return view('livewire.navigation.stock-hub')->layout('livewire.layouts.app-layout');
    }
}
