<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the roles that have access to this module.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_module');
    }

    /**
     * Get the users that have access to this module.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'module_user');
    }

    /**
     * Check if this module is enabled for the hotel.
     * Super Admin bypasses this check.
     * 
     * @return bool
     */
    public function isEnabledForHotel(): bool
    {
        $hotel = \App\Models\Hotel::getHotel();
        $enabledModuleIds = $hotel->getEnabledModuleIds();

        // If no modules are enabled, deny access (except Super Admin)
        if (empty($enabledModuleIds)) {
            return false;
        }

        return in_array($this->id, $enabledModuleIds);
    }
}
