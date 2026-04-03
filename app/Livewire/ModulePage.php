<?php

namespace App\Livewire;

use App\Models\Module;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ModulePage extends Component
{
    use ChecksModuleStatus;

    public $module;

    public function mount($module)
    {
        $moduleModel = Module::where('slug', $module)->firstOrFail();
        
        // PHASE 1: Check if module is enabled for the hotel (PM Warning: Every module must check feature status)
        $this->ensureModuleEnabled($moduleModel->slug);
        
        // Check if user has access to this module
        if (!Auth::user()->hasModuleAccess($moduleModel->id)) {
            abort(403, 'You do not have access to this module.');
        }

        $this->module = $moduleModel;
        
        // Store selected module in session
        session(['selected_module' => $module]);
    }

    public function render()
    {
        return view('livewire.module-page', [
            'module' => $this->module,
        ])->layout('livewire.layouts.app-layout');
    }
}
