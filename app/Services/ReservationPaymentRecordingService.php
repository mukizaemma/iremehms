<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Models\Reservation;
use Carbon\Carbon;

final class ReservationPaymentRecordingService
{
    /**
     * Map UI payment purpose to persisted columns (debt flag + revenue attribution date).
     *
     * @return array{payment_purpose: string, is_debt_settlement: bool, revenue_attribution_date: ?string}
     */
    public static function resolvePurposeFields(
        PaymentPurpose $purpose,
        Reservation $reservation,
        ?string $revenueAttributionDateInput = null,
    ): array {
        return match ($purpose) {
            PaymentPurpose::DebtSettlement => [
                'payment_purpose' => PaymentPurpose::DebtSettlement->value,
                'is_debt_settlement' => true,
                'revenue_attribution_date' => self::normalizeDateInput(
                    $revenueAttributionDateInput,
                    $reservation->check_out_date?->format('Y-m-d'),
                ),
            ],
            PaymentPurpose::AdvanceDeposit => [
                'payment_purpose' => PaymentPurpose::AdvanceDeposit->value,
                'is_debt_settlement' => false,
                'revenue_attribution_date' => $reservation->check_in_date->format('Y-m-d'),
            ],
            default => [
                'payment_purpose' => PaymentPurpose::CurrentStay->value,
                'is_debt_settlement' => false,
                'revenue_attribution_date' => null,
            ],
        };
    }

    /** Human-readable hint for reception when recording payment. */
    public static function reportingHint(PaymentPurpose $purpose, Reservation $reservation, string $currency): string
    {
        $fields = self::resolvePurposeFields($purpose, $reservation);

        return match ($purpose) {
            PaymentPurpose::DebtSettlement => sprintf(
                'Cash drawer: today. Rooms sales report: %s.',
                $fields['revenue_attribution_date'] ?? '—',
            ),
            PaymentPurpose::AdvanceDeposit => sprintf(
                'Cash drawer: today. Rooms sales report: check-in %s (not today\'s room revenue unless arrival is today).',
                $reservation->check_in_date->format('Y-m-d'),
            ),
            PaymentPurpose::CurrentStay => 'Cash drawer and sales report: counted on payment date (today unless back-dated).',
        };
    }

    public static function purposeFromLegacy(bool $isDebtSettlement): PaymentPurpose
    {
        return $isDebtSettlement ? PaymentPurpose::DebtSettlement : PaymentPurpose::CurrentStay;
    }

    protected static function normalizeDateInput(?string $input, ?string $fallbackYmd): ?string
    {
        $raw = trim((string) ($input ?? ''));
        if ($raw !== '') {
            try {
                return Carbon::parse($raw)->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }

        return $fallbackYmd ?: null;
    }
}
