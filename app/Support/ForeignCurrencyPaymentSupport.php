<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\ReservationPayment;

final class ForeignCurrencyPaymentSupport
{
    public const OPTIONS = ['USD', 'EUR', 'GBP'];

    public static function hotelBaseCurrency(?Hotel $hotel = null): string
    {
        $hotel = $hotel ?? Hotel::getHotel();

        return strtoupper((string) ($hotel?->currency ?? 'RWF'));
    }

    public static function isForeign(string $currencyCode, ?string $baseCurrency = null): bool
    {
        $base = strtoupper($baseCurrency ?? self::hotelBaseCurrency());

        return strtoupper($currencyCode) !== $base;
    }

    public static function convertForeignToLocal(float $foreign, float $rate): float
    {
        return round($foreign * $rate, 2);
    }

    public static function convertLocalToForeign(float $local, float $rate): float
    {
        if ($rate <= 0) {
            return 0.0;
        }

        return round($local / $rate, 2);
    }

    /**
     * @return array{
     *     amount: float,
     *     currency: string,
     *     foreign_currency: ?string,
     *     foreign_amount: ?float,
     *     exchange_rate: ?float
     * }
     */
    public static function resolveStorage(
        ?Hotel $hotel,
        bool $useForeign,
        ?string $foreignCurrency,
        ?string $exchangeRate,
        ?string $amountForeign,
        ?string $amountLocal,
        ?string $amountDirect = null,
    ): array {
        $baseCurrency = self::hotelBaseCurrency($hotel);

        if (! $useForeign) {
            $amount = is_numeric($amountDirect ?? $amountLocal ?? '')
                ? (float) ($amountDirect ?? $amountLocal)
                : 0.0;

            return [
                'amount' => $amount,
                'currency' => $baseCurrency,
                'foreign_currency' => null,
                'foreign_amount' => null,
                'exchange_rate' => null,
            ];
        }

        $rate = is_numeric($exchangeRate) ? (float) $exchangeRate : 0.0;
        $foreign = is_numeric($amountForeign) ? (float) $amountForeign : null;
        $local = is_numeric($amountLocal) ? (float) $amountLocal : null;

        if ($foreign !== null && $rate > 0) {
            $local = self::convertForeignToLocal($foreign, $rate);
        } elseif ($local !== null && $rate > 0) {
            $foreign = self::convertLocalToForeign($local, $rate);
        } else {
            $local ??= 0.0;
        }

        return [
            'amount' => (float) $local,
            'currency' => $baseCurrency,
            'foreign_currency' => strtoupper(trim((string) $foreignCurrency)) ?: null,
            'foreign_amount' => $foreign,
            'exchange_rate' => $rate > 0 ? $rate : null,
        ];
    }

    public static function resolvedPaidAmount(
        ?Hotel $hotel,
        bool $useForeign,
        ?string $foreignCurrency,
        ?string $exchangeRate,
        ?string $amountForeign,
        ?string $amountLocal,
        ?string $amountDirect = null,
    ): float {
        return self::resolveStorage(
            $hotel,
            $useForeign,
            $foreignCurrency,
            $exchangeRate,
            $amountForeign,
            $amountLocal,
            $amountDirect,
        )['amount'];
    }

    public static function hasForeign(ReservationPayment $payment): bool
    {
        return filled($payment->foreign_currency) && $payment->foreign_amount !== null;
    }

    public static function formatLocalAmount(ReservationPayment $payment): string
    {
        $currency = $payment->currency ?? self::hotelBaseCurrency();

        return $currency.' '.number_format((float) ($payment->amount ?? 0), 2, '.', '');
    }

    public static function formatForeignSuffix(ReservationPayment $payment): string
    {
        if (! self::hasForeign($payment)) {
            return '';
        }

        return ' ('.$payment->foreign_currency.' '.number_format((float) $payment->foreign_amount, 2, '.', '').')';
    }

    public static function formatDisplay(ReservationPayment $payment): string
    {
        return self::formatLocalAmount($payment).self::formatForeignSuffix($payment);
    }

    public static function formatReportLine(ReservationPayment $payment, float $amount): string
    {
        $line = number_format($amount, 2, '.', '');

        if (! self::hasForeign($payment)) {
            return $line;
        }

        return $line.' · '.$payment->foreign_currency.' '.number_format((float) $payment->foreign_amount, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function foreignColumnsFromPayment(ReservationPayment $payment): array
    {
        return [
            'foreign_currency' => $payment->foreign_currency,
            'foreign_amount' => $payment->foreign_amount,
            'exchange_rate' => $payment->exchange_rate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function foreignColumnsFromInput(
        ?Hotel $hotel,
        bool $useForeign,
        ?string $foreignCurrency,
        ?string $exchangeRate,
        ?string $amountForeign,
        ?string $amountLocal,
        ?string $amountDirect = null,
    ): array {
        $resolved = self::resolveStorage(
            $hotel,
            $useForeign,
            $foreignCurrency,
            $exchangeRate,
            $amountForeign,
            $amountLocal,
            $amountDirect,
        );

        return [
            'foreign_currency' => $resolved['foreign_currency'],
            'foreign_amount' => $resolved['foreign_amount'],
            'exchange_rate' => $resolved['exchange_rate'],
        ];
    }
}
