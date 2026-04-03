<?php

namespace Database\Seeders;

use App\Models\MenuItemType;
use Illuminate\Database\Seeder;

class MenuItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'FINISHED_GOOD',
                'name' => 'Finished Good',
                'description' => 'Sold as-is from stock. BoM allowed but not required; no need to add BoM versions.',
                'requires_bom' => false,
                'allows_bom' => true,
                'affects_stock' => true,
                'is_active' => true,
            ],
            [
                'code' => 'PREPARED_ITEM',
                'name' => 'Prepared Item',
                'description' => 'Cooked/assembled from ingredients. BoM defines all ingredients consumed.',
                'requires_bom' => true,
                'allows_bom' => true,
                'affects_stock' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SERVICE',
                'name' => 'Service',
                'description' => 'No stock impact. BoM NOT allowed.',
                'requires_bom' => false,
                'allows_bom' => false,
                'affects_stock' => false,
                'is_active' => true,
            ],
            [
                'code' => 'EQUIPMENT',
                'name' => 'Equipment',
                'description' => 'Asset or rentable item. BoM optional, quantity = 0.',
                'requires_bom' => false,
                'allows_bom' => true,
                'affects_stock' => false,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            MenuItemType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }
    }
}
