<?php

namespace App\Livewire\Layouts;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AppLayout extends Component
{
    public $selectedDepartment = null;
    public $selectedUserContext = null;
    public $availableDepartments = [];
    public $availableUsers = [];
    public $user;
    public $modules = [];
    public $selectedModule = '';

    public function mount()
    {
        $this->user = Auth::user();
        
        // Load available departments for switching
        if ($this->user->isSuperAdmin() || $this->user->isManager()) {
            $this->availableDepartments = Department::where('is_active', true)->get();
            $this->selectedDepartment = session('selected_department_id', $this->user->department_id);
        } else {
            $this->selectedDepartment = $this->user->department_id;
        }

        // Load available users for context switching (super admin and managers) — current hotel only when in hotel context
        if ($this->user->isSuperAdmin() || $this->user->isManager()) {
            $userQuery = User::where('is_active', true)->with(['role', 'department']);
            $hotel = Hotel::getHotel();
            if ($hotel) {
                $userQuery->where('hotel_id', $hotel->id);
            }
            $this->availableUsers = $userQuery->orderBy('name')->get();
            $this->selectedUserContext = session('selected_user_context_id', $this->user->id);
        } else {
            $this->selectedUserContext = $this->user->id;
        }
        
        $this->loadUserData();
    }
    
    public function loadUserData()
    {
        $this->user = Auth::user();
        $this->modules = $this->getFilteredModules();
        $this->selectedModule = session('selected_module', '');
    }
    
    public function getFilteredModules()
    {
        if (!$this->user) {
            return collect();
        }
        
        // Use the User model's getAccessibleModules which handles role-based access correctly
        // Super Admin: ALL modules
        // Manager: Hotel-enabled modules only
        // Others: Role-based + hotel-enabled modules
        return $this->user->getAccessibleModules();
    }
    
    public function switchModule($moduleSlug)
    {
        $module = Module::where('slug', $moduleSlug)->first();
        
        if ($module && $this->user && $this->user->hasModuleAccess($module->id)) {
            session(['selected_module' => $moduleSlug]);
            $this->selectedModule = $moduleSlug;
            
            // Redirect to module page
            return redirect()->route('module.show', $moduleSlug);
        }
    }

    public function switchDepartment($departmentId)
    {
        if (Auth::user()->isSuperAdmin() || Auth::user()->isManager()) {
            session(['selected_department_id' => $departmentId]);
            $this->selectedDepartment = $departmentId;
            $this->dispatch('department-switched', departmentId: $departmentId);
        }
    }

    public function switchUserContext($userId)
    {
        if (Auth::user()->isSuperAdmin() || Auth::user()->isManager()) {
            session(['selected_user_context_id' => $userId]);
            $this->selectedUserContext = $userId;
            $this->dispatch('user-context-switched', userId: $userId);
        }
    }

    // Logout is handled via form POST to /logout route

    protected $listeners = ['profile-updated' => 'refreshUser'];
    
    public function refreshUser()
    {
        // Reload user data from database
        $this->user = Auth::user()->fresh();
        $this->loadUserData();
    }

    public function render()
    {
        // Ensure user data is loaded
        if (!$this->user) {
            $this->loadUserData();
        }
        
        // Always get fresh user data to ensure profile image is up to date
        $this->user = Auth::user()->fresh();
        
        return view('livewire.layouts.app-layout');
    }
}
