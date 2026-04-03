<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\ItemType;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ChecksModuleStatus;
use Livewire\Component;
use Livewire\WithPagination;

class StockMovements extends Component
{
    use WithPagination, ChecksModuleStatus;

    public $movements = [];
    public $stocks = [];
    public $departments = [];
    public $itemTypes = [];
    
    // Filters
    public $filter_stock = '';
    public $filter_movement_type = '';
    public $filter_department = '';
    public $filter_item_type = '';
    public $date_from = '';
    public $date_to = '';
    public $search = '';

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');
        
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        
        $this->stocks = Stock::with('itemType')->orderBy('name')->get();
        $deptQuery = Department::where('is_active', true);
        if (!empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->orderBy('name')->get();
        $this->itemTypes = ItemType::active()->get();
        $this->date_from = now()->subDays(30)->format('Y-m-d');
        $this->date_to = now()->format('Y-m-d');
        $this->loadMovements();
    }

    public function loadMovements()
    {
        $query = StockMovement::with([
            'stock.itemType',
            'stock.department',
            'user',
            'fromDepartment',
            'toDepartment',
            'shift'
        ]);

        if ($this->filter_stock) {
            $query->where('stock_id', $this->filter_stock);
        }

        if ($this->filter_movement_type) {
            $query->where('movement_type', $this->filter_movement_type);
        }

        if ($this->filter_department) {
            $query->where(function($q) {
                $q->whereHas('stock', function($sq) {
                    $sq->where('department_id', $this->filter_department);
                })
                ->orWhere('to_department_id', $this->filter_department)
                ->orWhere('from_department_id', $this->filter_department);
            });
        }

        if ($this->filter_item_type) {
            $query->whereHas('stock', function($q) {
                $q->where('item_type_id', $this->filter_item_type);
            });
        }

        if ($this->date_from) {
            $query->whereDate('business_date', '>=', $this->date_from);
        }

        if ($this->date_to) {
            $query->whereDate('business_date', '<=', $this->date_to);
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->whereHas('stock', function($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                })
                ->orWhereHas('user', function($uq) {
                    $uq->where('name', 'like', '%' . $this->search . '%');
                });
            });
        }

        $this->movements = $query->orderBy('business_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }

    public function updatedFilterStock()
    {
        $this->loadMovements();
    }

    public function updatedFilterMovementType()
    {
        $this->loadMovements();
    }

    public function updatedFilterDepartment()
    {
        $this->loadMovements();
    }

    public function updatedFilterItemType()
    {
        $this->loadMovements();
    }

    public function updatedDateFrom()
    {
        $this->loadMovements();
    }

    public function updatedDateTo()
    {
        $this->loadMovements();
    }

    public function updatedSearch()
    {
        $this->loadMovements();
    }

    public function render()
    {
        return view('livewire.stock-movements')->layout('livewire.layouts.app-layout');
    }
}
