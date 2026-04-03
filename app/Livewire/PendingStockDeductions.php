<?php

namespace App\Livewire;

use App\Models\PendingStockDeduction;
use App\Services\StockDeductionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PendingStockDeductions extends Component
{
    public $filter_status = 'PENDING';

    public function mount()
    {
        $user = Auth::user();
        $storeModuleId = \App\Models\Module::where('slug', 'store')->first()?->id ?? 0;
        $canAccess = $user->isSuperAdmin() || $user->isManager() || $user->hasModuleAccess($storeModuleId);
        if (!$canAccess) {
            abort(403, 'Only Store Keeper, Manager and Super Admin can view pending stock deductions.');
        }
    }

    public function applyDeduction(int $id)
    {
        $pending = PendingStockDeduction::with(['stock', 'order'])->find($id);
        if (!$pending || $pending->status !== PendingStockDeduction::STATUS_PENDING) {
            session()->flash('error', 'Cannot apply this deduction.');
            return;
        }
        try {
            StockDeductionService::applyPendingDeduction($pending);
            session()->flash('message', 'Stock deduction applied.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function writeOff(int $id)
    {
        $pending = PendingStockDeduction::find($id);
        if (!$pending || $pending->status !== PendingStockDeduction::STATUS_PENDING) {
            session()->flash('error', 'Cannot write off this deduction.');
            return;
        }
        $pending->update([
            'status' => PendingStockDeduction::STATUS_WRITTEN_OFF,
            'notes' => trim(($pending->notes ?? '') . "\nWritten off at " . now()->toDateTimeString()),
        ]);
        session()->flash('message', 'Written off.');
    }

    public function getPendingQuery()
    {
        $query = PendingStockDeduction::with(['order', 'orderItem.menuItem', 'stock'])
            ->orderByDesc('created_at');

        if ($this->filter_status !== '') {
            $query->where('status', $this->filter_status);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.pending-stock-deductions', [
            'pendings' => $this->getPendingQuery()->paginate(20),
        ])->layout('livewire.layouts.app-layout');
    }
}
