<?php

namespace App\Support;

/**
 * Maps POS sales into "general report" columns (food, beverages, …).
 *
 * Menu Management shows an item "type" (finished good, etc.) while the general report
 * historically used menu CATEGORY names + optional pos_report_column_key. Categories like
 * "Main Courses" or "Coffee" do not contain the word "food", so sales were mis-bucketed
 * into OTHER unless pos_report_column_key was set on the category.
 *
 * This resolver normalizes stored keys and uses category + optional menu item type hints.
 */
final class GeneralReportPosBuckets
{
    /** @var list<string> */
    public const COLUMN_KEYS = [
        'food',
        'beverages',
        'conference_halls',
        'rooms',
        'swimming_pool',
        'sauna',
        'massage',
        'gym',
        'garden',
        'outside_catering',
        'other',
    ];

    public static function normalizeStoredKey(?string $key): string
    {
        if ($key === null) {
            return '';
        }

        return mb_strtolower(trim($key));
    }

    /**
     * @param  string  $posBucketKey  menu_categories.pos_report_column_key (may be empty)
     * @param  string  $categoryName  menu_categories.name (may be empty when uncategorised)
     * @param  string|null  $typeName  menu_item_types.name
     * @param  string|null  $typeCode  menu_item_types.code
     */
    public static function resolve(
        string $posBucketKey,
        string $categoryName,
        ?string $typeName,
        ?string $typeCode
    ): string {
        $k = self::normalizeStoredKey($posBucketKey);

        // Direct matches (and common legacy variants).
        if ($k !== '') {
            if (in_array($k, self::COLUMN_KEYS, true)) {
                return $k;
            }
            if (in_array($k, ['foods', 'food_sales'], true)) {
                return 'food';
            }
            if (in_array($k, ['beverage', 'drinks', 'drink'], true)) {
                return 'beverages';
            }
        }

        $text = mb_strtolower(trim(
            $categoryName.' '.($typeName ?? '').' '.($typeCode ?? '')
        ));

        return self::inferFromPlainText($text);
    }

    public static function inferFromPlainText(string $text): string
    {
        $n = mb_strtolower(trim($text));
        if ($n === '') {
            return 'other';
        }

        // Non-POS / venue buckets first.
        if (str_contains($n, 'conference') && (str_contains($n, 'hall') || str_contains($n, 'halls'))) {
            return 'conference_halls';
        }
        if (str_contains($n, 'swimming') && str_contains($n, 'pool')) {
            return 'swimming_pool';
        }
        if (str_contains($n, 'sauna')) {
            return 'sauna';
        }
        if (str_contains($n, 'massage')) {
            return 'massage';
        }
        if (str_contains($n, 'gym')) {
            return 'gym';
        }
        if (str_contains($n, 'garden') || str_contains($n, 'lawn') || str_contains($n, 'outdoor event')) {
            return 'garden';
        }
        if (
            str_contains($n, 'outside catering')
            || str_contains($n, 'outdoor catering')
            || str_contains($n, 'external catering')
            || ($n === 'catering' && ! str_contains($n, 'conference'))
        ) {
            return 'outside_catering';
        }

        // Food hints (before beverages so coffee / cappuccino land in FOOD).
        $foodHints = [
            'food',
            'kitchen',
            'burger',
            'burgers',
            'pizza',
            'pasta',
            'sandwich',
            'breakfast',
            'lunch',
            'dinner',
            'brunch',
            'dessert',
            'appetizer',
            'starter',
            'main course',
            'entree',
            'grill',
            'bbq',
            'barbecue',
            'ribs',
            'coffee',
            'cappuccino',
            'espresso',
            'latte',
            'mocha',
            'soup',
            'salad',
            'snack',
            'pastry',
            'cake',
            'bakery',
            'continental',
            'course', // e.g. "Main Courses"
            'platter',
            'combo',
            'meal',
        ];
        foreach ($foodHints as $hint) {
            if (str_contains($n, $hint)) {
                return 'food';
            }
        }

        // Beverages (include common misspellings like "bevarage").
        if (
            str_contains($n, 'beverage')
            || str_contains($n, 'bevarage')
            || str_contains($n, 'bevar')
            || str_contains($n, 'drink')
            || str_contains($n, 'juice')
            || str_contains($n, 'soda')
            || str_contains($n, 'soft drink')
            || str_contains($n, 'beer')
            || str_contains($n, 'wine')
            || str_contains($n, 'cocktail')
            || str_contains($n, 'spirit')
            || str_contains($n, 'mineral water')
            || preg_match('/\bbar\b/', $n)
        ) {
            return 'beverages';
        }

        // Tea is usually beverage unless part of a longer food word (rare).
        if (str_contains($n, 'tea')) {
            return 'beverages';
        }

        if (str_contains($n, 'room')) {
            return 'other';
        }

        return 'other';
    }
}
