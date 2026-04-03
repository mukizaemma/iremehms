<?php

namespace App\Support;

/**
 * Line types for proforma rows (workshops, weddings, outside catering, etc.).
 */
final class ProformaCatalog
{
    /**
     * @return array<string, string> value => label
     */
    public static function lineTypes(): array
    {
        return [
            'room_night' => 'Room / accommodation',
            'meal' => 'Meal (breakfast, lunch, dinner)',
            'break' => 'Tea / coffee break',
            'beverage' => 'Beverages & water',
            'transport' => 'Transport',
            'venue' => 'Venue / hall / wedding room',
            'decoration' => 'Decoration',
            'sound' => 'Sound / AV',
            'outside_catering' => 'Outside catering',
            'conference_halls' => 'Conference / meeting',
            'garden' => 'Garden / events',
            'wellness' => 'Wellness / spa',
            'other' => 'Other',
            'custom' => 'Custom line',
        ];
    }

    /**
     * Default report bucket when a line type is chosen (can be overridden per line).
     *
     * @return array<string, string>
     */
    public static function defaultBucketForLineType(): array
    {
        return [
            'room_night' => 'rooms',
            'meal' => 'food',
            'break' => 'food',
            'beverage' => 'beverages',
            'transport' => 'other',
            'venue' => 'conference_halls',
            'decoration' => 'garden',
            'sound' => 'other',
            'outside_catering' => 'outside_catering',
            'conference_halls' => 'conference_halls',
            'garden' => 'garden',
            'wellness' => 'massage',
            'other' => 'other',
            'custom' => 'other',
        ];
    }
}
