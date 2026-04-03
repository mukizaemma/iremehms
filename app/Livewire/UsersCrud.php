<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class UsersCrud extends Component
{
    public $users = [];
    public $showForm = false;
    public $editingUserId = null;
    
    // Form fields
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role_id = '';
    public $department_id = '';
    public $selectedDepartments = [];
    public $is_active = true;
    public $email_verified = false;
    
    public $roles = [];
    public $departments = [];
    public $modules = [];
    public $permissions = []; // all active permissions (for grouping by module in view)
    public $selectedModules = [];
    public $selectedPermissions = [];
    public $search = '';

    /** For "View modules & permissions" modal */
    public $viewingUser = null;
    public $showModulesPermissionsModal = false;

    public function mount()
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('hotel_manage_users')) {
            abort(403, 'Only Super Admin or users with Hotel: Manage users permission can access Users.');
        }

        $this->loadUsers();
        $this->roles = Role::whereNotIn('slug', ['super-admin', 'department-user'])->get();
        $this->loadDepartmentsForHotelContext();

        $this->loadModulesAndPermissionsForHotelContext($user);
    }

    /**
     * Modules = only those enabled for the current hotel (pivot / enabled_modules).
     * Permissions = only those whose module_slug matches those modules (never the full system list).
     * Without a hotel context (e.g. Ireme Super Admin), all active modules are available.
     */
    /**
     * Same rules as Departments management: only departments enabled for this hotel (JSON + implied by modules).
     */
    protected function loadDepartmentsForHotelContext(): void
    {
        $hotel = \App\Models\Hotel::getHotel();
        if ($hotel) {
            $deptIds = $hotel->getDepartmentIdsForAssignments();
            $this->departments = $deptIds !== []
                ? Department::where('is_active', true)->whereIn('id', $deptIds)->orderBy('name')->get()
                : collect();
        } else {
            $this->departments = Department::where('is_active', true)->orderBy('name')->get();
        }
    }

    protected function loadModulesAndPermissionsForHotelContext(User $actingUser): void
    {
        $hotel = \App\Models\Hotel::getHotel();

        if ($hotel) {
            $enabledIds = $hotel->getEnabledModuleIds();
            $this->modules = ! empty($enabledIds)
                ? Module::whereIn('id', $enabledIds)
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get()
                : collect();
        } else {
            $this->modules = Module::where('is_active', true)->orderBy('order')->get();
        }

        $moduleSlugs = $this->modules->pluck('slug')->filter()->unique()->values()->all();

        if ($moduleSlugs === []) {
            $this->permissions = collect();
        } else {
            $query = Permission::where('is_active', true)
                ->whereIn('module_slug', $moduleSlugs)
                ->orderBy('module_slug')
                ->orderBy('name');

            $this->permissions = $query->get();
        }

        // Hotel context: never offer Ireme platform permissions on this screen
        if ($hotel) {
            $this->permissions = $this->permissions->filter(fn ($p) => ! str_starts_with($p->slug ?? '', 'ireme_'))->values();
        } elseif (! $actingUser->isSuperAdmin() && ($actingUser->isEffectiveDirector() || $actingUser->isEffectiveGeneralManager())) {
            $this->permissions = $this->permissions->filter(fn ($p) => ! str_starts_with($p->slug ?? '', 'ireme_'))->values();
        }
    }

    /**
     * @return array<int, int>
     */
    protected function sanitizedSelectedModuleIds(): array
    {
        $allowed = $this->modules->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect($this->selectedModules ?? [], $allowed));
    }

    /**
     * @return array<int, int>
     */
    protected function sanitizedSelectedPermissionIds(): array
    {
        $allowed = $this->permissions->pluck('id')->all();

        return array_values(array_intersect($this->selectedPermissions ?? [], $allowed));
    }

    /** Only Director, General Manager, and Super Admin can assign modules & permissions. Super Admin can always assign (including when viewing as another role). */
    public function canAssignModulesAndPermissions(): bool
    {
        $user = Auth::user();
        return $user->isSuperAdmin() || $user->isEffectiveDirector() || $user->isEffectiveGeneralManager();
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

    public function loadUsers()
    {
        $query = User::with(['role', 'department']);

        $currentUser = Auth::user();
        if (!$currentUser->isSuperAdmin()) {
            $query->where('hotel_id', $currentUser->hotel_id);
            $query->whereHas('role', function ($q) {
                $q->where('slug', '!=', 'super-admin');
            });
        }

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        $this->users = $query->with('departments')->get();
    }

    public function updatedSearch()
    {
        $this->loadUsers();
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editingUserId = null;
    }

    public function edit($userId)
    {
        $user = User::with('role')->findOrFail($userId);
        if (!Auth::user()->isSuperAdmin() && $user->role && $user->role->slug === 'super-admin') {
            abort(403, 'You cannot edit Super Admin users.');
        }

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = $user->role_id;
        $this->department_id = $user->department_id;
        $this->selectedDepartments = $user->departments()->pluck('departments.id')->toArray();
        $this->is_active = $user->is_active;
        $this->email_verified = $user->email_verified;
        $this->password = '';
        $this->password_confirmation = '';
        
        // Load user's assigned modules and direct permissions (only those valid for this hotel's modules)
        $allowedModuleIds = $this->modules->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->selectedModules = array_values(array_intersect(
            $user->modules()->pluck('modules.id')->map(fn ($id) => (int) $id)->all(),
            $allowedModuleIds
        ));
        $allowedPermIds = $this->permissions->pluck('id')->all();
        $this->selectedPermissions = array_values(array_intersect(
            $user->permissions()->pluck('permissions.id')->all(),
            $allowedPermIds
        ));

        $this->showForm = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editingUserId ? ',' . $this->editingUserId : ''),
            'role_id' => 'required|exists:roles,id',
            'department_id' => 'nullable|exists:departments,id',
            'selectedDepartments' => 'nullable|array',
            'selectedDepartments.*' => 'exists:departments,id',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
        ];

        if ($this->editingUserId) {
            // Updating existing user
            if ($this->password) {
                $rules['password'] = 'required|string|min:8|confirmed';
            }
        } else {
            // Creating: if a user with this email already exists but has no hotel, assign them to current user's hotel
            $existingUser = User::where('email', $this->email)->first();
            $currentUser = Auth::user();
            $hotelId = $currentUser->hotel_id ?? \App\Models\Hotel::getHotel()?->id;
            if ($existingUser && $existingUser->hotel_id === null && $hotelId) {
                $this->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email',
                    'role_id' => 'required|exists:roles,id',
                    'department_id' => 'nullable|exists:departments,id',
                    'selectedDepartments' => 'nullable|array',
                    'selectedDepartments.*' => 'exists:departments,id',
                    'is_active' => 'boolean',
                    'email_verified' => 'boolean',
                    'password' => 'nullable|string|min:8|confirmed',
                ]);
                $primaryDept = is_array($this->selectedDepartments) && count($this->selectedDepartments) > 0
                    ? $this->selectedDepartments[0]
                    : null;
                $existingUser->update([
                    'name' => $this->name,
                    'role_id' => $this->role_id,
                    'department_id' => $primaryDept,
                    'is_active' => $this->is_active,
                    'email_verified' => $this->email_verified,
                    'hotel_id' => $hotelId,
                ]);
                if ($this->password && strlen($this->password) >= 8) {
                    $existingUser->update(['password' => Hash::make($this->password)]);
                }
                $existingUser->departments()->sync($this->selectedDepartments ?? []);
                $canAssign = $currentUser->isSuperAdmin() || $currentUser->isEffectiveDirector() || $currentUser->isEffectiveGeneralManager();
                if ($canAssign) {
                    $existingUser->modules()->sync($this->sanitizedSelectedModuleIds());
                    $existingUser->permissions()->sync($this->sanitizedSelectedPermissionIds());
                }
                session()->flash('message', 'This user was already in the system but not assigned to your hotel. They have been assigned to your hotel and updated. You can now edit them to add or change permissions.');
                $this->resetForm();
                $this->loadUsers();
                return;
            }
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
        if ($superAdminRoleId && (int) $this->role_id === (int) $superAdminRoleId && !Auth::user()->isEffectiveSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can assign the Super Admin role. Super Admin users are created via seeder.');
            return;
        }

        $primaryDept = is_array($this->selectedDepartments) && count($this->selectedDepartments) > 0
            ? $this->selectedDepartments[0]
            : null;
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'department_id' => $primaryDept,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingUserId) {
            $user = User::findOrFail($this->editingUserId);
            $user->update($data);

            $user->departments()->sync($this->selectedDepartments ?? []);

            $canAssignPermissions = Auth::user()->isSuperAdmin()
                || Auth::user()->isEffectiveDirector()
                || Auth::user()->isEffectiveGeneralManager();

            if ($canAssignPermissions) {
                $user->modules()->sync($this->sanitizedSelectedModuleIds());
                $user->permissions()->sync($this->sanitizedSelectedPermissionIds());
                if (Auth::user()->isSuperAdmin()) {
                    UserPermissionRequest::where('user_id', $user->id)
                        ->where('status', UserPermissionRequest::STATUS_PENDING)
                        ->whereIn('permission_id', $this->sanitizedSelectedPermissionIds())
                        ->update([
                            'status' => UserPermissionRequest::STATUS_APPROVED,
                            'approved_by_id' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                }
            }

            session()->flash('message', 'User updated successfully!');
        } else {
            // Assign new user to a hotel so they appear in the list and are scoped correctly.
            $currentUser = Auth::user();
            $hotelId = null;
            if (!$currentUser->isSuperAdmin()) {
                $hotelId = $currentUser->hotel_id ?? \App\Models\Hotel::getHotel()?->id;
            } else {
                // Super Admin: still create under the current hotel context when one is selected
                $hotelId = \App\Models\Hotel::getHotel()?->id;
            }
            if ($hotelId) {
                $data['hotel_id'] = $hotelId;
            }
            $user = User::create($data);
            $user->departments()->sync($this->selectedDepartments ?? []);

            $canAssignPermissions = Auth::user()->isSuperAdmin()
                || Auth::user()->isEffectiveDirector()
                || Auth::user()->isEffectiveGeneralManager();

            if ($canAssignPermissions) {
                $user->modules()->sync($this->sanitizedSelectedModuleIds());
                $user->permissions()->sync($this->sanitizedSelectedPermissionIds());
            }

            session()->flash('message', 'User created successfully!');
        }

        $this->resetForm();
        $this->loadUsers();
    }

    public function delete($userId)
    {
        if ($userId == Auth::id()) {
            session()->flash('error', 'You cannot delete your own account!');
            return;
        }

        $user = User::with('role')->findOrFail($userId);
        if (Auth::user()->isManager() && !Auth::user()->isSuperAdmin() && $user->role && $user->role->slug === 'super-admin') {
            session()->flash('error', 'You cannot delete Super Admin users.');
            return;
        }

        $user->delete();
        session()->flash('message', 'User deleted successfully!');
        $this->loadUsers();
    }

    public function verifyEmail($userId)
    {
        $user = User::with('role')->findOrFail($userId);
        if (Auth::user()->isManager() && !Auth::user()->isSuperAdmin() && $user->role && $user->role->slug === 'super-admin') {
            session()->flash('error', 'You cannot modify Super Admin users.');
            return;
        }
        User::where('id', $userId)->update(['email_verified' => true]);
        session()->flash('message', 'Email verified successfully!');
        $this->loadUsers();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role_id = '';
        $this->department_id = '';
        $this->selectedDepartments = [];
        $this->is_active = true;
        $this->email_verified = false;
        $this->selectedModules = [];
        $this->selectedPermissions = [];
        $this->showForm = false;
        $this->editingUserId = null;
    }

    public function viewModulesPermissions($userId)
    {
        $user = User::with('role')->findOrFail($userId);
        if (Auth::user()->isManager() && !Auth::user()->isSuperAdmin() && $user->role && $user->role->slug === 'super-admin') {
            abort(403, 'You cannot view Super Admin users.');
        }
        $this->viewingUser = User::with(['role.permissions', 'departments', 'modules', 'permissions'])
            ->find($userId);
        $this->showModulesPermissionsModal = true;
    }

    public function closeModulesPermissionsModal()
    {
        $this->showModulesPermissionsModal = false;
        $this->viewingUser = null;
    }

    public function closeAndEditUser($userId)
    {
        $this->showModulesPermissionsModal = false;
        $this->viewingUser = null;
        $this->edit($userId);
    }

    public function render()
    {
        return view('livewire.users-crud')->layout('livewire.layouts.app-layout');
    }
}
