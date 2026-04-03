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
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralReportSummaryDashboard extends Component
{
    /**
     * Number of days to pivot in the dashboard.
     * Keep small because this component runs multiple daily aggregates.
     */
    public int $days = 7;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $month = '';
    public string $monthFrom = '';
    public string $monthTo = '';
    public string $currency = 'RWF';

    /** Optional names printed on the signature blocks (shown only on print). */
    public string $verified_by_name = '';
    public string $approved_by_name = '';

    public array $columnKeys = [];
    public array $columnLabels = [];

    /**
     * @var array<int, array<string, float|string>>
     * Each row contains: date_label + bucket keys + total.
     */
    public array $rows = [];

    /**
     * @var array<string, float>
     */
    public array $totals = [];

    public function mount(): void
    {
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
            || ($effectiveSlug === 'accountant')
            || $user->isEffectiveGeneralManager()
            || $user->isIremeAccountant();

        if (! $allowed) {
            abort(403, 'You do not have access to the general report summary.');
        }

        $this->currency = (string) ($hotel->currency ?? 'RWF');
        $tz = $hotel->getTimezone();

        $today = Hotel::getTodayForHotel() ?: now($tz)->toDateString();
        $this->dateTo = request('dateTo', $today);
        $this->days = max(1, (int) request('days', $this->days));
        $this->dateFrom = Carbon::parse($this->dateTo, $tz)->subDays($this->days - 1)->format('Y-m-d');

        $this->initColumns();
        $this->loadReport();

        // Navigation helpers (top action buttons)
        $dateToObj = Carbon::parse($this->dateTo, $tz);
        $this->month = $dateToObj->format('Y-m');
        $this->monthFrom = $dateToObj->copy()->startOfMonth()->format('Y-m-d');
        $this->monthTo = $dateToObj->copy()->endOfMonth()->format('Y-m-d');
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

        $this->totals = array_fill_keys(array_merge($this->columnKeys, ['total']), 0.0);
    }

    protected function loadReport(): void
    {
        $hotel = Hotel::getHotel();
        $tz = $hotel->getTimezone();

        $hasPosBucketColumn = Schema::hasColumn('menu_categories', 'pos_report_column_key');

        $start = Carbon::parse($this->dateFrom, $tz)->startOfDay();
        $end = Carbon::parse($this->dateTo, $tz)->endOfDay();

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        $this->rows = [];
        $this->totals = array_fill_keys(array_merge($this->columnKeys, ['total']), 0.0);

        foreach ($days as $ymd) {
            $row = array_fill_keys($this->columnKeys, 0.0);
            $row['total'] = 0.0;
            $row['date_label'] = Carbon::parse($ymd, $tz)->format('M d, Y');

            // Rooms revenue (accommodation) for the day.
            $roomsAmt = (float) DB::table('reservation_payments')
                ->where('hotel_id', $hotel->id)
                ->where('status', 'confirmed')
                ->whereDate('received_at', $ymd)
                ->sum('amount');
            $roomsCol = HotelRevenueReportColumnService::mapBucketToActiveColumn('rooms', $this->columnKeys);
            $row[$roomsCol] = ($row[$roomsCol] ?? 0) + $roomsAmt;

            // Restaurant POS revenue for the day by bucket.
            $dateStart = Carbon::parse($ymd, $tz)->startOfDay();
            $dateEnd = Carbon::parse($ymd, $tz)->endOfDay();

            $posDailyQueryBase = DB::table('order_items')
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
                $posDailyQuery = $posDailyQueryBase
                    ->select(
                        'menu_categories.name as category_name',
                        'menu_categories.pos_report_column_key as pos_bucket_key',
                        'menu_item_types.name as type_name',
                        'menu_item_types.code as type_code',
                        DB::raw('SUM(order_items.line_total) as total')
                    )
                    ->groupBy(
                        'menu_categories.name',
                        'menu_categories.pos_report_column_key',
                        'menu_item_types.name',
                        'menu_item_types.code'
                    );
            } else {
                $posDailyQuery = $posDailyQueryBase
                    ->select(
                        'menu_categories.name as category_name',
                        DB::raw('NULL as pos_bucket_key'),
                        'menu_item_types.name as type_name',
                        'menu_item_types.code as type_code',
                        DB::raw('SUM(order_items.line_total) as total')
                    )
                    ->groupBy('menu_categories.name', 'menu_item_types.name', 'menu_item_types.code');
            }

            $posLines = $posDailyQuery->get();
            foreach ($posLines as $line) {
                $bucket = GeneralReportPosBuckets::resolve(
                    (string) ($line->pos_bucket_key ?? ''),
                    (string) ($line->category_name ?? ''),
                    isset($line->type_name) ? (string) $line->type_name : null,
                    isset($line->type_code) ? (string) $line->type_code : null
                );
                $col = HotelRevenueReportColumnService::mapBucketToActiveColumn($bucket, $this->columnKeys);
                $row[$col] += (float) ($line->total ?? 0);
            }

            SupplementalGeneralReportRevenueService::mergeIntoDailyRow($hotel, $ymd, $this->columnKeys, $row);

            // Total is sum of all department buckets (including rooms).
            $row['total'] = array_sum(array_map(fn ($k) => (float) $row[$k], $this->columnKeys));

            foreach ($this->columnKeys as $k) {
                $this->totals[$k] += (float) $row[$k];
            }
            $this->totals['total'] += (float) $row['total'];

            $this->rows[] = $row;
        }
    }

    /**
     * Export the pivot/general summary to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $user = Auth::user();
        $hotel = Hotel::getHotel();

        $filename = 'general-report-summary-' . $this->dateFrom . '-' . $this->dateTo . '.csv';

        return response()->streamDownload(function () use ($user, $hotel, $filename) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['General report summary (departments)']);
            fputcsv($out, ['Hotel', $hotel?->name ?? '']);
            fputcsv($out, ['Period', $this->dateFrom . ' to ' . $this->dateTo]);
            fputcsv($out, ['Prepared by', $user?->name ?? '']);
            fputcsv($out, ['Verified by', $this->verified_by_name !== '' ? $this->verified_by_name : '']);
            fputcsv($out, ['Approved by', $this->approved_by_name !== '' ? $this->approved_by_name : '']);
            fputcsv($out, []);

            $headers = array_merge(['Date'], array_map(
                fn ($k) => $this->columnLabels[$k] ?? strtoupper($k),
                $this->columnKeys
            ), ['Total revenues']);
            fputcsv($out, $headers);

            foreach ($this->rows as $row) {
                $line = [$row['date_label'] ?? ''];
                foreach ($this->columnKeys as $k) {
                    $line[] = (string) ($row[$k] ?? 0);
                }
                $line[] = (string) ($row['total'] ?? 0);
                fputcsv($out, $line);
            }

            fputcsv($out, []);
            $grandLine = array_merge(['GRAND TOTAL'], array_map(
                fn ($k) => (string) ($this->totals[$k] ?? 0),
                $this->columnKeys
            ), [(string) ($this->totals['total'] ?? 0)]);
            fputcsv($out, $grandLine);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render()
    {
        return view('livewire.front-office.general-report-summary-dashboard');
    }
}

