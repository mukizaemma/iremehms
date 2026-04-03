<?php

namespace App\Livewire\Navigation;

use App\Livewire\Navigation\Concerns\EnsuresManagerHubAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PosHub extends Component
{
    use EnsuresManagerHubAccess;

    public function mount(): void
    {
        $this->ensureManagerHubAccess();
        $modules = Auth::user()->getAccessibleModules();
        if (! $modules->contains('slug', 'restaurant')) {
            $this->redirect(route('dashboard'));
        }
        session(['selected_module' => 'restaurant']);
    }

    public function render()
    {
        $u = Auth::user();
        $showReceiptModification = $u->hasPermission('pos_approve_receipt_modification') || $u->canNavigateModules();

        return view('livewire.navigation.pos-hub', [
            'showReceiptModification' => $showReceiptModification,
        ])->layout('livewire.layouts.app-layout');
    }
}
