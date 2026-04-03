<?php

namespace App\Support;

/**
 * Module slug stored on activity_logs for filtering (per-area audit views).
 */
final class ActivityLogModule
{
    public const FRONT_OFFICE = 'front-office';

    public const POS = 'pos';

    public const STOCK = 'stock';

    public const GENERAL = 'general';

    /**
     * @return array<string, string> slug => label
     */
    public static function labels(): array
    {
        return [
            self::FRONT_OFFICE => 'Front office',
            self::POS => 'POS / Restaurant',
            self::STOCK => 'Stock / Store',
            self::GENERAL => 'General',
        ];
    }

    public static function allSlugs(): array
    {
        return [
            self::FRONT_OFFICE,
            self::POS,
            self::STOCK,
            self::GENERAL,
        ];
    }
}
