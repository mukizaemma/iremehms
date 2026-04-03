<?php

namespace App\Support;

/**
 * Canonical payment methods and settlement statuses used across Front Office and POS.
 */
final class PaymentCatalog
{
    public const METHOD_CASH = 'Cash';

    public const METHOD_MOMO = 'MoMo';

    public const METHOD_POS_CARD = 'POS Card';

    public const METHOD_BANK = 'Bank';

    public const STATUS_PAID = 'Paid';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_DEBITS = 'Debits';

    public const STATUS_OFFER = 'Offer';

    /**
     * Single-dropdown options for accommodation / folio payments (method + settlement in one choice).
     *
     * @return array<string, string> value => label
     */
    public static function unifiedAccommodationOptions(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_MOMO => 'MoMo',
            self::METHOD_POS_CARD => 'POS / Card',
            self::METHOD_BANK => 'Bank',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_DEBITS => 'Debit / on account',
            self::STATUS_OFFER => 'Offer (hotel covered)',
        ];
    }

    /** @return list<string> */
    public static function unifiedAccommodationValues(): array
    {
        return array_keys(self::unifiedAccommodationOptions());
    }

    public static function normalizeUnifiedChoice(string $choice): string
    {
        $t = trim($choice);
        foreach (self::unifiedAccommodationValues() as $v) {
            if (strcasecmp($t, $v) === 0) {
                return $v;
            }
        }

        return self::METHOD_CASH;
    }

    /** Pending / Debit require guest or account reference notes when recording. */
    public static function unifiedChoiceRequiresClientDetails(string $choice): bool
    {
        $u = self::normalizeUnifiedChoice($choice);

        return $u === self::STATUS_PENDING || $u === self::STATUS_DEBITS;
    }

    /**
     * Map one UI value to persisted columns (payment_method stores the same unified value for easy reporting).
     *
     * @return array{payment_method: string, payment_status: string}
     */
    public static function expandUnifiedToStorage(string $choice, bool $cashHeldByWaiterUntilShiftEnd = false): array
    {
        $u = self::normalizeUnifiedChoice($choice);
        if ($u === self::METHOD_CASH && $cashHeldByWaiterUntilShiftEnd) {
            return [
                'payment_method' => self::METHOD_CASH,
                'payment_status' => self::STATUS_PENDING,
            ];
        }

        $status = match ($u) {
            self::STATUS_PENDING => self::STATUS_PENDING,
            self::STATUS_DEBITS => self::STATUS_DEBITS,
            self::STATUS_OFFER => self::STATUS_OFFER,
            default => self::STATUS_PAID,
        };

        return [
            'payment_method' => $u,
            'payment_status' => $status,
        ];
    }

    /**
     * Load a stored row into the single dropdown (legacy rows: infer from status when needed).
     */
    public static function collapseStorageToUnified(?string $payment_method, ?string $payment_status): string
    {
        $s = self::normalizeStatus($payment_status);
        if ($s === self::STATUS_PENDING) {
            $m = trim((string) $payment_method);
            if (strcasecmp($m, self::METHOD_CASH) === 0) {
                return self::METHOD_CASH;
            }

            return self::STATUS_PENDING;
        }
        if ($s === self::STATUS_DEBITS) {
            return self::STATUS_DEBITS;
        }
        if ($s === self::STATUS_OFFER) {
            return self::STATUS_OFFER;
        }

        $m = trim((string) $payment_method);
        foreach ([self::METHOD_CASH, self::METHOD_MOMO, self::METHOD_POS_CARD, self::METHOD_BANK] as $v) {
            if (strcasecmp($m, $v) === 0) {
                return $v;
            }
        }

        return self::normalizeReservationMethod($m);
    }

    /** @return array<string, string> value => label */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH => self::METHOD_CASH,
            self::METHOD_MOMO => self::METHOD_MOMO,
            self::METHOD_POS_CARD => self::METHOD_POS_CARD,
            self::METHOD_BANK => self::METHOD_BANK,
        ];
    }

    /** @return list<string> */
    public static function methodValues(): array
    {
        return array_keys(self::methods());
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PAID => self::STATUS_PAID,
            self::STATUS_PENDING => self::STATUS_PENDING,
            self::STATUS_DEBITS => self::STATUS_DEBITS,
            self::STATUS_OFFER => self::STATUS_OFFER,
        ];
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return array_keys(self::statuses());
    }

    public static function validationRuleMethods(): string
    {
        return 'in:' . implode(',', self::methodValues());
    }

    public static function validationRuleStatuses(): string
    {
        return 'in:' . implode(',', self::statusValues());
    }

    public static function validationRuleUnifiedAccommodation(): string
    {
        return 'in:' . implode(',', self::unifiedAccommodationValues());
    }

    /**
     * POS / invoice payment uses the same canonical values as accommodation (stored in payments.payment_method).
     */
    public static function unifiedPosOptions(): array
    {
        return self::unifiedAccommodationOptions();
    }

    /** @return list<string> */
    public static function unifiedPosValues(): array
    {
        return self::unifiedAccommodationValues();
    }

    public static function validationRuleUnifiedPos(): string
    {
        return 'in:' . implode(',', self::unifiedPosValues());
    }

    /** @return list<string> */
    public static function accommodationReportBucketKeys(): array
    {
        return self::unifiedAccommodationValues();
    }

    /** @return array<string, string> bucket key => label */
    public static function accommodationReportBucketLabels(): array
    {
        return self::unifiedAccommodationOptions();
    }

    public static function accommodationPaymentReportBucket(?string $payment_method, ?string $payment_status): string
    {
        $m = self::normalizeReservationMethod($payment_method ?? '');
        $s = self::normalizeStatus($payment_status ?? self::STATUS_PAID);
        // Cash held for shift-end submission and other pending lines all use the Pending bucket on reports.
        if ($s === self::STATUS_PENDING) {
            return self::STATUS_PENDING;
        }

        return $m;
    }

    public static function formatPaymentLineForReport(?string $payment_method, ?string $payment_status): string
    {
        $key = self::accommodationPaymentReportBucket($payment_method, $payment_status);
        $labels = self::accommodationReportBucketLabels();

        return $labels[$key] ?? $key;
    }

    public static function mergeClientReferenceIntoComment(?string $comment, ?string $clientReference): string
    {
        $c = trim((string) $comment);
        $d = trim((string) $clientReference);
        if ($d === '') {
            return $c;
        }
        $tag = 'Client / account ref: ' . $d;
        if ($c === '') {
            return $tag;
        }

        return $c . "\n\n" . $tag;
    }

    /** Map legacy POS enum / strings to canonical method. */
    public static function normalizePosMethod(?string $raw): string
    {
        $u = strtoupper(trim((string) $raw));

        return match ($u) {
            'CASH' => self::METHOD_CASH,
            'MOMO', 'MOMO_PERSONAL', 'MOMO_HOTEL' => self::METHOD_MOMO,
            'CARD' => self::METHOD_POS_CARD,
            'CREDIT' => self::METHOD_BANK,
            'PENDING' => self::STATUS_PENDING,
            'DEBITS', 'DEBIT' => self::STATUS_DEBITS,
            'OFFER' => self::STATUS_OFFER,
            default => self::canonicalMethodFromLoose($raw),
        };
    }

    /** Normalize free-text method (reservation payments, imports). */
    public static function normalizeReservationMethod(?string $raw): string
    {
        return self::canonicalMethodFromLoose($raw);
    }

    public static function normalizeStatus(?string $raw): string
    {
        $t = trim((string) $raw);
        foreach (self::statusValues() as $v) {
            if (strcasecmp($t, $v) === 0) {
                return $v;
            }
        }
        $l = strtolower($t);
        if (in_array($l, ['confirmed'], true)) {
            return self::STATUS_PAID;
        }
        if (in_array($l, ['credit', 'city ledger', 'ledger'], true)) {
            return self::STATUS_DEBITS;
        }
        if (in_array($l, ['complimentary', 'hotel covered', 'comp', 'offer'], true)) {
            return self::STATUS_OFFER;
        }
        if (in_array($l, ['pending', 'unpaid'], true)) {
            return self::STATUS_PENDING;
        }

        return $t !== '' ? $t : self::STATUS_PAID;
    }

    /**
     * Whether this reservation payment line should increase "paid" on the folio.
     * Pending = not yet received; others count toward guest settlement.
     */
    public static function reservationPaymentCountsTowardPaid(?string $paymentStatus): bool
    {
        return self::normalizeStatus($paymentStatus) !== self::STATUS_PENDING;
    }

    protected static function canonicalMethodFromLoose(?string $raw): string
    {
        $t = trim((string) $raw);
        foreach (self::unifiedAccommodationValues() as $v) {
            if (strcasecmp($t, $v) === 0) {
                return $v;
            }
        }
        if (strcasecmp($t, 'POS / Card') === 0 || strcasecmp($t, 'Card') === 0) {
            return self::METHOD_POS_CARD;
        }
        foreach (self::methodValues() as $v) {
            if (strcasecmp($t, $v) === 0) {
                return $v;
            }
        }
        $l = strtolower($t);
        if (str_contains($l, 'momo') || str_contains($l, 'mobile') || str_contains($l, 'mtn') || str_contains($l, 'airtel')) {
            return self::METHOD_MOMO;
        }
        if (str_contains($l, 'bank') || str_contains($l, 'transfer') || str_contains($l, 'cheque') || str_contains($l, 'neft') || str_contains($l, 'swift')) {
            return self::METHOD_BANK;
        }
        if (str_contains($l, 'card') || str_contains($l, 'pos') || str_contains($l, 'visa') || str_contains($l, 'master')) {
            return self::METHOD_POS_CARD;
        }
        if ($l === 'cash' || $t === '') {
            return self::METHOD_CASH;
        }

        return self::METHOD_CASH;
    }
}
