<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use App\Models\Module;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class IremeUserPermissions extends Component
{
    /** @var Hotel */
    public $hotel;

    /** @var User */
    public $user;

    public $modules;
    public $permissions;
    public $selectedModules = [];
    public $selectedPermissions = [];

    public function mount($hotel, $user)
    {
        if (!Auth::user() || !Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can manage hotel user permissions from Ireme.');
        }

        $this->hotel = $hotel instanceof Hotel ? $hotel : Hotel::findOrFail($hotel);
        $this->user = $user instanceof User ? $user : User::findOrFail($user);

        if ($this->user->hotel_id != $this->hotel->id) {
            abort(404, 'User does not belong to this hotel.');
        }

        if ($this->user->role && $this->user->role->slug === 'super-admin') {
            abort(403, 'Super Admin users have full access; no need to manage permissions.');
        }

        $enabledModuleIds = $this->hotel->getEnabledModuleIds();
        $this->modules = ! empty($enabledModuleIds)
            ? Module::whereIn('id', $enabledModuleIds)
                ->where('is_active', true)
                ->orderBy('order')
                ->get()
            : collect();

        $moduleSlugs = $this->modules->pluck('slug')->filter()->unique()->values()->all();
        if ($moduleSlugs === []) {
            $this->permissions = collect();
        } else {
            $this->permissions = Permission::where('is_active', true)
                ->whereIn('module_slug', $moduleSlugs)
                ->orderBy('module_slug')
                ->orderBy('name')
                ->get();
        }

        // Ireme platform permissions are managed separately; hotel staff should not get them from this screen
        $this->permissions = $this->permissions->filter(fn ($p) => ! str_starts_with($p->slug ?? '', 'ireme_'))->values();

        $allowedModuleIds = $this->modules->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->selectedModules = array_values(array_intersect(
            $this->user->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all(),
            $allowedModuleIds
        ));
        $allowedPermIds = $this->permissions->pluck('id')->all();
        $this->selectedPermissions = array_values(array_intersect(
            $this->user->permissions()->pluck('permissions.id')->all(),
            $allowedPermIds
        ));
    }

    public function selectAllModules()
    {
        $this->selectedModules = $this->modules->pluck('id')->toArray();
    }

    public function clearModules()
    {
        $this->selectedModules = [];
    }

    public function selectAllPermissions()
    {
        $this->selectedPermissions = $this->permissions->pluck('id')->toArray();
    }

    public function clearPermissions()
    {
        $this->selectedPermissions = [];
    }

    public function save()
    {
        $allowedModuleIds = $this->modules->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedPermIds = $this->permissions->pluck('id')->all();
        $moduleIds = array_values(array_intersect($this->selectedModules ?? [], $allowedModuleIds));
        $permIds = array_values(array_intersect($this->selectedPermissions ?? [], $allowedPermIds));

        $this->user->modules()->sync($moduleIds);
        $this->user->permissions()->sync($permIds);

        UserPermissionRequest::where('user_id', $this->user->id)
            ->where('status', UserPermissionRequest::STATUS_PENDING)
            ->whereIn('permission_id', $permIds)
            ->update([
                'status' => UserPermissionRequest::STATUS_APPROVED,
                'approved_by_id' => Auth::id(),
                'approved_at' => now(),
            ]);

        session()->flash('message', 'Permissions updated successfully.');
    }

    public function render()
    {
        return view('livewire.ireme.ireme-user-permissions')
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Manage Permissions – ' . $this->user->name]);
    }
}
