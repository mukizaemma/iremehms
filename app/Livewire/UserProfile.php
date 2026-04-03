<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class UserProfile extends Component
{
    use WithFileUploads;

    public $name;
    public $email;
    public $profileImage;
    public $profileImagePreview;
    public $currentPassword;
    public $newPassword;
    public $newPasswordConfirmation;
    public $showPasswordForm = false;

    public function mount()
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            $this->profileImagePreview = Storage::url($user->profile_image);
        } else {
            $this->profileImagePreview = asset('admintemplates/img/user.jpg');
        }
    }

    public function updatedProfileImage()
    {
        $this->validate(['profileImage' => 'image|max:2048']);
        $this->profileImagePreview = $this->profileImage->temporaryUrl();
    }

    public function updateProfile()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'profileImage' => 'nullable|image|max:2048',
        ]);

        $user = Auth::user();
        $user->name = $this->name;
        $user->email = $this->email;

        // Handle profile image upload
        if ($this->profileImage) {
            // Delete old image if exists
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            
            $imagePath = $this->profileImage->store('profile-images', 'public');
            $user->profile_image = $imagePath;
            $this->profileImagePreview = Storage::url($imagePath);
        }

        $user->save();
        
        // Refresh user data
        Auth::user()->refresh();
        
        // Dispatch event to refresh layout
        $this->dispatch('profile-updated');

        session()->flash('message', 'Profile updated successfully!');
        $this->profileImage = null;
        
        // Reload the preview with the new image
        if ($user->profile_image) {
            $this->profileImagePreview = Storage::url($user->profile_image);
        }
    }

    public function togglePasswordForm()
    {
        $this->showPasswordForm = !$this->showPasswordForm;
        $this->resetPasswordForm();
    }

    public function resetPasswordForm()
    {
        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
    }

    public function updatePassword()
    {
        $this->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($this->currentPassword, $user->password)) {
            session()->flash('error', 'Current password is incorrect.');
            return;
        }

        // Update password
        $user->password = Hash::make($this->newPassword);
        $user->save();

        session()->flash('message', 'Password updated successfully!');
        $this->togglePasswordForm();
    }

    public function render()
    {
        return view('livewire.user-profile')->layout('livewire.layouts.app-layout');
    }
}
