<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Order and names aligned with blueprint "Main Modules" (Back Office, POS, Stock & Logistics, Front Office, Recovery, System Administration)
        $modules = [
            [
                'name' => 'Back Office',
                'slug' => 'back-office',
                'icon' => 'briefcase',
                'description' => 'Management & configuration: stock items, rooms, menu, BoM, stations, cost & margin.',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'POS (Restaurant & Bars)',
                'slug' => 'restaurant',
                'icon' => 'utensils',
                'description' => 'Restaurant and bar orders, menu management, and dining services.',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Stock & Logistics',
                'slug' => 'store',
                'icon' => 'warehouse',
                'description' => 'Inventory management, stock control, and store operations.',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'Front Office (Rooms)',
                'slug' => 'front-office',
                'icon' => 'desk',
                'description' => 'Guest check-in, check-out, and front desk operations.',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'Recovery & Credit Control',
                'slug' => 'recovery',
                'icon' => 'document-search',
                'description' => 'Credit control: unpaid invoices, room charges, credits, accountability.',
                'is_active' => true,
                'order' => 5,
            ],
            [
                'name' => 'System Administration (Super Admin)',
                'slug' => 'settings',
                'icon' => 'cog',
                'description' => 'System settings, configurations, and user management.',
                'is_active' => true,
                'order' => 6,
            ],
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'icon' => 'dashboard',
                'description' => 'Main dashboard with overview and statistics.',
                'is_active' => true,
                'order' => 7,
            ],
            [
                'name' => 'Reports',
                'slug' => 'reports',
                'icon' => 'chart-bar',
                'description' => 'View reports and analytics across departments.',
                'is_active' => true,
                'order' => 8,
            ],
            [
                'name' => 'Housekeeping',
                'slug' => 'housekeeping',
                'icon' => 'broom',
                'description' => 'Room cleaning, maintenance, and housekeeping management.',
                'is_active' => true,
                'order' => 9,
            ],
        ];

        foreach ($modules as $module) {
            $moduleModel = Module::updateOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }

        // Assign all modules to Super Admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $allModules = Module::all();
            $superAdminRole->modules()->sync($allModules->pluck('id'));
        }

        // Assign modules to Manager role (all except settings)
        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole) {
            $managerModules = Module::where('slug', '!=', 'settings')->get();
            $managerRole->modules()->sync($managerModules->pluck('id'));
        }

        // Waiter: Restaurant only
        $waiterRole = Role::where('slug', 'waiter')->first();
        if ($waiterRole) {
            $waiterRole->modules()->sync(Module::where('slug', 'restaurant')->pluck('id'));
        }

        // Receptionist: Front Office only
        $receptionistRole = Role::where('slug', 'receptionist')->first();
        if ($receptionistRole) {
            $receptionistRole->modules()->sync(Module::where('slug', 'front-office')->pluck('id'));
        }

        // Store Keeper: Store only
        $storeKeeperRole = Role::where('slug', 'store-keeper')->first();
        if ($storeKeeperRole) {
            $storeKeeperRole->modules()->sync(Module::where('slug', 'store')->pluck('id'));
        }

        // Controller: Dashboard, Restaurant, Store, Front Office, Reports (audit access)
        $controllerRole = Role::where('slug', 'controller')->first();
        if ($controllerRole) {
            $controllerRole->modules()->sync(Module::whereIn('slug', ['dashboard', 'restaurant', 'store', 'front-office', 'reports'])->pluck('id'));
        }

        // Accountant: Dashboard, Reports, Store (view)
        $accountantRole = Role::where('slug', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->modules()->sync(Module::whereIn('slug', ['dashboard', 'reports', 'store'])->pluck('id'));
        }

        // Barman: Restaurant only
        $barmanRole = Role::where('slug', 'barman')->first();
        if ($barmanRole) {
            $barmanRole->modules()->sync(Module::where('slug', 'restaurant')->pluck('id'));
        }

        // Restaurant Manager: Restaurant + Reports
        $restaurantManagerRole = Role::where('slug', 'restaurant-manager')->first();
        if ($restaurantManagerRole) {
            $restaurantManagerRole->modules()->sync(Module::whereIn('slug', ['restaurant', 'reports'])->pluck('id'));
        }

        // Purchaser: Store only
        $purchaserRole = Role::where('slug', 'purchaser')->first();
        if ($purchaserRole) {
            $purchaserRole->modules()->sync(Module::where('slug', 'store')->pluck('id'));
        }

        // Supervisor: Store (and optionally Restaurant for internal requests)
        $supervisorRole = Role::where('slug', 'supervisor')->first();
        if ($supervisorRole) {
            $supervisorRole->modules()->sync(Module::whereIn('slug', ['store', 'restaurant'])->pluck('id'));
        }

        // Logistics: Store, Reports
        $logisticsRole = Role::where('slug', 'logistics')->first();
        if ($logisticsRole) {
            $logisticsRole->modules()->sync(Module::whereIn('slug', ['store', 'reports'])->pluck('id'));
        }

        // Recovery: Recovery module only
        $recoveryRole = Role::where('slug', 'recovery')->first();
        if ($recoveryRole) {
            $recoveryRole->modules()->sync(Module::where('slug', 'recovery')->pluck('id'));
        }

        // Admin Officer: Front Office, Back Office (limited)
        $adminOfficerRole = Role::where('slug', 'admin-officer')->first();
        if ($adminOfficerRole) {
            $adminOfficerRole->modules()->sync(Module::whereIn('slug', ['front-office', 'back-office', 'dashboard'])->pluck('id'));
        }
    }
}
