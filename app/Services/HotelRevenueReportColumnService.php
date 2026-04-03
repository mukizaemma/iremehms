<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelRevenueReportLine;
use App\Support\GeneralReportPosBuckets;

/**
 * Per-hotel configuration for general report columns (revenue lines).
 */
final class HotelRevenueReportColumnService
{
    /**
     * Default bucket keys and printable labels (before hotel customization).
     *
     * @return array<string, string>
     */
    public static function defaultDefinitions(): array
    {
        return [
            'food' => 'FOOD',
            'beverages' => 'BEVERAGES',
            'conference_halls' => 'CONFERENCE HALLS',
            'rooms' => 'ROOMS',
            'swimming_pool' => 'SWIMMING POOL',
            'sauna' => 'SAUNA',
            'massage' => 'MASSAGE',
            'gym' => 'GYM',
            'garden' => 'GARDEN / EVENTS',
            'outside_catering' => 'OUTSIDE CATERING',
            'other' => 'OTHER',
        ];
    }

    public static function allowedBucketKeys(): array
    {
        return array_keys(self::defaultDefinitions());
    }

    /**
     * Seed default rows if the hotel has none yet.
     */
    public static function ensureDefaultLines(Hotel $hotel): void
    {
        if ($hotel->revenueReportLines()->exists()) {
            return;
        }

        $order = 0;
        foreach (self::defaultDefinitions() as $key => $label) {
            HotelRevenueReportLine::create([
                'hotel_id' => $hotel->id,
                'bucket_key' => $key,
                'label' => $label,
                'sort_order' => $order,
                'is_active' => true,
            ]);
            $order++;
        }
    }

    /**
     * If every line was saved inactive (or data is inconsistent), never fall back to "all columns":
     * activate at least the "other" row so reports stay bounded to configured visibility rules.
     */
    private static function ensureAtLeastOneActiveLine(Hotel $hotel): void
    {
        if ($hotel->revenueReportLines()->where('is_active', true)->exists()) {
            return;
        }

        $other = $hotel->revenueReportLines()->where('bucket_key', 'other')->first();
        if ($other) {
            $other->update(['is_active' => true]);

            return;
        }

        self::ensureDefaultLines($hotel);
    }

    /**
     * @return array{keys: array<int, string>, labels: array<string, string>}
     */
    public static function getActiveColumns(Hotel $hotel): array
    {
        self::ensureDefaultLines($hotel);

        $lines = $hotel->revenueReportLines()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('bucket_key')
            ->get();

        if ($lines->isEmpty()) {
            self::ensureAtLeastOneActiveLine($hotel);
            $lines = $hotel->revenueReportLines()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('bucket_key')
                ->get();
        }

        $keys = [];
        $labels = [];
        foreach ($lines as $line) {
            $keys[] = $line->bucket_key;
            $labels[$line->bucket_key] = $line->label;
        }

        if ($keys === []) {
            $defs = self::defaultDefinitions();

            return [
                'keys' => ['other'],
                'labels' => ['other' => $defs['other'] ?? 'OTHER'],
            ];
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    /**
     * Map a resolved POS/rooms bucket to a visible column; overflow goes to "other" if present.
     *
     * @param  array<int, string>  $activeKeys
     */
    public static function mapBucketToActiveColumn(string $resolvedBucket, array $activeKeys): string
    {
        $resolvedBucket = GeneralReportPosBuckets::normalizeStoredKey($resolvedBucket) ?: $resolvedBucket;
        if (in_array($resolvedBucket, $activeKeys, true)) {
            return $resolvedBucket;
        }
        if (in_array('other', $activeKeys, true)) {
            return 'other';
        }

        return $activeKeys[0] ?? 'other';
    }
}
