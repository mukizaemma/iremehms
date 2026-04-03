<?php

namespace Database\Seeders;

use App\Models\ItemType;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $itemTypes = [
            [
                'code' => 'ASSETS',
                'name' => 'Assets',
                'description' => 'Long-life operational assets and equipment (ovens, refrigerators, POS terminals, furniture)',
                'is_consumable' => false,
                'is_sellable' => false,
                'allows_waste' => false,
                'allows_transfer' => true,
                'allows_adjustment' => true,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'code' => 'EXPENSES',
                'name' => 'Expenses',
                'description' => 'Operational expenses and supplies (cleaning supplies, office stationery, utilities)',
                'is_consumable' => true,
                'is_sellable' => false,
                'allows_waste' => true,
                'allows_transfer' => true,
                'allows_adjustment' => true,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'code' => 'FINISHED_PRODUCT',
                'name' => 'Finished Product',
                'description' => 'Ready-for-sale or ready-for-service items (bottled drinks, packaged snacks, minibar items)',
                'is_consumable' => true,
                'is_sellable' => true,
                'allows_waste' => true,
                'allows_transfer' => true,
                'allows_adjustment' => true,
                'is_active' => true,
                'order' => 3,
            ],
            [
                'code' => 'RAW_MATERIAL',
                'name' => 'Raw Material',
                'description' => 'Used as inputs for preparation or service (rice, flour, oil, coffee beans, cleaning chemicals)',
                'is_consumable' => true,
                'is_sellable' => false,
                'allows_waste' => true,
                'allows_transfer' => true,
                'allows_adjustment' => true,
                'is_active' => true,
                'order' => 4,
            ],
            [
                'code' => 'SERVICE',
                'name' => 'Service',
                'description' => 'Service items and intangible products',
                'is_consumable' => false,
                'is_sellable' => true,
                'allows_waste' => false,
                'allows_transfer' => false,
                'allows_adjustment' => true,
                'is_active' => true,
                'order' => 5,
            ],
        ];

        foreach ($itemTypes as $itemType) {
            ItemType::updateOrCreate(
                ['code' => $itemType['code']],
                $itemType
            );
        }
    }
}
