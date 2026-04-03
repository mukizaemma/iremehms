<?php

namespace App\Livewire\Navigation\Concerns;

use Illuminate\Support\Facades\Auth;

trait EnsuresManagerHubAccess
{
    protected function ensureManagerHubAccess(): void
    {
        if (! Auth::user() || ! Auth::user()->canNavigateModules()) {
            $this->redirect(route('dashboard'));
        }
    }
}
