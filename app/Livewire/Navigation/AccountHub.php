<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Livewire\Component;

class AccountHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();
    }

    public function render()
    {
        return view('livewire.navigation.account-hub')->layout('livewire.layouts.app-layout');
    }
}
