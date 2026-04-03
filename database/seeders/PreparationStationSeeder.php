<?php

namespace Database\Seeders;

use App\Models\PreparationStation;
use Illuminate\Database\Seeder;

class PreparationStationSeeder extends Seeder
{
    public function run(): void
    {
        $stations = [
            ['name' => 'Kitchen', 'slug' => 'kitchen', 'display_order' => 1],
            ['name' => 'Bar', 'slug' => 'bar', 'display_order' => 2],
            ['name' => 'Coffee Station', 'slug' => 'coffee_station', 'display_order' => 3],
            ['name' => 'Grill', 'slug' => 'grill', 'display_order' => 4],
            ['name' => 'Pastry', 'slug' => 'pastry', 'display_order' => 5],
        ];

        foreach ($stations as $i => $s) {
            PreparationStation::updateOrCreate(
                ['slug' => $s['slug']],
                array_merge($s, ['is_active' => true])
            );
        }
    }
}
