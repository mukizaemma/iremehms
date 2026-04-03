<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\ItemType;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\TimeAndShiftResolver;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockDashboard extends Component
{
    use ChecksModuleStatus;

    public $totalItems = 0;
    public $totalValue = 0;
    public $lowStockItems = [];
    public $recentMovements = [];
    public $movementsByType = [];
    public $topItems = [];
    public $departmentFilter = null;
    public $itemTypeFilter = null;
    public $departments = [];
    public $itemTypes = [];

    public function mount()
    {
        // Check if store module is enabled
        $this->ensureModuleEnabled('store');
        
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel?->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $deptQuery = Department::where('is_active', true);
        if (!empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->orderBy('name')->get();
        $this->itemTypes = ItemType::active()->get();
        $this->loadDashboardData();
    }

    public function loadDashboardData()
    {
        $query = Stock::with(['itemType', 'department']);
        
        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }
        
        if ($this->itemTypeFilter) {
            $query->where('item_type_id', $this->itemTypeFilter);
        }

        // Total items and value
        $this->totalItems = $query->count();
        $this->totalValue = $query->sum(DB::raw('quantity * unit_price'));

        // Low stock items (below reorder level)
        $this->lowStockItems = $query->get()
            ->filter(function($stock) {
                return $stock->reorder_level && $stock->quantity <= $stock->reorder_level;
            })
            ->sortBy('quantity')
            ->take(10)
            ->values()
            ->toArray();

        // Recent movements (last 10)
        $movementQuery = StockMovement::with(['stock.itemType', 'stock.department', 'user', 'fromDepartment', 'toDepartment'])
            ->orderBy('business_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10);
        
        if ($this->departmentFilter) {
            $movementQuery->where(function($q) {
                $q->whereHas('stock', function($sq) {
                    $sq->where('department_id', $this->departmentFilter);
                })
                ->orWhere('to_department_id', $this->departmentFilter)
                ->orWhere('from_department_id', $this->departmentFilter);
            });
        }
        
        $this->recentMovements = $movementQuery->get()->toArray();

        // Movements by type (last 30 days)
        $this->movementsByType = StockMovement::where('business_date', '>=', now()->subDays(30)->toDateString())
            ->select('movement_type', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_quantity'))
            ->groupBy('movement_type')
            ->get()
            ->keyBy('movement_type')
            ->toArray();

        // Top items by value
        $this->topItems = $query->get()
            ->map(function($stock) {
                return [
                    'id' => $stock->id,
                    'name' => $stock->name,
                    'quantity' => $stock->quantity,
                    'unit_price' => $stock->unit_price,
                    'total_value' => $stock->quantity * ($stock->unit_price ?? 0),
                    'item_type' => $stock->itemType->name ?? 'N/A',
                    'department' => $stock->department->name ?? 'N/A',
                ];
            })
            ->sortByDesc('total_value')
            ->take(10)
            ->values()
            ->toArray();
    }

    public function updatedDepartmentFilter()
    {
        $this->loadDashboardData();
    }

    public function updatedItemTypeFilter()
    {
        $this->loadDashboardData();
    }

    public function render()
    {
        return view('livewire.stock-dashboard')->layout('livewire.layouts.app-layout');
    }
}
