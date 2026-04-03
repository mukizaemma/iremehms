<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            HotelSeeder::class, // Must be first - creates the single hotel record
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            ModuleSeeder::class,
            PreparationStationSeeder::class,
            ItemTypeSeeder::class, // Item types for stock classification
            MenuItemTypeSeeder::class, // Menu item types for POS
            SuperAdminSeeder::class,
            AmenitySeeder::class, // General hotel amenities and room amenities
        ]);
    }
}
