<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case DryGoods = 'dry_goods';
    case FruitsVegetables = 'fruits_vegetables';
    case MeatPoultry = 'meat_poultry';
    case BakeryPastry = 'bakery_pastry';
    case DairyEggs = 'dairy_eggs';
    case NonFoodMaterials = 'non_food_materials';
    case Assets = 'assets';
    case Beverage = 'beverage';

    public function label(): string
    {
        return match ($this) {
            self::DryGoods => 'Dry Goods',
            self::FruitsVegetables => 'Fruits & Vegetables',
            self::MeatPoultry => 'Meat & Poultry',
            self::BakeryPastry => 'Bakery & Pastry',
            self::DairyEggs => 'Dairy & Eggs',
            self::NonFoodMaterials => 'Non-food Materials',
            self::Assets => 'Assets',
            self::Beverage => 'Beverage',
        };
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        return [
            self::DryGoods,
            self::FruitsVegetables,
            self::MeatPoultry,
            self::BakeryPastry,
            self::DairyEggs,
            self::NonFoodMaterials,
            self::Assets,
            self::Beverage,
        ];
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
