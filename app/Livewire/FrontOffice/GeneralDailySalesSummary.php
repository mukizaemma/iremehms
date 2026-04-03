<?php

namespace App\Livewire\FrontOffice;

use App\Models\Hotel;
use App\Services\HotelRevenueReportColumnService;
use App\Services\SupplementalGeneralReportRevenueService;
use App\Support\GeneralReportPosBuckets;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class GeneralDailySalesSummary extends Component
{
    public string $date = '';
    public string $approved_by_name = '';
    public string $verified_by_name = '';

    /** Column keys mapped to UI labels (order preserved in $columnKeys). */
    public array $columnKeys = [];

    public array $columnLabels = [];

    /**
     * @var array<string, float>
     */
    public array $row = [];

    /**
     * @var array<string, float>
     */
    public array $totals = [];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context not found.');
        }

        $effectiveSlug = $user->getEffectiveRole()?->slug;
        $allowed = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('reports_view_all')
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->isRestaurantManager()
            || $user->isManager()
            || ($effectiveSlug === 'manager')
            || $user->isEffectiveGeneralManager()
            || $user->isIremeAccountant();

        if (! $allowed) {
            abort(403, 'You do not have access to the general report.');
        }

        $this->date = request('date', Hotel::getTodayForHotel());
        $this->loadReport();
    }

    public function applyFilters(): void
    {
        $this->loadReport();
    }

    protected function loadReport(): void
    {
        $hotel = Hotel::getHotel();
        $tz = $hotel->getTimezone();

        $dateStart = Carbon::parse($this->date, $tz)->startOfDay();
        $dateEnd = $dateStart->copy()->endOfDay();
        $ymd = $dateStart->format('Y-m-d');

        $cols = HotelRevenueReportColumnService::getActiveColumns($hotel);
        $this->columnKeys = $cols['keys'];
        $this->columnLabels = $cols['labels'];

        $this->row = array_fill_keys($this->columnKeys, 0.0);
        $this->row['total'] = 0.0;

        $this->totals = $this->row;

        // Rooms revenue (accommodation payments) for the day.
        $roomsAmt = (float) DB::table('reservation_payments')
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereDate('received_at', $ymd)
            ->sum('amount');
        $roomsCol = HotelRevenueReportColumnService::mapBucketToActiveColumn('rooms', $this->columnKeys);
        $this->row[$roomsCol] = $roomsAmt;
        $hasPosBucketColumn = Schema::hasColumn('menu_categories', 'pos_report_column_key');

        // Restaurant POS revenue for the day by menu category.
        $posDailyQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.menu_item_id')
            ->leftJoin('menu_categories', 'menu_items.category_id', '=', 'menu_categories.category_id')
            ->leftJoin('menu_item_types', 'menu_items.menu_item_type_id', '=', 'menu_item_types.type_id')
            ->whereNull('order_items.voided_at')
            ->where('orders.order_status', 'PAID')
            ->whereBetween('orders.updated_at', [$dateStart, $dateEnd])
            ->join('users', 'orders.waiter_id', '=', 'users.id')
            ->where('users.hotel_id', $hotel->id);

        if ($hasPosBucketColumn) {
            $posDailyQuery
                ->groupBy(
                    'menu_categories.pos_report_column_key',
                    'menu_categories.name',
                    'menu_item_types.name',
                    'menu_item_types.code'
                )
                ->select(
                    'menu_categories.name as category_name',
                    'menu_categories.pos_report_column_key as pos_bucket_key',
                    'menu_item_types.name as type_name',
                    'menu_item_types.code as type_code',
                    DB::raw('SUM(order_items.line_total) as total')
                );
        } else {
            $posDailyQuery
                ->groupBy('menu_categories.name', 'menu_item_types.name', 'menu_item_types.code')
                ->select(
                    'menu_categories.name as category_name',
                    DB::raw('NULL as pos_bucket_key'),
                    'menu_item_types.name as type_name',
                    'menu_item_types.code as type_code',
                    DB::raw('SUM(order_items.line_total) as total')
                );
        }

        $posDaily = $posDailyQuery->get();

        foreach ($posDaily as $r) {
            $key = GeneralReportPosBuckets::resolve(
                (string) ($r->pos_bucket_key ?? ''),
                (string) ($r->category_name ?? ''),
                isset($r->type_name) ? (string) $r->type_name : null,
                isset($r->type_code) ? (string) $r->type_code : null
            );
            $col = HotelRevenueReportColumnService::mapBucketToActiveColumn($key, $this->columnKeys);
            $this->row[$col] = (float) ($this->row[$col] ?? 0) + (float) ($r->total ?? 0);
        }

        SupplementalGeneralReportRevenueService::mergeIntoDailyRow($hotel, $ymd, $this->columnKeys, $this->row);

        $sum = 0.0;
        foreach ($this->columnKeys as $k) {
            $sum += (float) ($this->row[$k] ?? 0);
        }
        $this->row['total'] = $sum;
        $this->totals = $this->row;
    }

    public function render()
    {
        return view('livewire.front-office.general-daily-sales-summary')->layout('livewire.layouts.app-layout');
    }
}

