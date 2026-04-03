<?php

namespace App\Traits;

use App\Models\Hotel;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;

trait ChecksModuleStatus
{
    /**
     * Check if a module is enabled for the hotel.
     * Super Admin bypasses this check.
     * 
     * @param string|int $moduleSlugOrId Module slug or ID
     * @return bool
     */
    protected function isModuleEnabled($moduleSlugOrId): bool
    {
        $user = Auth::user();
        
        // Super Admin has access to all modules
        if ($user && $user->isSuperAdmin()) {
            return true;
        }

        $hotel = Hotel::getHotel();
        $enabledModuleIds = $hotel->getEnabledModuleIds();

        // If no modules are enabled, deny access (except Super Admin)
        if (empty($enabledModuleIds)) {
            return false;
        }

        // Get module by slug or ID
        if (is_string($moduleSlugOrId)) {
            $module = Module::where('slug', $moduleSlugOrId)->first();
        } else {
            $module = Module::find($moduleSlugOrId);
        }

        if (!$module) {
            return false;
        }

        // Check if module is in enabled modules list
        return in_array($module->id, $enabledModuleIds);
    }

    /**
     * Ensure module is enabled before proceeding.
     * Throws 403 if module is disabled.
     * 
     * @param string|int $moduleSlugOrId Module slug or ID
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function ensureModuleEnabled($moduleSlugOrId): void
    {
        if (!$this->isModuleEnabled($moduleSlugOrId)) {
            abort(403, 'This module is currently disabled. Please contact the system administrator.');
        }
    }
}
