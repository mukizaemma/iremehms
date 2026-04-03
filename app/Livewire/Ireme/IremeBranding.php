<?php

namespace App\Livewire\Ireme;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class IremeBranding extends Component
{
    use WithFileUploads;

    public $ireme_logo;
    public $ireme_logo_preview = '';
    public $login_background;
    public $login_background_preview = '';

    public function mount(): void
    {
        $path = PlatformSetting::get('ireme_logo');
        $this->ireme_logo_preview = $path ? Storage::url($path) : '';

        $bgPath = PlatformSetting::get('login_background');
        $this->login_background_preview = $bgPath ? Storage::url($bgPath) : '';
    }

    public function updatedIreme_logo(): void
    {
        $this->validate(['ireme_logo' => 'image|max:2048']);
        $this->ireme_logo_preview = $this->ireme_logo->temporaryUrl();
    }

    public function updatedLogin_background(): void
    {
        $this->validate(['login_background' => 'image|max:4096']);
        $this->login_background_preview = $this->login_background->temporaryUrl();
    }

    public function save(): void
    {
        $this->validate(['ireme_logo' => 'nullable|image|max:2048']);

        if ($this->ireme_logo) {
            $path = $this->ireme_logo->store('platform', 'public');
            PlatformSetting::set('ireme_logo', $path);
            $this->ireme_logo_preview = Storage::url($path);
            $this->ireme_logo = null;
        }
        session()->flash('message', 'Ireme logo saved.');
    }

    public function saveLoginBackground(): void
    {
        $this->validate(['login_background' => 'nullable|image|max:4096']);

        if ($this->login_background) {
            $path = $this->login_background->store('platform', 'public');
            PlatformSetting::set('login_background', $path);
            $this->login_background_preview = Storage::url($path);
            $this->login_background = null;
        }
        session()->flash('message', 'Login background image saved.');
    }

    public function removeIremeLogo(): void
    {
        $path = PlatformSetting::get('ireme_logo');
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        PlatformSetting::set('ireme_logo', '');
        $this->ireme_logo_preview = '';
        $this->ireme_logo = null;
        session()->flash('message', 'Ireme logo removed.');
    }

    public function removeLoginBackground(): void
    {
        $path = PlatformSetting::get('login_background');
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        PlatformSetting::set('login_background', '');
        $this->login_background_preview = '';
        $this->login_background = null;
        session()->flash('message', 'Login background image removed.');
    }

    public function render()
    {
        return view('livewire.ireme.ireme-branding')
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Branding']);
    }
}
