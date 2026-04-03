<?php

namespace App\Helpers;

use App\Models\Hotel;
use Carbon\Carbon;

/**
 * Format dates/times in the hotel's timezone for display.
 * Business day and POS times are stored in UTC; use this for user-facing display.
 */
class HotelTimeHelper
{
    public static function getTimezone(): string
    {
        return Hotel::getHotel()->getTimezone();
    }

    /**
     * Format a date/datetime in the hotel's timezone.
     *
     * @param \DateTimeInterface|string|null $date
     * @param string $format Default 'd/m H:i' for datetime; use 'd M Y' for date only.
     * @return string Formatted string, or empty string if $date is null/empty.
     */
    public static function format($date, string $format = 'd/m H:i'): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $carbon = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);
        return $carbon->setTimezone(self::getTimezone())->format($format);
    }
}
