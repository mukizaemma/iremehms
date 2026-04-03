<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates the single hotel record for this system.
     * There is always exactly one hotel in this system.
     */
    public function run(): void
    {
        Hotel::firstOrCreate(
            ['id' => 1],
            [
                'name' => config('app.name', 'Hotel Management System'),
                'contact' => null,
                'email' => null,
                'address' => null,
                'logo' => null,
                'primary_color' => '#667eea',
                'secondary_color' => '#764ba2',
                'font_family' => 'Heebo',
                'currency' => 'RWF',
                'enabled_modules' => null, // Will be set by Super Admin
                'enabled_departments' => null, // Will be set by Super Admin
                'business_day_rollover_time' => '03:00:00',
                'shifts_enabled' => true,
            ]
        );
    }
}
