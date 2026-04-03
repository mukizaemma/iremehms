<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Reset Actions page – Super Admin only. Irreversible data resets.
 */
class ResetActions extends Component
{
    public $showResetConfirmation = false;
    public $resetType = '';
    public $resetConfirmation = '';

    public function mount(): void
    {
        if (! Auth::user()->isEffectiveSuperAdmin()) {
            abort(403, 'Only Super Admin can access Reset Actions.');
        }
    }

    public function confirmReset(string $type): void
    {
        $this->resetType = $type;
        $this->showResetConfirmation = true;
        $this->resetConfirmation = '';
    }

    public function cancelReset(): void
    {
        $this->showResetConfirmation = false;
        $this->resetType = '';
        $this->resetConfirmation = '';
    }

    public function executeReset(): void
    {
        if ($this->resetConfirmation !== 'RESET') {
            session()->flash('error', 'Please type "RESET" to confirm this action.');
            return;
        }

        try {
            switch ($this->resetType) {
                case 'stocks':
                    $this->resetStocks();
                    break;
                case 'sales':
                    $this->resetSales();
                    break;
                case 'expenses':
                    $this->resetExpenses();
                    break;
                case 'hr':
                    $this->resetHR();
                    break;
                case 'activity_logs':
                    $this->resetActivityLogs();
                    break;
                case 'all':
                    $this->resetAll();
                    break;
            }

            $this->cancelReset();
            session()->flash('message', ucfirst(str_replace('_', ' ', $this->resetType)) . ' reset successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Reset failed: ' . $e->getMessage());
        }
    }

    protected function resetStocks(): void
    {
        if (Schema::hasTable('stocks')) {
            DB::table('stocks')->truncate();
        }
    }

    protected function resetSales(): void
    {
        if (Schema::hasTable('sales')) {
            DB::table('sales')->truncate();
        }
    }

    protected function resetExpenses(): void
    {
        if (Schema::hasTable('expenses')) {
            DB::table('expenses')->truncate();
        }
    }

    protected function resetHR(): void
    {
        if (Schema::hasTable('hr_data')) {
            DB::table('hr_data')->truncate();
        }
    }

    protected function resetActivityLogs(): void
    {
        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')->truncate();
        }
    }

    protected function resetAll(): void
    {
        if (Schema::hasTable('stocks')) {
            DB::table('stocks')->truncate();
        }
        if (Schema::hasTable('sales')) {
            DB::table('sales')->truncate();
        }
        if (Schema::hasTable('expenses')) {
            DB::table('expenses')->truncate();
        }
        if (Schema::hasTable('hr_data')) {
            DB::table('hr_data')->truncate();
        }
        if (Schema::hasTable('activity_logs')) {
            DB::table('activity_logs')->truncate();
        }
    }

    public function render()
    {
        return view('livewire.reset-actions')->layout('livewire.layouts.app-layout');
    }
}
