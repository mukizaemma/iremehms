<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Front Office',
                'slug' => 'front-office',
                'description' => 'Guest check-in, check-out, reservations, and front desk. Matches Front Office module.',
                'is_active' => true,
            ],
            [
                'name' => 'Back Office',
                'slug' => 'back-office',
                'description' => 'Management & configuration: rooms, menu, BoM, stations, additional charges. Matches Back Office module.',
                'is_active' => true,
            ],
            [
                'name' => 'POS (Restaurant & Bars)',
                'slug' => 'restaurant',
                'description' => 'Restaurant and bar orders, menu, dining. Matches POS / Restaurant module.',
                'is_active' => true,
            ],
            [
                'name' => 'Stock & Store',
                'slug' => 'store',
                'description' => 'Inventory, stock, requisitions, goods receipt. Matches Stock module.',
                'is_active' => true,
            ],
            [
                'name' => 'Recovery & Credit Control',
                'slug' => 'recovery',
                'description' => 'Unpaid invoices, room charges, credits, accountability. Matches Recovery module.',
                'is_active' => true,
            ],
            [
                'name' => 'Housekeeping',
                'slug' => 'housekeeping',
                'description' => 'Room cleaning and housekeeping operations.',
                'is_active' => true,
            ],
            [
                'name' => 'Maintenance',
                'slug' => 'maintenance',
                'description' => 'Facility maintenance and repairs.',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['slug' => $department['slug']],
                $department
            );
        }
    }
}
