<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('slug', 'store-keeper')->first();
        $perm = Permission::where('slug', 'stock_authorize_requests')->first();
        if ($role && $perm && ! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
            $role->permissions()->attach($perm->id);
        }
    }

    public function down(): void
    {
        $role = Role::where('slug', 'store-keeper')->first();
        $perm = Permission::where('slug', 'stock_authorize_requests')->first();
        if ($role && $perm) {
            $role->permissions()->detach($perm->id);
        }
    }
};
