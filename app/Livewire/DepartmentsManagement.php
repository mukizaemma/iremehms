<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class DepartmentsManagement extends Component
{
    public $departments = [];
    public $selectedDepartment = null;
    public $departmentUsers = [];
    public $showUserForm = false;
    public $editingUserId = null;
    
    // User form fields
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role_id = '';
    public $department_id = '';
    public $is_active = true;
    public $email_verified = false;
    
    public $roles = [];
    public $search = '';

    public function mount()
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('hotel_configure_details') && !Auth::user()->hasPermission('hotel_manage_users')) {
            abort(403, 'Unauthorized access. Only Super Admin or users with Hotel configuration permission can access Departments.');
        }

        $this->loadDepartments();
        $this->roles = Role::whereNotIn('slug', ['super-admin', 'department-user'])->get();
    }

    public function loadDepartments()
    {
        $hotel = Hotel::getHotel();

        if (! $hotel) {
            $this->departments = collect();
            $this->selectedDepartment = null;
            $this->departmentUsers = [];

            return;
        }

        $deptIds = $hotel->getDepartmentIdsForAssignments();

        $query = Department::where('is_active', true);
        if ($deptIds !== []) {
            $query->whereIn('id', $deptIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        $this->departments = $query->orderBy('name')->get();

        $allowedIds = $this->departments->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($this->selectedDepartment && ! in_array((int) $this->selectedDepartment, $allowedIds, true)) {
            $this->selectedDepartment = null;
            $this->departmentUsers = [];
        }

        $hotelId = (int) $hotel->id;

        // Count users per department for this hotel only
        foreach ($this->departments as $d) {
            $d->users_count = User::query()
                ->where('hotel_id', $hotelId)
                ->where(function ($q) use ($d) {
                    $q->where('department_id', $d->id)
                        ->orWhereHas('departments', fn ($sub) => $sub->where('departments.id', $d->id));
                })
                ->count();
        }
    }

    public function selectDepartment($departmentId)
    {
        $this->selectedDepartment = $departmentId;
        $this->department_id = $departmentId;
        $this->loadDepartmentUsers();
        $this->showUserForm = false;
    }

    public function loadDepartmentUsers()
    {
        if (! $this->selectedDepartment) {
            $this->departmentUsers = [];

            return;
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            $this->departmentUsers = [];

            return;
        }

        $deptId = $this->selectedDepartment;
        $query = User::with(['role', 'departments'])
            ->where('hotel_id', $hotel->id)
            ->where(function ($q) use ($deptId) {
                $q->where('department_id', $deptId)
                    ->orWhereHas('departments', fn ($d) => $d->where('departments.id', $deptId));
            });

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        $this->departmentUsers = $query->orderBy('name')->get();
    }

    public function updatedSearch()
    {
        $this->loadDepartmentUsers();
    }

    public function createUser()
    {
        $this->resetUserForm();
        $this->showUserForm = true;
        $this->editingUserId = null;
    }

    public function editUser($userId)
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            session()->flash('error', 'Hotel context not found.');

            return;
        }

        $user = User::where('hotel_id', $hotel->id)->findOrFail($userId);
        
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role_id = $user->role_id;
        $this->department_id = $user->department_id;
        $this->is_active = $user->is_active;
        $this->email_verified = $user->email_verified;
        $this->password = '';
        $this->password_confirmation = '';
        
        $this->showUserForm = true;
    }

    public function saveUser()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editingUserId ? ',' . $this->editingUserId : ''),
            'role_id' => 'required|exists:roles,id',
            'department_id' => 'required|exists:departments,id',
            'is_active' => 'boolean',
            'email_verified' => 'boolean',
        ];

        if ($this->editingUserId) {
            if ($this->password) {
                $rules['password'] = 'required|string|min:8|confirmed';
            }
        } else {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            session()->flash('error', 'Hotel context not found.');

            return;
        }

        $allowedDeptIds = $this->departmentIdsForHotel($hotel);
        if (! in_array((int) $this->department_id, $allowedDeptIds, true)) {
            $this->addError('department_id', 'This department is not enabled for your hotel.');

            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $this->role_id,
            'department_id' => $this->department_id,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified,
            'hotel_id' => $hotel->id,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingUserId) {
            unset($data['hotel_id']);
            $user = User::where('hotel_id', $hotel->id)->findOrFail($this->editingUserId);
            $user->update($data);
            $user->departments()->syncWithoutDetaching([$this->department_id]);
            session()->flash('message', 'User updated successfully!');
        } else {
            $user = User::create($data);
            $user->departments()->syncWithoutDetaching([(int) $this->department_id]);
            session()->flash('message', 'User created successfully!');
        }

        $this->resetUserForm();
        $this->loadDepartmentUsers();
        $this->loadDepartments();
    }

    public function deleteUser($userId)
    {
        if ($userId == Auth::id()) {
            session()->flash('error', 'You cannot delete your own account!');
            return;
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            session()->flash('error', 'Hotel context not found.');

            return;
        }
        User::where('hotel_id', $hotel->id)->findOrFail($userId)->delete();
        session()->flash('message', 'User deleted successfully!');
        $this->loadDepartmentUsers();
        $this->loadDepartments();
    }

    public function verifyEmail($userId)
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            session()->flash('error', 'Hotel context not found.');

            return;
        }
        User::where('hotel_id', $hotel->id)->where('id', $userId)->update(['email_verified' => true]);
        session()->flash('message', 'Email verified successfully!');
        $this->loadDepartmentUsers();
    }

    public function resetUserForm()
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role_id = '';
        $this->department_id = $this->selectedDepartment;
        $this->is_active = true;
        $this->email_verified = false;
        $this->showUserForm = false;
        $this->editingUserId = null;
    }

    public function render()
    {
        return view('livewire.departments-management')->layout('livewire.layouts.app-layout');
    }
}
