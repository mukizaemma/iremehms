<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Controls system-wide configurations, manages hotel packages, features, and branding, assigns admins and departments.',
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Oversees all departments in the hotel, views reports across all departments, manages users within departments, manages shifts and approvals.',
            ],
            [
                'name' => 'Department Admin',
                'slug' => 'department-admin',
                'description' => 'Manages operations of a specific department (Front Office, Restaurant, Store, etc.), views department-level reports.',
            ],
            [
                'name' => 'Department User',
                'slug' => 'department-user',
                'description' => 'Generic department staff (e.g. storekeeper, cleaner) with limited access to assigned modules.',
            ],
            [
                'name' => 'Waiter',
                'slug' => 'waiter',
                'description' => 'Handles restaurant orders for assigned tables; limited to their own orders, tables, and sales.',
            ],
            [
                'name' => 'Cashier',
                'slug' => 'cashier',
                'description' => 'Handles payments and billing at POS; can receive payments across orders.',
            ],
            [
                'name' => 'Receptionist',
                'slug' => 'receptionist',
                'description' => 'Front-desk staff handling guest check-ins, check-outs, and room-related sales.',
            ],
            [
                'name' => 'Store Keeper',
                'slug' => 'store-keeper',
                'description' => 'Manages store inventory, stock requisitions, movements, and goods receipts.',
            ],
            [
                'name' => 'Controller',
                'slug' => 'controller',
                'description' => 'Audit sales, stock, shifts; validate variances. Scope configurable: GLOBAL (entire hotel) or per DEPARTMENT.',
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Views costs & revenues, profit margin analysis, no operational edits, prepares financial exports.',
            ],
            [
                'name' => 'Barman',
                'slug' => 'barman',
                'description' => 'Prepares and serves bar orders; limited to bar station.',
            ],
            [
                'name' => 'Restaurant Manager',
                'slug' => 'restaurant-manager',
                'description' => 'Approves voids, transfers; oversees restaurant operations.',
            ],
            [
                'name' => 'Purchaser',
                'slug' => 'purchaser',
                'description' => 'Creates purchase requisitions.',
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'description' => 'Requests internal stock; department supervisor.',
            ],
            [
                'name' => 'Logistics',
                'slug' => 'logistics',
                'description' => 'Oversees assets and stock valuation.',
            ],
            [
                'name' => 'Recovery',
                'slug' => 'recovery',
                'description' => 'Credit control: unpaid invoices, room charges, credits; follow up and flag accountability.',
            ],
            [
                'name' => 'Admin Officer',
                'slug' => 'admin-officer',
                'description' => 'Front office admin; supports reception and back office.',
            ],
            [
                'name' => 'PR Officer',
                'slug' => 'pr-officer',
                'description' => 'Front office PR/marketing: can view guest lists and send one-to-one or bulk communications.',
            ],
            // Ireme (platform) role
            [
                'name' => 'Ireme Accountant',
                'slug' => 'ireme-accountant',
                'description' => 'Platform accountant: view hotels, subscriptions, payments, generate/send invoices, confirm payments, view requests.',
            ],
            // Hotel-level leadership (assigned by Ireme when onboarding)
            [
                'name' => 'Hotel Admin',
                'slug' => 'hotel-admin',
                'description' => 'Hotel administrator: full access within the hotel, manages users and config.',
            ],
            [
                'name' => 'Director',
                'slug' => 'director',
                'description' => 'Hotel director: oversight and strategic access within the hotel.',
            ],
            [
                'name' => 'General Manager',
                'slug' => 'general-manager',
                'description' => 'Hotel general manager: day-to-day operations and reports.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
