<?php

namespace App\Helpers;

use Carbon\Carbon;

class ReportDatePreset
{
    public const TODAY = 'today';
    public const YESTERDAY = 'yesterday';
    public const LAST_7 = 'last_7';
    public const LAST_30 = 'last_30';
    public const RANGE = 'range';

    /**
     * Return [dateFrom, dateTo] for the given preset and optional custom from/to.
     */
    public static function apply(string $preset, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $today = Carbon::today();
        switch ($preset) {
            case self::TODAY:
                return [$today->format('Y-m-d'), $today->format('Y-m-d')];
            case self::YESTERDAY:
                $y = $today->copy()->subDay();
                return [$y->format('Y-m-d'), $y->format('Y-m-d')];
            case self::LAST_7:
                $from = $today->copy()->subDays(6);
                return [$from->format('Y-m-d'), $today->format('Y-m-d')];
            case self::LAST_30:
                $from = $today->copy()->subDays(29);
                return [$from->format('Y-m-d'), $today->format('Y-m-d')];
            case self::RANGE:
                $from = $dateFrom ? Carbon::parse($dateFrom)->format('Y-m-d') : $today->format('Y-m-d');
                $to = $dateTo ? Carbon::parse($dateTo)->format('Y-m-d') : $today->format('Y-m-d');
                return [$from, $to];
            default:
                return [$today->format('Y-m-d'), $today->format('Y-m-d')];
        }
    }

    public static function options(): array
    {
        return [
            self::TODAY => 'Today',
            self::YESTERDAY => 'Yesterday',
            self::LAST_7 => 'Last 7 days',
            self::LAST_30 => 'Last 30 days',
            self::RANGE => 'Date range',
        ];
    }
}
