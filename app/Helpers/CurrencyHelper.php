<?php

namespace App\Helpers;

use App\Models\Hotel;

class CurrencyHelper
{
    /**
     * Get system currency code
     */
    public static function getCurrency(): string
    {
        return Hotel::getHotel()->getCurrency();
    }

    /**
     * Get system currency symbol
     */
    public static function getCurrencySymbol(): string
    {
        return Hotel::getHotel()->getCurrencySymbol();
    }

    /**
     * Format amount with system currency (value first, then currency)
     */
    public static function format(float $amount, int $decimals = 2): string
    {
        $symbol = self::getCurrencySymbol();
        $currency = self::getCurrency();
        $formatted = number_format($amount, $decimals);

        // For currencies that use the code as symbol (RWF, KES, etc.), show value then code
        if (in_array($currency, ['RWF', 'KES', 'UGX', 'TZS', 'ETB'])) {
            return $formatted . ' ' . $currency;
        }

        // For currencies with symbols ($, €, £), show value then symbol
        return $formatted . ' ' . $symbol;
    }
}
