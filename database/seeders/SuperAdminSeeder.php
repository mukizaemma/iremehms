<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if ($superAdminRole) {
            User::updateOrCreate(
                ['email' => 'admin@iremetech.com'],
                [
                    'name' => 'Super Admin',
                    'email' => 'admin@iremetech.com',
                    'password' => Hash::make('Ireme@2021'),
                    'role_id' => $superAdminRole->id,
                    'hotel_id' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
