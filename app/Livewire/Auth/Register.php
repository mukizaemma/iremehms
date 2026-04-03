<?php

namespace App\Livewire\Auth;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role_id = '';
    public $department_id = '';
    public $roles = [];
    public $departments = [];

    public function mount()
    {
        // If user is already logged in, redirect to dashboard
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Load roles and departments
        $this->roles = Role::whereNotIn('slug', ['super-admin', 'department-user'])->get();
        $this->departments = Department::where('is_active', true)->get();
    }

    public function register()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'department_id' => 'nullable|exists:departments,id',
        ], [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role_id.required' => 'Please select a role.',
            'role_id.exists' => 'Selected role is invalid.',
            'department_id.exists' => 'Selected department is invalid.',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role_id' => $this->role_id,
            'department_id' => $this->department_id,
            'is_active' => true,
        ]);

        Auth::login($user);

        return redirect()->route('login');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
