<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'hotel_id',
        'role_id',
        'department_id',
        'package_id',
        'is_active',
        'email_verified',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'email_verified' => 'boolean',
    ];

    /**
     * Get the role that owns the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the hotel this user belongs to (null = Ireme platform user).
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Get the primary department (single, for backward compatibility).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * All departments assigned to this user (many-to-many).
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    /**
     * Get the package that owns the user.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(HotelPackage::class);
    }

    /**
     * Get the modules that the user has access to.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_user');
    }

    /**
     * User-level permission assignments (additive to role permissions).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Get the role used for UI and module access (for Super Admin "view as" switching).
     * Only Super Admin can "act as" another role; others always use their real role.
     */
    public function getEffectiveRole(): ?Role
    {
        $role = $this->role;
        if (! $role) {
            return null;
        }
        if ($role->slug !== 'super-admin') {
            return $role;
        }
        $actingId = session('acting_as_role_id');
        if ($actingId === null || $actingId === '') {
            return $role;
        }
        $acting = Role::find($actingId);

        return $acting ?? $role;
    }

    /**
     * Check if user is super admin (real role, for authorization).
     */
    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->slug === 'super-admin';
    }

    /**
     * Platform (Ireme) user: no hotel_id. Can access Ireme dashboard.
     */
    public function isIremeUser(): bool
    {
        return $this->hotel_id === null;
    }

    /**
     * Ireme accountant role (platform-side only).
     */
    public function isIremeAccountant(): bool
    {
        return $this->isIremeUser() && $this->role && $this->role->slug === 'ireme-accountant';
    }

    /**
     * Check if effective role is super admin (for sidebar / what to show).
     */
    public function isEffectiveSuperAdmin(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'super-admin';
    }

    /**
     * Check if user is manager (real role, for authorization).
     */
    public function isManager(): bool
    {
        return $this->role && $this->role->slug === 'manager';
    }

    /**
     * Check if effective role is manager (for sidebar / what to show).
     */
    public function isEffectiveManager(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'manager';
    }

    /**
     * Check if effective role is director (full module navigation, no Ireme details).
     */
    public function isEffectiveDirector(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'director';
    }

    /**
     * Check if effective role is general manager (full module navigation, no Ireme details).
     */
    public function isEffectiveGeneralManager(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'general-manager';
    }

    /**
     * Whether the user may delete a room type that has no rooms and no reservations (Director, Manager, GM, Hotel Admin, or Super Admin).
     */
    public function canDeleteRoomTypeWhenUnused(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $effective = $this->getEffectiveRole();
        if (! $effective) {
            return false;
        }

        return in_array($effective->slug, ['director', 'manager', 'general-manager', 'hotel-admin'], true);
    }

    /**
     * Whether user can navigate full modules (Director, GM, Manager, Hotel Admin).
     */
    public function canNavigateModules(): bool
    {
        $effective = $this->getEffectiveRole();
        if (! $effective) {
            return false;
        }

        return in_array($effective->slug, ['manager', 'director', 'general-manager', 'hotel-admin', 'accountant'], true);
    }

    /**
     * Check if user is department admin.
     */
    public function isDepartmentAdmin(): bool
    {
        return $this->role && $this->role->slug === 'department-admin';
    }

    /**
     * Check if user is department user.
     */
    public function isDepartmentUser(): bool
    {
        return $this->role && $this->role->slug === 'department-user';
    }

    /**
     * Check if user is waiter.
     */
    public function isWaiter(): bool
    {
        return $this->role && $this->role->slug === 'waiter';
    }

    /**
     * Check if user is restaurant manager (real role).
     */
    public function isRestaurantManager(): bool
    {
        return $this->role && $this->role->slug === 'restaurant-manager';
    }

    /**
     * Check if user is cashier.
     */
    public function isCashier(): bool
    {
        return $this->role && $this->role->slug === 'cashier';
    }

    /**
     * Check if user is receptionist.
     */
    public function isReceptionist(): bool
    {
        return $this->role && $this->role->slug === 'receptionist';
    }

    /**
     * Check if effective role is waiter (for sidebar).
     */
    public function isEffectiveWaiter(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'waiter';
    }

    /**
     * Check if effective role is receptionist (for sidebar).
     */
    public function isEffectiveReceptionist(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'receptionist';
    }

    /**
     * Check if effective role is store keeper (for sidebar). Role slug: store-keeper.
     */
    public function isEffectiveStoreKeeper(): bool
    {
        $effective = $this->getEffectiveRole();

        return $effective && $effective->slug === 'store-keeper';
    }

    /**
     * Stock analytics reports (summary, movements, opening/closing, by location).
     * Includes users with {@see self::isEffectiveStoreKeeper()} or {@see self::hasPermission()} {@code back_office_stock_items}
     * so store staff seeded with only stock-item permission still see reports in the sidebar.
     */
    public function canViewStockReports(): bool
    {
        $module = Module::where('slug', 'store')->first();
        if (! $module || ! $this->hasModuleAccess($module->id)) {
            return false;
        }

        return $this->isSuperAdmin()
            || $this->canNavigateModules()
            || $this->hasPermission('stock_audit')
            || $this->hasPermission('stock_logistics')
            || $this->hasPermission('reports_view_all')
            || $this->isEffectiveStoreKeeper()
            || $this->hasPermission('back_office_stock_items');
    }

    /**
     * Whether the user can add, edit, or delete stock master records (items).
     */
    public function canManageStockItems(): bool
    {
        if ($this->isSuperAdmin() || $this->isManager()) {
            return true;
        }
        if ($this->isEffectiveStoreKeeper()) {
            return true;
        }
        // Cashier dashboard links to stock (e.g. expense items); allow manage when they can reach the page
        if ($this->getEffectiveRole()?->slug === 'cashier') {
            return true;
        }

        return $this->hasPermission('back_office_stock_items');
    }

    /**
     * Whether the user can create or edit main / sub-stock locations (substocks).
     */
    public function canManageStockLocations(): bool
    {
        if ($this->isSuperAdmin() || $this->isManager()) {
            return true;
        }
        if ($this->isEffectiveStoreKeeper()) {
            return true;
        }

        return $this->hasPermission('back_office_stock_items');
    }

    /**
     * Get accessible modules for the user based on role and hotel configuration.
     * When Super Admin is "acting as" another role, returns only that role's modules.
     *
     * CORE PRINCIPLE: Single hotel system
     * - Super Admin: Access to ALL active modules (unless acting as another role)
     * - Manager: Access to hotel-enabled modules only
     * - Department Admin/User: Access based on role + hotel-enabled modules
     */
    public function getAccessibleModules()
    {
        $effectiveRole = $this->getEffectiveRole();
        if (! $effectiveRole) {
            return collect();
        }

        $hotel = \App\Models\Hotel::getHotel();
        // Ireme (platform) users do not use hotel modules; they use Ireme dashboard.
        if (! $hotel) {
            return collect();
        }

        $enabledModuleIds = $hotel->getEnabledModuleIds();

        // Effective Super Admin (and not acting as another role): full access
        if ($effectiveRole->slug === 'super-admin') {
            return Module::where('is_active', true)
                ->orderBy('order')
                ->get();
        }

        // Effective Manager, Director, General Manager, Hotel Admin: hotel-enabled modules only (full module navigation)
        if (in_array($effectiveRole->slug, ['manager', 'director', 'general-manager', 'hotel-admin'])) {
            if (empty($enabledModuleIds)) {
                return collect();
            }

            return Module::whereIn('id', $enabledModuleIds)
                ->where('is_active', true)
                ->orderBy('order')
                ->get();
        }

        // Other roles: role's modules + user's direct modules (when not acting), filtered by hotel
        $roleModules = $effectiveRole->modules()->where('is_active', true)->get();
        $userModules = $this->role_id === $effectiveRole->id
            ? $this->modules()->where('is_active', true)->get()
            : collect();
        $allModules = $roleModules->merge($userModules)->unique('id');

        if (! empty($enabledModuleIds)) {
            $allModules = $allModules->filter(function ($module) use ($enabledModuleIds) {
                return in_array($module->id, $enabledModuleIds);
            });
        }

        return $allModules->sortBy('order')->values();
    }

    /**
     * Check if user has a specific permission (role OR direct user assignment).
     * Selling and sensitive actions must be explicit; default = no permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        $effectiveRole = $this->getEffectiveRole();
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($effectiveRole && $effectiveRole->hasPermission($permissionSlug)) {
            return true;
        }

        return $this->permissions()->where('slug', $permissionSlug)->where('is_active', true)->exists();
    }

    /**
     * Check if user has access to a module (uses effective role when Super Admin is acting as another).
     *
     * CORE PRINCIPLE: Single hotel system
     * - Super Admin: Access to ALL modules (unless acting as another role)
     * - Manager: Access to hotel-enabled modules only
     * - Others: Role-based + hotel-enabled modules
     */
    public function hasModuleAccess($moduleId): bool
    {
        $effectiveRole = $this->getEffectiveRole();
        if (! $effectiveRole) {
            return false;
        }

        if ($effectiveRole->slug === 'super-admin') {
            return true;
        }

        $hotel = \App\Models\Hotel::getHotel();
        if (! $hotel) {
            return false;
        }

        $enabledModuleIds = $hotel->getEnabledModuleIds();

        if (in_array($effectiveRole->slug, ['manager', 'director', 'general-manager', 'hotel-admin'])) {
            return in_array($moduleId, $enabledModuleIds);
        }

        $hasDirectAccess = $this->role_id === $effectiveRole->id
            && $this->modules()->where('modules.id', $moduleId)->exists();
        $hasRoleAccess = $effectiveRole->hasModule($moduleId);

        if ($hasDirectAccess || $hasRoleAccess) {
            if (! empty($enabledModuleIds)) {
                return in_array($moduleId, $enabledModuleIds);
            }

            return true;
        }

        return false;
    }

    /**
     * Active users assigned to a hotel with an exact role slug (e.g. waiter).
     * If $hotelId is null, returns no rows — avoids listing users from other hotels.
     */
    public function scopeActiveInHotelWithRoleSlug(Builder $query, ?int $hotelId, string $roleSlug): Builder
    {
        $query->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', $roleSlug));

        if ($hotelId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('hotel_id', $hotelId);
    }

    /**
     * Active users in a hotel matching any of the given role slugs.
     */
    public function scopeActiveInHotelWithRoleSlugs(Builder $query, ?int $hotelId, array $roleSlugs): Builder
    {
        $query->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $roleSlugs));

        if ($hotelId === null || $roleSlugs === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('hotel_id', $hotelId);
    }
}
