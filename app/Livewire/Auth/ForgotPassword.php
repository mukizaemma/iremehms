<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public $email;
    public $message = '';
    public $error = '';

    public function sendResetLink()
    {
        $this->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'We could not find a user with that email address.',
        ]);

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->message = 'Password reset link has been sent to your email address.';
            $this->error = '';
            $this->email = '';
        } else {
            $this->error = 'Unable to send password reset link. Please try again later.';
            $this->message = '';
        }
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')->layout('layouts.guest');
    }
}
