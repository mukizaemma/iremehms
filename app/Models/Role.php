<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the users for the role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the modules for the role.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'role_module');
    }

    /**
     * Permissions assigned to this role (granular: sell, void, approve, etc.).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Department scope for this role (e.g. Controller: global vs per-department).
     */
    public function roleDepartments(): HasMany
    {
        return $this->hasMany(RoleDepartment::class);
    }

    /**
     * Check if role has access to a module.
     */
    public function hasModule($moduleId): bool
    {
        return $this->modules()->where('modules.id', $moduleId)->exists();
    }

    /**
     * Check if role has a specific permission by slug.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->where('is_active', true)->exists();
    }
}

