<?php

namespace App\Services;

use App\Enums\InventoryCategory;
use App\Models\Hotel;
use App\Models\Stock;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockCategoryReportService
{
    /**
     * One summary row per inventory category for the given calendar day.
     *
     * @return list<array{category:string,label:string,opening_value:float,received_value:float,issued_value:float,closing_value:float}>
     */
    public function categorySummaryForDate(string $reportDate, ?int $stockLocationId = null): array
    {
        $date = Carbon::parse($reportDate)->startOfDay()->toDateString();

        $stocks = $this->scopedStocksQuery($stockLocationId)->get(['id', 'inventory_category', 'purchase_price', 'unit_price']);
        if ($stocks->isEmpty()) {
            return $this->emptySummaryRows();
        }

        $stockIds = $stocks->pluck('id')->all();
        $openingStats = $this->cumulativeQtyBefore($stockIds, $date);
        $dayMovements = $this->movementsOnDateGrouped($stockIds, $date);

        $totals = [];
        foreach (InventoryCategory::ordered() as $cat) {
            $totals[$cat->value] = [
                'opening_value' => 0.0,
                'received_value' => 0.0,
                'issued_value' => 0.0,
                'closing_value' => 0.0,
            ];
        }

        foreach ($stocks as $stock) {
            $cat = $stock->inventory_category ?? InventoryCategory::DryGoods->value;
            if (! isset($totals[$cat])) {
                $cat = InventoryCategory::DryGoods->value;
            }

            $unitCost = $this->unitCost($stock);
            $openRow = $openingStats->get($stock->id);
            $hasPriorMovements = (int) ($openRow->movement_count ?? 0) > 0;
            $openQty = $hasPriorMovements
                ? (float) ($openRow->qty ?? 0)
                : $this->fallbackOpeningQty($stock);
            $movements = $dayMovements->get($stock->id, collect());

            $receivedVal = 0.0;
            $issuedVal = 0.0;
            foreach ($movements as $m) {
                if ($stockLocationId === null && strtoupper((string) $m->movement_type) === 'TRANSFER') {
                    // All-locations view should not double count internal transfers as received/issued value.
                    continue;
                }
                $qty = (float) $m->quantity;
                $tv = $m->total_value;
                if ($tv === null) {
                    $tv = abs((float) $qty) * (float) ($m->unit_price ?? 0);
                } else {
                    $tv = abs((float) $tv);
                }
                if ($qty > 0) {
                    $receivedVal += $tv;
                } elseif ($qty < 0) {
                    $issuedVal += $tv;
                }
            }

            $dayNet = $movements->sum(fn ($m) => (float) $m->quantity);
            $closeQty = $openQty + $dayNet;

            $openVal = round($openQty * $unitCost, 2);
            $closeVal = round($closeQty * $unitCost, 2);

            $totals[$cat]['opening_value'] += $openVal;
            $totals[$cat]['received_value'] += round($receivedVal, 2);
            $totals[$cat]['issued_value'] += round($issuedVal, 2);
            $totals[$cat]['closing_value'] += $closeVal;
        }

        $out = [];
        foreach (InventoryCategory::ordered() as $cat) {
            $t = $totals[$cat->value];
            $out[] = [
                'category' => $cat->value,
                'label' => $cat->label(),
                'opening_value' => round($t['opening_value'], 2),
                'received_value' => round($t['received_value'], 2),
                'issued_value' => round($t['issued_value'], 2),
                'closing_value' => round($t['closing_value'], 2),
            ];
        }

        return $out;
    }

    /**
     * Line-level detail for one category and date (like spreadsheet category sheet).
     *
     * @return list<array{name:string,qty_unit:?string,opening_qty:float,opening_value:float,received_qty:float,received_value:float,issued_qty:float,issued_value:float,closing_qty:float,closing_value:float,unit_cost:float}>
     */
    public function linesForCategory(string $reportDate, string $categoryValue, ?int $stockLocationId = null): array
    {
        $enum = InventoryCategory::tryFrom($categoryValue);
        if (! $enum) {
            return [];
        }

        $date = Carbon::parse($reportDate)->startOfDay()->toDateString();

        $stocks = $this->scopedStocksQuery($stockLocationId)
            ->where('inventory_category', $enum->value)
            ->orderBy('name')
            ->get();

        if ($stocks->isEmpty()) {
            return [];
        }

        $stockIds = $stocks->pluck('id')->all();
        $openingStats = $this->cumulativeQtyBefore($stockIds, $date);
        $dayMovements = $this->movementsOnDateGrouped($stockIds, $date);

        $rows = [];
        foreach ($stocks as $stock) {
            $unitCost = $this->unitCost($stock);
            $openRow = $openingStats->get($stock->id);
            $hasPriorMovements = (int) ($openRow->movement_count ?? 0) > 0;
            $openQty = $hasPriorMovements
                ? (float) ($openRow->qty ?? 0)
                : $this->fallbackOpeningQty($stock);
            $movements = $dayMovements->get($stock->id, collect());

            $receivedQty = 0.0;
            $receivedVal = 0.0;
            $issuedQty = 0.0;
            $issuedVal = 0.0;
            foreach ($movements as $m) {
                if ($stockLocationId === null && strtoupper((string) $m->movement_type) === 'TRANSFER') {
                    // All-locations view should not show transfer IN/OUT as separate issue/receive lines.
                    continue;
                }
                $qty = (float) $m->quantity;
                $tv = $m->total_value;
                if ($tv === null) {
                    $tv = abs($qty) * (float) ($m->unit_price ?? 0);
                } else {
                    $tv = abs((float) $tv);
                }
                if ($qty > 0) {
                    $receivedQty += $qty;
                    $receivedVal += $tv;
                } elseif ($qty < 0) {
                    $issuedQty += abs($qty);
                    $issuedVal += $tv;
                }
            }

            $dayNet = $movements->sum(fn ($m) => (float) $m->quantity);
            $closeQty = $openQty + $dayNet;

            $rows[] = [
                'name' => $stock->name,
                'qty_unit' => $stock->qty_unit ?? $stock->unit ?? null,
                'opening_qty' => round($openQty, 4),
                'opening_value' => round($openQty * $unitCost, 2),
                'received_qty' => round($receivedQty, 4),
                'received_value' => round($receivedVal, 2),
                'issued_qty' => round($issuedQty, 4),
                'issued_value' => round($issuedVal, 2),
                'closing_qty' => round($closeQty, 4),
                'closing_value' => round($closeQty * $unitCost, 2),
                'unit_cost' => round($unitCost, 4),
            ];
        }

        return $this->mergeDuplicateItemLines($rows);
    }

    protected function scopedStocksQuery(?int $stockLocationId)
    {
        $hotel = Hotel::getHotel();
        $enabledDepartments = is_array($hotel->enabled_departments ?? null) ? $hotel->enabled_departments : [];

        $q = Stock::query()->with(['stockLocation']);

        if ($stockLocationId) {
            $q->where('stock_location_id', $stockLocationId);
        }

        if (! empty($enabledDepartments)) {
            $q->where(function ($sub) use ($enabledDepartments) {
                $sub->whereIn('department_id', $enabledDepartments)
                    ->orWhereNull('department_id');
            });
        }

        return $q;
    }

    /**
     * @param  list<int>  $stockIds
     */
    protected function cumulativeQtyBefore(array $stockIds, string $date): Collection
    {
        if ($stockIds === []) {
            return collect();
        }

        return StockMovement::query()
            ->whereIn('stock_id', $stockIds)
            ->where('business_date', '<', $date)
            ->select('stock_id', DB::raw('COALESCE(SUM(quantity), 0) as qty'), DB::raw('COUNT(*) as movement_count'))
            ->groupBy('stock_id')
            ->get()
            ->keyBy('stock_id');
    }

    /**
     * @param  list<int>  $stockIds
     * @return Collection<int, \Illuminate\Support\Collection<int, StockMovement>>
     */
    protected function movementsOnDateGrouped(array $stockIds, string $date): Collection
    {
        if ($stockIds === []) {
            return collect();
        }

        return StockMovement::query()
            ->whereIn('stock_id', $stockIds)
            ->whereDate('business_date', $date)
            ->orderBy('id')
            ->get()
            ->groupBy('stock_id');
    }

    protected function unitCost(Stock $stock): float
    {
        $p = (float) ($stock->purchase_price ?? 0);

        return $p > 0 ? $p : (float) ($stock->unit_price ?? 0);
    }

    protected function fallbackOpeningQty(Stock $stock): float
    {
        $beginning = (float) ($stock->beginning_stock_qty ?? 0);
        if ($beginning > 0) {
            return $beginning;
        }

        return (float) ($stock->current_stock ?? $stock->quantity ?? 0);
    }

    /**
     * @param  list<array{name:string,qty_unit:?string,opening_qty:float,opening_value:float,received_qty:float,received_value:float,issued_qty:float,issued_value:float,closing_qty:float,closing_value:float,unit_cost:float}>  $rows
     * @return list<array{name:string,qty_unit:?string,opening_qty:float,opening_value:float,received_qty:float,received_value:float,issued_qty:float,issued_value:float,closing_qty:float,closing_value:float,unit_cost:float}>
     */
    protected function mergeDuplicateItemLines(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row['name'])).'|'.strtolower(trim((string) ($row['qty_unit'] ?? '')));
            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }
            $merged[$key]['opening_qty'] += (float) $row['opening_qty'];
            $merged[$key]['opening_value'] += (float) $row['opening_value'];
            $merged[$key]['received_qty'] += (float) $row['received_qty'];
            $merged[$key]['received_value'] += (float) $row['received_value'];
            $merged[$key]['issued_qty'] += (float) $row['issued_qty'];
            $merged[$key]['issued_value'] += (float) $row['issued_value'];
            $merged[$key]['closing_qty'] += (float) $row['closing_qty'];
            $merged[$key]['closing_value'] += (float) $row['closing_value'];
        }

        return array_values(array_map(function (array $row): array {
            $closingQty = (float) $row['closing_qty'];
            $derivedUnitCost = $closingQty !== 0.0 ? ((float) $row['closing_value'] / $closingQty) : (float) $row['unit_cost'];

            $row['opening_qty'] = round((float) $row['opening_qty'], 4);
            $row['opening_value'] = round((float) $row['opening_value'], 2);
            $row['received_qty'] = round((float) $row['received_qty'], 4);
            $row['received_value'] = round((float) $row['received_value'], 2);
            $row['issued_qty'] = round((float) $row['issued_qty'], 4);
            $row['issued_value'] = round((float) $row['issued_value'], 2);
            $row['closing_qty'] = round((float) $row['closing_qty'], 4);
            $row['closing_value'] = round((float) $row['closing_value'], 2);
            $row['unit_cost'] = round($derivedUnitCost, 4);

            return $row;
        }, $merged));
    }

    /**
     * @return list<array{category:string,label:string,opening_value:float,received_value:float,issued_value:float,closing_value:float}>
     */
    protected function emptySummaryRows(): array
    {
        $out = [];
        foreach (InventoryCategory::ordered() as $cat) {
            $out[] = [
                'category' => $cat->value,
                'label' => $cat->label(),
                'opening_value' => 0.0,
                'received_value' => 0.0,
                'issued_value' => 0.0,
                'closing_value' => 0.0,
            ];
        }

        return $out;
    }
}
