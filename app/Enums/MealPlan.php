<?php

namespace App\Enums;

enum MealPlan: string
{
    case BB = 'bb';
    case HB = 'hb';
    case FB = 'fb';
    case COMP = 'comp';

    public function label(): string
    {
        return match ($this) {
            self::BB => 'Bed & breakfast (BB)',
            self::HB => 'Half board (HB)',
            self::FB => 'Full board (FB)',
            self::COMP => 'Complimentary',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::BB => 'BB',
            self::HB => 'HB',
            self::FB => 'FB',
            self::COMP => 'Comp',
        };
    }

    public function includesBreakfast(): bool
    {
        return match ($this) {
            self::BB, self::HB, self::FB, self::COMP => true,
        };
    }

    public function includesLunch(): bool
    {
        return match ($this) {
            self::FB, self::COMP => true,
            default => false,
        };
    }

    public function includesDinner(): bool
    {
        return match ($this) {
            self::HB, self::FB, self::COMP => true,
            default => false,
        };
    }

    public function allowsMealSupplement(): bool
    {
        return $this === self::HB || $this === self::FB;
    }

    /**
     * @return list<self>
     */
    public static function forMeal(string $meal): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $plan) => match ($meal) {
                'breakfast' => $plan->includesBreakfast(),
                'lunch' => $plan->includesLunch(),
                'dinner' => $plan->includesDinner(),
                default => false,
            }
        ));
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower($value));
    }

    public static function parse(?string $value): self
    {
        return self::tryFromString($value) ?? self::BB;
    }

    /** Board options shown in forms (complimentary meals use a separate flag). */
    public static function selectableCases(): array
    {
        return [self::BB, self::HB, self::FB];
    }
}
