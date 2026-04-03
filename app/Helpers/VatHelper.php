<?php

namespace App\Helpers;

/**
 * Rwanda VAT (18%). Assumes all amounts are VAT-inclusive.
 * Net = Total / (1 + rate/100), VAT = Total × (18/118) for RRA remittance.
 */
class VatHelper
{
    public static function getVatRate(): float
    {
        return (float) (config('pos.vat_rate', 18));
    }

    /**
     * Given a VAT-inclusive total, return [net, vat].
     */
    public static function fromInclusive(float $totalInclusive): array
    {
        $rate = self::getVatRate();
        if ($rate <= 0) {
            return ['net' => $totalInclusive, 'vat' => 0.0];
        }
        $divisor = 1 + ($rate / 100);
        $net = round($totalInclusive / $divisor, 2);
        $vat = round($totalInclusive - $net, 2);
        return ['net' => $net, 'vat' => $vat];
    }

    /**
     * Net amount (exclusive of VAT).
     */
    public static function netFromInclusive(float $totalInclusive): float
    {
        return self::fromInclusive($totalInclusive)['net'];
    }

    /**
     * VAT amount (18% of net, or total - net).
     */
    public static function vatFromInclusive(float $totalInclusive): float
    {
        return self::fromInclusive($totalInclusive)['vat'];
    }
}
