<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ResetPassword extends Component
{
    public $email;
    public $token;
    public $password;
    public $password_confirmation;
    public $message = '';
    public $error = '';

    public function mount($token, $email = null)
    {
        $this->token = $token;
        $this->email = $email ?? request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $this->message = 'Your password has been reset successfully. You can now login.';
            $this->error = '';
            return redirect()->route('login')->with('success', 'Password reset successfully. Please login.');
        } else {
            $this->error = 'Invalid or expired reset token. Please request a new password reset link.';
            $this->message = '';
        }
    }

    public function render()
    {
        return view('livewire.auth.reset-password')->layout('layouts.guest');
    }
}
