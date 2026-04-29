<?php

namespace App\Livewire;

use App\Helpers\ReportDatePreset;
use App\Models\Hotel;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Services\ActivityLogger;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StockLocationActivityReport extends Component
{
    use ChecksModuleStatus;

    public string $datePreset = ReportDatePreset::TODAY;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** Null = all locations */
    public ?int $stockLocationId = null;

    public string $verified_by_name = '';

    public string $approved_by_name = '';

    protected $queryString = [
        'datePreset' => ['except' => ReportDatePreset::TODAY],
        'dateFrom',
        'dateTo',
        'stockLocationId',
    ];

    public function mount(): void
    {
        $this->ensureModuleEnabled('store');

        $user = Auth::user();
        if (! $user || ! $user->canViewStockReports()) {
            abort(403, 'You do not have access to this stock report.');
        }

        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);

        ActivityLogger::log(
            'report_viewed',
            'Viewed Stock location activity report',
            self::class,
            null,
            null,
            [
                'date_preset' => $this->datePreset,
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'stock_location_id' => $this->stockLocationId,
            ],
            'store'
        );
    }

    public function applyPreset(): void
    {
        [$this->dateFrom, $this->dateTo] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
    }

    /**
     * @return array<int, array{
     *   location: \App\Models\StockLocation,
     *   rows: array<int, array<string, mixed>>,
     *   totals: array{in_value: float, out_value: float, net_value: float, movement_count: int},
     *   inventory_value: float
     * }>
     */
    public function getSections(): array
    {
        [$from, $to] = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);
        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            return [];
        }

        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $locationsQuery = StockLocation::query()->where('is_active', true)
            ->orderByDesc('is_main_location')
            ->orderBy('name');
        if ($this->stockLocationId) {
            $locationsQuery->whereKey($this->stockLocationId);
        }
        $locations = $locationsQuery->get();
        if ($locations->isEmpty()) {
            return [];
        }

        $movements = StockMovement::query()
            ->with(['stock.itemType', 'stock.stockLocation', 'user', 'fromDepartment', 'toDepartment'])
            ->whereBetween('business_date', [$from, $to])
            ->whereHas('stock', function ($q) use ($enabledDepartments, $locations) {
                $q->whereIn('stock_location_id', $locations->pluck('id'));
                if (! empty($enabledDepartments)) {
                    $q->where(function ($q2) use ($enabledDepartments) {
                        $q2->whereIn('department_id', $enabledDepartments)
                            ->orWhereNull('department_id');
                    });
                }
            })
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $byLocation = $movements->groupBy(fn (StockMovement $m) => $m->stock?->stock_location_id);

        $sections = [];
        foreach ($locations as $location) {
            $locMovements = $byLocation->get($location->id, collect())->filter(fn (StockMovement $m) => $m->stock !== null);
            $rows = $locMovements->map(fn (StockMovement $m) => $this->formatMovementRow($m))->values()->all();
            $sections[] = [
                'location' => $location,
                'rows' => $rows,
                'totals' => $this->summarizeMovements($locMovements),
                'inventory_value' => $this->inventoryValueAtLocation($location->id, $enabledDepartments),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int, array{totals: array{in_value: float, out_value: float, received_value?: float}, inventory_value: float}>  $sections
     * @return array{in_value: float, out_value: float, net_value: float, inventory_value_all: float, received_value_all: float}
     */
    protected function computeGrandTotalsFromSections(array $sections): array
    {
        $in = 0.0;
        $out = 0.0;
        $inv = 0.0;
        $recv = 0.0;
        foreach ($sections as $s) {
            $in += $s['totals']['in_value'];
            $out += $s['totals']['out_value'];
            $inv += $s['inventory_value'];
            $recv += $s['totals']['received_value'] ?? 0;
        }

        return [
            'in_value' => round($in, 2),
            'out_value' => round($out, 2),
            'net_value' => round($in - $out, 2),
            'inventory_value_all' => round($inv, 2),
            'received_value_all' => round($recv, 2),
        ];
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     * @return array{in_value: float, out_value: float, net_value: float, movement_count: int, received_qty_base: float, received_value: float}
     */
    protected function summarizeMovements(Collection $movements): array
    {
        $inValue = 0.0;
        $outValue = 0.0;
        foreach ($movements as $m) {
            $v = $this->movementFinancialValue($m);
            if ($v >= 0) {
                $inValue += $v;
            } else {
                $outValue += abs($v);
            }
        }
        $net = $movements->sum(fn (StockMovement $m) => $this->movementFinancialValue($m));

        $purchase = $movements->filter(fn (StockMovement $m) => $m->movement_type === 'PURCHASE');
        $receivedQtyBase = round($purchase->sum(fn (StockMovement $m) => abs((float) $m->quantity)), 2);
        $receivedValue = round($purchase->sum(fn (StockMovement $m) => $this->movementFinancialValue($m)), 2);

        return [
            'in_value' => round($inValue, 2),
            'out_value' => round($outValue, 2),
            'net_value' => round($net, 2),
            'movement_count' => $movements->count(),
            'received_qty_base' => $receivedQtyBase,
            'received_value' => $receivedValue,
        ];
    }

    protected function movementFinancialValue(StockMovement $m): float
    {
        $tv = $m->total_value;
        if ($tv !== null && (float) $tv !== 0.0) {
            return (float) $tv;
        }

        return (float) $m->quantity * (float) ($m->unit_price ?? 0);
    }

    /**
     * @param  array<int, int>  $enabledDepartments
     */
    protected function inventoryValueAtLocation(int $locationId, array $enabledDepartments): float
    {
        $q = Stock::query()->where('stock_location_id', $locationId);
        if (! empty($enabledDepartments)) {
            $q->where(function ($q2) use ($enabledDepartments) {
                $q2->whereIn('department_id', $enabledDepartments)
                    ->orWhereNull('department_id');
            });
        }

        return round($q->get()->sum(fn (Stock $s) => $s->purchaseLineValue()), 2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatMovementRow(StockMovement $m): array
    {
        $stock = $m->stock;
        if (! $stock) {
            return [
                'id' => $m->id,
                'date' => $m->business_date?->format('Y-m-d') ?? '',
                'type' => $m->movement_type,
                'direction' => '—',
                'item' => '(deleted stock)',
                'item_type' => '—',
                'qty_base' => 0,
                'base_unit' => '',
                'qty_package' => null,
                'package_unit' => '',
                'show_package' => false,
                'value' => round($this->movementFinancialValue($m), 2),
                'user' => $m->user->name ?? '—',
                'reference' => $m->notes ?? '',
            ];
        }

        $qty = (float) $m->quantity;
        $absBase = abs($qty);
        $pkgSize = (float) ($stock->package_size ?? 0);
        $pkgQty = $pkgSize > 0 ? $absBase / $pkgSize : null;
        $pkgUnit = $stock->package_unit ?? '';
        $baseUnit = $stock->qty_unit ?? $stock->unit ?? '';

        $fin = $this->movementFinancialValue($m);
        $direction = $qty > 0 ? 'IN' : ($qty < 0 ? 'OUT' : '—');

        $ref = $m->notes ?? '';
        if ($m->reason) {
            $ref = trim($ref.' '.$m->reason);
        }

        $deptHint = '';
        if ($m->fromDepartment || $m->toDepartment) {
            $deptHint = trim(
                ($m->fromDepartment ? 'From: '.$m->fromDepartment->name : '')
                .' '
                .($m->toDepartment ? 'To: '.$m->toDepartment->name : '')
            );
        }

        return [
            'id' => $m->id,
            'date' => $m->business_date?->format('Y-m-d') ?? '',
            'type' => $m->movement_type === 'PURCHASE' ? 'PURCHASE (received)' : $m->movement_type,
            'direction' => $direction,
            'item' => $stock->name ?? '—',
            'item_type' => $stock->itemType->name ?? '—',
            'qty_base' => round($absBase, 4),
            'base_unit' => $baseUnit,
            'qty_package' => $pkgQty !== null ? round($pkgQty, 4) : null,
            'package_unit' => $pkgUnit,
            'show_package' => $pkgSize > 0,
            'value' => round($fin, 2),
            'user' => $m->user->name ?? '—',
            'reference' => $ref !== '' ? $ref : $deptHint,
        ];
    }

    public function updatedDatePreset(): void
    {
        $this->applyPreset();
    }

    public function updatedStockLocationId(mixed $value): void
    {
        $this->stockLocationId = $value === null || $value === '' ? null : (int) $value;
    }

    public function render()
    {
        $sections = $this->getSections();
        $grandTotals = $this->computeGrandTotalsFromSections($sections);
        $dateRange = ReportDatePreset::apply($this->datePreset, $this->dateFrom, $this->dateTo);

        return view('livewire.stock-location-activity-report', [
            'sections' => $sections,
            'grandTotals' => $grandTotals,
            'datePresetOptions' => ReportDatePreset::options(),
            'dateRange' => $dateRange,
            'locationOptions' => StockLocation::active()->orderByDesc('is_main_location')->orderBy('name')->get(),
        ])->layout('livewire.layouts.app-layout');
    }
}
