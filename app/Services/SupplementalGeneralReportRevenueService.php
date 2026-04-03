<?php

namespace App\Services;

use App\Models\Hotel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wellness and proforma (post-event) payments recorded outside POS — merged into general report columns.
 */
final class SupplementalGeneralReportRevenueService
{
    /**
     * Per-day, per-mapped-column amounts for the date range (hotel timezone day boundaries in caller).
     *
     * @return array<string, array<string, float>> [Y-m-d => [column_key => amount]]
     */
    public static function dailyBucketAmounts(Hotel $hotel, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $out = [];
        if (! Schema::hasTable('wellness_payments') || ! Schema::hasTable('proforma_invoice_payments')) {
            return $out;
        }

        $cols = HotelRevenueReportColumnService::getActiveColumns($hotel);
        $columnKeys = $cols['keys'];

        $append = function (string $ymd, string $rawBucket, float $amount) use (&$out, $columnKeys): void {
            if ($amount == 0.0) {
                return;
            }
            $col = HotelRevenueReportColumnService::mapBucketToActiveColumn($rawBucket, $columnKeys);
            if (! isset($out[$ymd])) {
                $out[$ymd] = [];
            }
            $out[$ymd][$col] = ($out[$ymd][$col] ?? 0.0) + $amount;
        };

        $fromStr = $rangeStart->toDateTimeString();
        $toStr = $rangeEnd->toDateTimeString();

        $wellness = DB::table('wellness_payments')
            ->where('hotel_id', $hotel->id)
            ->whereBetween('received_at', [$fromStr, $toStr])
            ->selectRaw('DATE(received_at) as sale_date, report_bucket_key, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(received_at)'), 'report_bucket_key')
            ->get();

        foreach ($wellness as $r) {
            $append((string) $r->sale_date, (string) $r->report_bucket_key, (float) ($r->total ?? 0));
        }

        $proforma = DB::table('proforma_invoice_payments')
            ->where('hotel_id', $hotel->id)
            ->whereBetween('received_at', [$fromStr, $toStr])
            ->selectRaw('DATE(received_at) as sale_date, report_bucket_key, SUM(amount) as total')
            ->groupBy(DB::raw('DATE(received_at)'), 'report_bucket_key')
            ->get();

        foreach ($proforma as $r) {
            $append((string) $r->sale_date, (string) $r->report_bucket_key, (float) ($r->total ?? 0));
        }

        return $out;
    }

    /**
     * Add supplemental amounts into a single day's row (keys = columnKeys + total already recomputed by caller).
     *
     * @param  array<string, float>  $row
     */
    public static function mergeIntoDailyRow(Hotel $hotel, string $ymd, array $columnKeys, array &$row): void
    {
        $tz = $hotel->getTimezone();
        $start = Carbon::parse($ymd, $tz)->startOfDay();
        $end = Carbon::parse($ymd, $tz)->endOfDay();
        $daily = self::dailyBucketAmounts($hotel, $start, $end);
        $extra = $daily[$ymd] ?? [];
        foreach ($extra as $col => $amt) {
            if (! in_array($col, $columnKeys, true)) {
                continue;
            }
            $row[$col] = (float) ($row[$col] ?? 0) + (float) $amt;
        }
    }
}
