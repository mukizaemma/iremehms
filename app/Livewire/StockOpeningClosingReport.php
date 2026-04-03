<?php

namespace App\Livewire;

use App\Helpers\ReportDatePreset;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockOpeningClosingReport extends Component
{
    use ChecksModuleStatus, WithPagination;

    public string $datePreset = ReportDatePreset::TODAY;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?int $singleStockId = null; // when "by single item"
    public string $viewMode = 'by_stock'; // by_stock | by_single_item

    // Optional names printed on signature lines (shown only on print area)
    public string $verified_by_name = '';
    public string $approved_by_name = '';

    public $stockLocations = [];
    public $stocksForSelect = [];

    protected $queryString = ['datePreset' => ['except' => 'today'], 'dateFrom', 'dateTo', 'viewMode', 'singleStockId'];

    public function mount()
    {
        $this->ensureModuleEnabled('store');
        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $this->stockLocations = StockLocation::active()->orderBy('name')->get();
        $this->stocksForSelect = Stock::with('itemType')->orderBy('name')->get();
    }

    public function applyPreset()
    {
        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $this->resetPage();
    }

    public function getRows(): LengthAwarePaginator
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            return new Paginator([], 20, 1);
        }

        $stockQuery = Stock::with(['itemType', 'department', 'stockLocation']);
        if ($this->viewMode === 'by_single_item' && $this->singleStockId) {
            $stockQuery->where('id', $this->singleStockId);
        }
        $stocks = $stockQuery->orderBy('name')->get();
        if ($stocks->isEmpty()) {
            return new Paginator([], 20, 1);
        }

        $stockIds = $stocks->pluck('id')->toArray();
        $openingBefore = StockMovement::query()
            ->whereIn('stock_id', $stockIds)
            ->where('business_date', '<', $from)
            ->select('stock_id', DB::raw('sum(quantity) as qty'))
            ->groupBy('stock_id')
            ->pluck('qty', 'stock_id');

        $periodMovements = StockMovement::query()
            ->whereIn('stock_id', $stockIds)
            ->whereBetween('business_date', [$from, $to])
            ->select('stock_id', 'movement_type', DB::raw('sum(quantity) as qty'), DB::raw('sum(total_value) as val'))
            ->groupBy('stock_id', 'movement_type')
            ->get()
            ->groupBy('stock_id');

        $rows = [];
        foreach ($stocks as $stock) {
            $opening = (float) ($openingBefore->get($stock->id) ?? 0);
            $byType = $periodMovements->get($stock->id, collect())->keyBy('movement_type');
            $saleQty = abs((float) (optional($byType->get('SALE'))->qty ?? 0));
            $soldAmount = abs((float) (optional($byType->get('SALE'))->val ?? 0));
            $periodNet = $periodMovements->get($stock->id, collect())->sum('qty');
            $closing = $opening + (float) $periodNet;
            $unitPrice = (float) ($stock->unit_price ?? 0);
            $closingValue = $closing * $unitPrice;

            $rows[] = [
                'stock_id' => $stock->id,
                'stock_name' => $stock->name,
                'location_name' => $stock->stockLocation->name ?? '—',
                'unit_price' => $unitPrice,
                'opening' => round($opening, 2),
                'qty_sold' => round($saleQty, 2),
                'sold_amount' => round($soldAmount, 2),
                'closing' => round($closing, 2),
                'closing_value' => round($closingValue, 2),
            ];
        }

        $page = (int) request()->get('page', 1);
        $perPage = 20;
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    public function getTotalAmount(): float
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $stockQuery = Stock::query();
        if ($this->viewMode === 'by_single_item' && $this->singleStockId) {
            $stockQuery->where('id', $this->singleStockId);
        }
        $stockIds = $stockQuery->pluck('id')->toArray();
        if (empty($stockIds)) {
            return 0;
        }
        return (float) StockMovement::query()
            ->whereIn('stock_id', $stockIds)
            ->whereBetween('business_date', [$from, $to])
            ->where('movement_type', 'SALE')
            ->selectRaw('abs(sum(total_value)) as tot')
            ->value('tot') ?? 0;
    }

    public function getShareUrl(): string
    {
        return route('stock.opening-closing-report') . '?' . http_build_query([
            'datePreset' => $this->datePreset,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'viewMode' => $this->viewMode,
            'singleStockId' => $this->singleStockId,
        ]);
    }

    public function getShareText(): string
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        $total = $this->getTotalAmount();
        return sprintf(
            "Stock Opening & Closing Report %s to %s. Total sales amount: %s. View full report: %s",
            $from,
            $to,
            \App\Helpers\CurrencyHelper::format($total),
            $this->getShareUrl()
        );
    }

    public function updatedDatePreset()
    {
        $this->applyPreset();
    }

    public function updatedViewMode()
    {
        $this->resetPage();
    }

    public function updatedSingleStockId()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.stock-opening-closing-report', [
            'rows' => $this->getRows(),
            'totalAmount' => $this->getTotalAmount(),
            'datePresetOptions' => ReportDatePreset::options(),
            'dateRange' => ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo),
            'shareText' => $this->getShareText(),
        ])->layout('livewire.layouts.app-layout');
    }
}
