<?php

namespace App\Livewire\Ireme;

use App\Models\PlatformSetting;
use Livewire\Component;

class IremeAccount extends Component
{
    public $ireme_company_name = '';
    public $ireme_phone = '';
    public $ireme_email = '';
    public $ireme_tin = '';
    public $ireme_bank_account = '';
    public $ireme_momo_code = '';
    public $ireme_invoice_description = '';
    public $ireme_invoice_thank_you = '';

    public function mount(): void
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Ireme Super Admin can access this page.');
        }
        $this->ireme_company_name = PlatformSetting::get('ireme_company_name', 'Ireme HMS');
        $this->ireme_phone = PlatformSetting::get('ireme_phone', '');
        $this->ireme_email = PlatformSetting::get('ireme_email', '');
        $this->ireme_tin = PlatformSetting::get('ireme_tin', '');
        $this->ireme_bank_account = PlatformSetting::get('ireme_bank_account', '');
        $this->ireme_momo_code = PlatformSetting::get('ireme_momo_code', '');
        $this->ireme_invoice_description = PlatformSetting::get('ireme_invoice_description', 'Hotel management system subscription.');
        $this->ireme_invoice_thank_you = PlatformSetting::get('ireme_invoice_thank_you', 'Thank you for your business.');
    }

    public function save(): void
    {
        $this->validate([
            'ireme_company_name' => 'required|string|min:1|max:255',
            'ireme_phone' => 'nullable|string|max:100',
            'ireme_email' => 'nullable|email|max:255',
            'ireme_tin' => 'nullable|string|max:100',
            'ireme_bank_account' => 'nullable|string|max:255',
            'ireme_momo_code' => 'nullable|string|max:100',
            'ireme_invoice_description' => 'nullable|string|max:500',
            'ireme_invoice_thank_you' => 'nullable|string|max:500',
        ]);

        PlatformSetting::set('ireme_company_name', $this->ireme_company_name);
        PlatformSetting::set('ireme_phone', $this->ireme_phone);
        PlatformSetting::set('ireme_email', $this->ireme_email);
        PlatformSetting::set('ireme_tin', $this->ireme_tin);
        PlatformSetting::set('ireme_bank_account', $this->ireme_bank_account);
        PlatformSetting::set('ireme_momo_code', $this->ireme_momo_code);
        PlatformSetting::set('ireme_invoice_description', $this->ireme_invoice_description);
        PlatformSetting::set('ireme_invoice_thank_you', $this->ireme_invoice_thank_you);

        session()->flash('message', 'Ireme account and invoice settings saved.');
    }

    public function render()
    {
        return view('livewire.ireme.ireme-account')
            ->layout('livewire.layouts.ireme-layout', ['title' => 'Ireme account']);
    }
}
