<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Stock;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockRequisitions extends Component
{
    use ChecksModuleStatus;

    public $requisitions = [];
    public $stocks = [];
    public $departments = [];
    public $showRequisitionForm = false;
    public $requisitionType = 'new_purchase'; // new_purchase, from_substock, from_department

    public function mount()
    {
        $user = Auth::user();
        $isRestaurantManager = $user->getEffectiveRole() && $user->getEffectiveRole()->slug === 'restaurant-manager';
        if (!$isRestaurantManager) {
            $this->ensureModuleEnabled('store');
        }

        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $stockQuery = Stock::with(['itemType', 'department'])->orderBy('name');
        $deptQuery = Department::where('is_active', true);

        if (!empty($enabledDepartments)) {
            $stockQuery->whereIn('department_id', $enabledDepartments);
            $deptQuery->whereIn('id', $enabledDepartments);
        }

        $this->stocks = $stockQuery->get();
        $this->departments = $deptQuery->get();
    }

    public function render()
    {
        return view('livewire.stock-requisitions')->layout('livewire.layouts.app-layout');
    }
}
