<?php

namespace App\Livewire\Auth;

use App\Models\Hotel;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $selectedModule = '';
    public $modules = [];
    public $error = '';
    public $hotelName = '';
    public $loginBackgroundUrl = '';
    public $iremeLogoUrl = '';
    public $loginRightImageUrl = '';

    public function mount()
    {
        // If user is already logged in, redirect to appropriate dashboard
        if (Auth::check()) {
            return auth()->user()->isIremeUser()
                ? redirect()->route('ireme.dashboard')
                : redirect()->route('dashboard');
        }

        $hotel = Hotel::getHotel();
        $this->hotelName = $hotel ? $hotel->name : config('app.name', 'Ireme Hotel Management');

        // Hotel-specific login background, or platform-level background as fallback
        $hotelBackground = $hotel && $hotel->getLoginBackgroundImageUrl()
            ? Storage::url($hotel->login_background_image)
            : '';
        $platformBackground = null;
        try {
            $platformBackground = PlatformSetting::getLoginBackgroundUrl();
        } catch (\Throwable $e) {
            // Table may not exist yet
        }
        $this->loginBackgroundUrl = $hotelBackground ?: ($platformBackground ?: '');

        $this->iremeLogoUrl = null;
        try {
            $this->iremeLogoUrl = PlatformSetting::getIremeLogoUrl();
        } catch (\Throwable $e) {
            // Table may not exist yet
        }
        $this->iremeLogoUrl = $this->iremeLogoUrl ?: asset('admintemplates/img/ireme-logo.png');
        $this->loginRightImageUrl = $this->loginBackgroundUrl ?: '';
    }

    public function updatedEmail()
    {
        $this->loadUserModules();
    }

    public function loadUserModules()
    {
        $this->modules = [];
        $this->selectedModule = '';
        $this->error = '';

        if (empty($this->email)) {
            return;
        }

        $user = User::where('email', $this->email)
            ->where('is_active', true)
            ->with(['role', 'modules'])
            ->first();

        if ($user) {
            $this->modules = $user->getAccessibleModules();
        }
    }

    public function login()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        $user = User::where('email', $this->email)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            $this->error = 'Invalid credentials.';
            return;
        }

        if (!Hash::check($this->password, $user->password)) {
            $this->error = 'Invalid credentials.';
            return;
        }

        // Ireme (platform) users: go to Ireme dashboard
        if ($user->isIremeUser()) {
            Auth::login($user);
            return redirect()->route('ireme.dashboard');
        }

        // Hotel users: log in and go to hotel dashboard (no module selection)
        Auth::login($user);
        session(['current_hotel_id' => $user->hotel_id]);
        if ($user->department_id) {
            session(['selected_department_id' => $user->department_id]);
        }
        // Set first accessible module as context for sidebar if any; otherwise leave unset
        $accessibleModules = $user->getAccessibleModules();
        if ($accessibleModules->isNotEmpty()) {
            session(['selected_module' => $accessibleModules->first()->slug]);
        } else {
            session()->forget('selected_module');
        }

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
