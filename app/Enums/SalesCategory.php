<?php

namespace App\Enums;

enum SalesCategory: string
{
    case Food = 'food';
    case Beverage = 'beverage';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food',
            self::Beverage => 'Beverage',
        };
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
