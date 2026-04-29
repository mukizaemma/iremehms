<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\StockDailyReport;
use App\Models\StockLocation;
use Illuminate\Support\Collection;

/**
 * Daily category report scoped to a single sub-location (bar, kitchen store, etc.) with its own audit scope.
 */
class StockSellingLocationDailyReport extends StockCategoryDailyReport
{
    protected function requiresStockLocationSelection(): bool
    {
        return true;
    }

    protected function stockLocationsForPicker(): array
    {
        return StockLocation::active()
            ->where('is_main_location', false)
            ->whereNotNull('parent_location_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    protected function auditScopeKey(): string
    {
        return 'selling-location:'.(int) $this->stockLocationId;
    }

    protected function isSellingLocationReport(): bool
    {
        return true;
    }

    protected function recentApprovedReportsForView(): Collection
    {
        $hotel = Hotel::getHotel();
        if (! $this->auditEnabled || ! $hotel) {
            return collect();
        }

        return StockDailyReport::query()
            ->with(['approvedBy', 'verifiedBy', 'stockLocation'])
            ->where('hotel_id', $hotel->id)
            ->where('status', StockDailyReport::STATUS_APPROVED)
            ->where('scope_key', 'like', 'selling-location:%')
            ->orderByDesc('report_date')
            ->limit(14)
            ->get();
    }
}
