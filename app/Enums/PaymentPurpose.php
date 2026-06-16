<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case CurrentStay = 'current_stay';
    case DebtSettlement = 'debt_settlement';
    case AdvanceDeposit = 'advance_deposit';

    public function label(): string
    {
        return match ($this) {
            self::CurrentStay => 'Current stay (folio)',
            self::DebtSettlement => 'Debt settlement (past stay)',
            self::AdvanceDeposit => 'Advance deposit (future stay)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::CurrentStay => 'Current stay',
            self::DebtSettlement => 'Past debt',
            self::AdvanceDeposit => 'Advance',
        };
    }

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::CurrentStay;
    }

    /** @return list<self> */
    public static function selectableCases(): array
    {
        return [self::CurrentStay, self::DebtSettlement, self::AdvanceDeposit];
    }
}
