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

class GeneralMonthlySalesSummary extends Component
{
    public string $month = '';
    public string $approved_by_name = '';
    public string $verified_by_name = '';

    /** @var array<int, array{label:string,ymd:string}> */
    public array $dayLabels = [];

    /** Column keys mapped to UI labels (order preserved in $columnKeys). */
    public array $columnLabels = [];

    /** @var array<int, string> */
    public array $columnKeys = [];

    /**
     * Rows: each row has ['date_label' => string, <colKey> => float, 'total' => float]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $rows = [];

    /** @var array<string, float> */
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

        // Broad authorization: managers + users who can view reports/oversight.
        $allowed = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('reports_view_all')
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->isRestaurantManager()
            || $user->isManager()
            || ($user->getEffectiveRole()?->slug === 'manager')
            || $user->isEffectiveGeneralManager()
            || $user->isIremeAccountant();

        if (! $allowed) {
            abort(403, 'You do not have access to the general report.');
        }

        $tz = $hotel->getTimezone();
        $this->month = request('month', Carbon::now($tz)->format('Y-m'));
        $this->initColumns();
        $this->loadReport();
    }

    protected function initColumns(): void
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $defs = HotelRevenueReportColumnService::defaultDefinitions();
            $this->columnKeys = array_keys($defs);
            $this->columnLabels = $defs;
        } else {
            $cols = HotelRevenueReportColumnService::getActiveColumns($hotel);
            $this->columnKeys = $cols['keys'];
            $this->columnLabels = $cols['labels'];
        }

        $this->totals = array_fill_keys($this->columnKeys, 0.0);
        $this->totals['total'] = 0.0;
    }

    public function applyFilters(): void
    {
        $this->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $this->loadReport();
    }

    protected function loadReport(): void
    {
        $hotel = Hotel::getHotel();

        $tz = $hotel->getTimezone();
        $monthStart = Carbon::parse($this->month.'-01', $tz)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        // Ensure the month picker stays consistent.
        $this->month = $monthStart->format('Y-m');

        $this->initColumns();
        $this->dayLabels = [];
        $this->rows = [];

        $daysInMonth = (int) $monthStart->daysInMonth;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $d = $monthStart->copy()->setDay($day);
            $ymd = $d->format('Y-m-d');
            $label = $d->format('j-M-y'); // e.g. 1-Feb-26

            $this->dayLabels[] = [
                'label' => $label,
                'ymd' => $ymd,
            ];

            $row = ['date_label' => $label];
            foreach ($this->columnKeys as $k) {
                $row[$k] = 0.0;
            }
            $row['total'] = 0.0;

            $this->rows[$ymd] = $row; // keyed by Y-m-d for fast population
        }

        $from = $monthStart->copy()->startOfDay();
        $to = $monthEnd->copy()->endOfDay();
        $hasPosBucketColumn = Schema::hasColumn('menu_categories', 'pos_report_column_key');

        // 1) Rooms sales from accommodation payments (confirmed).
        $roomDaily = DB::table('reservation_payments')
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereBetween(DB::raw('DATE(received_at)'), [$from->toDateString(), $to->toDateString()])
            ->select(DB::raw('DATE(received_at) as sale_date'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('DATE(received_at)'))
            ->get();

        $roomsCol = HotelRevenueReportColumnService::mapBucketToActiveColumn('rooms', $this->columnKeys);
        foreach ($roomDaily as $r) {
            $saleDate = (string) ($r->sale_date ?? '');
            if ($saleDate !== '' && isset($this->rows[$saleDate])) {
                $this->rows[$saleDate][$roomsCol] = (float) ($r->total ?? 0);
            }
        }

        // 2) Restaurant POS sales by menu category for each day.
        $posDailyQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.menu_item_id')
            ->leftJoin('menu_categories', 'menu_items.category_id', '=', 'menu_categories.category_id')
            ->leftJoin('menu_item_types', 'menu_items.menu_item_type_id', '=', 'menu_item_types.type_id')
            ->whereNull('order_items.voided_at')
            ->where('orders.order_status', 'PAID')
            ->whereBetween('orders.updated_at', [$from, $to])
            // Hotel scope via waiter -> hotel_id.
            ->join('users', 'orders.waiter_id', '=', 'users.id')
            ->where('users.hotel_id', $hotel->id);

        if ($hasPosBucketColumn) {
            $posDailyQuery
                ->groupBy(
                    DB::raw('DATE(orders.updated_at)'),
                    'menu_categories.pos_report_column_key',
                    'menu_categories.name',
                    'menu_item_types.name',
                    'menu_item_types.code'
                )
                ->select(
                    DB::raw('DATE(orders.updated_at) as sale_date'),
                    'menu_categories.name as category_name',
                    'menu_categories.pos_report_column_key as pos_bucket_key',
                    'menu_item_types.name as type_name',
                    'menu_item_types.code as type_code',
                    DB::raw('SUM(order_items.line_total) as total')
                );
        } else {
            // Fallback: no bucket column exists yet, use category name keyword matching in PHP.
            $posDailyQuery
                ->groupBy(DB::raw('DATE(orders.updated_at)'), 'menu_categories.name', 'menu_item_types.name', 'menu_item_types.code')
                ->select(
                    DB::raw('DATE(orders.updated_at) as sale_date'),
                    'menu_categories.name as category_name',
                    DB::raw('NULL as pos_bucket_key'),
                    'menu_item_types.name as type_name',
                    'menu_item_types.code as type_code',
                    DB::raw('SUM(order_items.line_total) as total')
                );
        }

        $posDaily = $posDailyQuery->get();

        foreach ($posDaily as $r) {
            $saleDate = (string) ($r->sale_date ?? '');
            $key = GeneralReportPosBuckets::resolve(
                (string) ($r->pos_bucket_key ?? ''),
                (string) ($r->category_name ?? ''),
                isset($r->type_name) ? (string) $r->type_name : null,
                isset($r->type_code) ? (string) $r->type_code : null
            );
            $col = HotelRevenueReportColumnService::mapBucketToActiveColumn($key, $this->columnKeys);

            if ($saleDate !== '' && isset($this->rows[$saleDate])) {
                $this->rows[$saleDate][$col] = ((float) $this->rows[$saleDate][$col]) + (float) ($r->total ?? 0);
            }
        }

        $supplemental = SupplementalGeneralReportRevenueService::dailyBucketAmounts($hotel, $from, $to);
        foreach ($supplemental as $ymd => $buckets) {
            if (! isset($this->rows[$ymd])) {
                continue;
            }
            foreach ($buckets as $col => $amt) {
                $this->rows[$ymd][$col] = ((float) ($this->rows[$ymd][$col] ?? 0)) + (float) $amt;
            }
        }

        // Compute totals per row + month.
        foreach ($this->rows as $ymd => &$row) {
            $sum = 0.0;
            foreach ($this->columnKeys as $k) {
                $v = (float) ($row[$k] ?? 0);
                $sum += $v;
                $this->totals[$k] += $v;
            }
            $row['total'] = $sum;
            $this->totals['total'] += $sum;
        }
        unset($row);
    }

    public function render()
    {
        return view('livewire.front-office.general-monthly-sales-summary')->layout('livewire.layouts.app-layout');
    }
}

