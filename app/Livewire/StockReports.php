<?php

namespace App\Livewire;

use App\Enums\InventoryCategory;
use App\Models\Department;
use App\Models\ItemType;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockReports extends Component
{
    use ChecksModuleStatus;

    public $departments = [];

    public $itemTypes = [];

    public $reportType = 'summary';

    public $departmentFilter = null;

    public $itemTypeFilter = null;

    public $dateFrom = '';

    public $dateTo = '';

    // Report data
    public $summaryData = [];

    public $movementReport = [];

    public $wasteReport = [];

    public $valueReport = [];

    public function mount()
    {
        $this->ensureModuleEnabled('store');

        $user = \Illuminate\Support\Facades\Auth::user();
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];
        if (! $user->canViewStockReports()) {
            abort(403, 'You do not have access to stock reports.');
        }

        $deptQuery = Department::where('is_active', true);
        if (! empty($enabledDepartments)) {
            $deptQuery->whereIn('id', $enabledDepartments);
        }
        $this->departments = $deptQuery->get();
        $this->itemTypes = ItemType::active()->get();
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->reportType = 'summary';
        $this->loadReport();
    }

    public function loadReport()
    {
        switch ($this->reportType) {
            case 'summary':
                $this->loadSummaryReport();
                break;
            case 'movements':
                $this->loadMovementReport();
                break;
            case 'waste':
                $this->loadWasteReport();
                break;
            case 'value':
                $this->loadValueReport();
                break;
            default:
                $this->reportType = 'summary';
                $this->loadSummaryReport();
                break;
        }
    }

    public function loadSummaryReport()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $query = Stock::with(['itemType', 'department']);

        if (! empty($enabledDepartments)) {
            $query->whereIn('department_id', $enabledDepartments);
        }

        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->itemTypeFilter) {
            $query->where('item_type_id', $this->itemTypeFilter);
        }

        $rows = $query->get();

        $this->summaryData = [
            'total_items' => $rows->count(),
            'total_quantity' => $rows->sum(fn ($s) => $this->resolvedStockQty($s)),
            'total_value' => $rows->sum(function ($s) {
                $q = $this->resolvedStockQty($s);

                return $q * (float) ($s->purchase_price ?? $s->unit_price ?? 0);
            }),
            'low_stock_count' => $rows->filter(function ($stock) {
                return $stock->reorder_level && $stock->quantity <= $stock->reorder_level;
            })->count(),
            'by_item_type' => $rows->groupBy('item_type_id')->map(function ($stocks, $typeId) {
                $itemType = ItemType::find($typeId);

                return [
                    'name' => $itemType->name ?? 'Unknown',
                    'count' => $stocks->count(),
                    'total_value' => $stocks->sum(function ($s) {
                        $q = $this->resolvedStockQty($s);

                        return $q * (float) ($s->purchase_price ?? $s->unit_price ?? 0);
                    }),
                ];
            })->values()->toArray(),
            'by_inventory_category' => $rows
                ->groupBy(fn ($s) => $s->inventory_category ?? InventoryCategory::DryGoods->value)
                ->map(function ($stocks, $catKey) {
                    $cat = InventoryCategory::tryFrom((string) $catKey);

                    return [
                        'name' => $cat?->label() ?? (string) $catKey,
                        'count' => $stocks->count(),
                        'total_value' => $stocks->sum(function ($s) {
                            $q = $this->resolvedStockQty($s);

                            return $q * (float) ($s->purchase_price ?? $s->unit_price ?? 0);
                        }),
                    ];
                })
                ->sortKeys()
                ->values()
                ->toArray(),
        ];
    }

    public function loadMovementReport()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $query = StockMovement::with(['stock.itemType', 'stock.department'])
            ->whereBetween('business_date', [$this->dateFrom, $this->dateTo]);

        if (! empty($enabledDepartments)) {
            $query->whereHas('stock', function ($q) use ($enabledDepartments) {
                $q->whereIn('department_id', $enabledDepartments);
            });
        }

        if ($this->departmentFilter) {
            $query->where(function ($q) {
                $q->whereHas('stock', function ($sq) {
                    $sq->where('department_id', $this->departmentFilter);
                })
                    ->orWhere('to_department_id', $this->departmentFilter)
                    ->orWhere('from_department_id', $this->departmentFilter);
            });
        }

        if ($this->itemTypeFilter) {
            $query->whereHas('stock', function ($q) {
                $q->where('item_type_id', $this->itemTypeFilter);
            });
        }

        $this->movementReport = $query->select('movement_type', DB::raw('count(*) as count'), DB::raw('sum(quantity) as total_quantity'), DB::raw('sum(total_value) as total_value'))
            ->groupBy('movement_type')
            ->get()
            ->toArray();
    }

    public function loadWasteReport()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $query = StockMovement::with(['stock.itemType', 'stock.department', 'user'])
            ->where('movement_type', 'WASTE')
            ->whereBetween('business_date', [$this->dateFrom, $this->dateTo]);

        if (! empty($enabledDepartments)) {
            $query->whereHas('stock', function ($q) use ($enabledDepartments) {
                $q->whereIn('department_id', $enabledDepartments);
            });
        }

        if ($this->departmentFilter) {
            $query->whereHas('stock', function ($q) {
                $q->where('department_id', $this->departmentFilter);
            });
        }

        if ($this->itemTypeFilter) {
            $query->whereHas('stock', function ($q) {
                $q->where('item_type_id', $this->itemTypeFilter);
            });
        }

        $this->wasteReport = $query->orderBy('business_date', 'desc')
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'stock_name' => $movement->stock->name,
                    'item_type' => $movement->stock->itemType->name ?? 'N/A',
                    'quantity' => abs($movement->quantity),
                    'value' => abs($movement->total_value ?? 0),
                    'reason' => $movement->reason,
                    'user' => $movement->user->name,
                    'date' => $movement->business_date ? $movement->business_date->format('Y-m-d') : ($movement->created_at ? $movement->created_at->format('Y-m-d H:i') : ''),
                ];
            })
            ->toArray();
    }

    public function loadValueReport()
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $query = Stock::with(['itemType', 'department'])
            ->where('unit_price', '>', 0);

        if (! empty($enabledDepartments)) {
            $query->whereIn('department_id', $enabledDepartments);
        }

        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->itemTypeFilter) {
            $query->where('item_type_id', $this->itemTypeFilter);
        }

        $this->valueReport = $query->get()
            ->map(function ($stock) {
                return [
                    'id' => $stock->id,
                    'name' => $stock->name,
                    'item_type' => $stock->itemType->name ?? 'N/A',
                    'department' => $stock->department->name ?? 'N/A',
                    'quantity' => $stock->quantity,
                    'unit_price' => $stock->unit_price,
                    'total_value' => $stock->quantity * $stock->unit_price,
                ];
            })
            ->sortByDesc('total_value')
            ->values()
            ->toArray();
    }

    public function updatedReportType()
    {
        $this->loadReport();
    }

    public function updatedDepartmentFilter()
    {
        $this->loadReport();
    }

    public function updatedItemTypeFilter()
    {
        $this->loadReport();
    }

    public function updatedDateFrom()
    {
        $this->loadReport();
    }

    public function updatedDateTo()
    {
        $this->loadReport();
    }

    public function render()
    {
        return view('livewire.stock-reports')->layout('livewire.layouts.app-layout');
    }

    protected function resolvedStockQty(Stock $stock): float
    {
        $current = (float) ($stock->current_stock ?? 0);
        $legacy = (float) ($stock->quantity ?? 0);

        if ($current === 0.0 && $legacy !== 0.0) {
            return $legacy;
        }

        return $current;
    }
}
