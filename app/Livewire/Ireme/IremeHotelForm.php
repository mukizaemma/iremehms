<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class IremeHotelForm extends Component
{
    use WithFileUploads;

    public $hotel = null;
    public $logo;
    public $logo_preview = '';
    public $hotel_code = '';
    public $name = '';
    public $email = '';
    public $contact = '';
    public $address = '';
    public $subscription_type = 'monthly';
    public $subscription_status = 'active';
    public $subscription_amount = '';
    public $subscription_start_date = '';
    public $next_due_date = '';
    public $module_ids = [];
    public $create_admin = true;
    public $admin_name = '';
    public $admin_email = '';
    public $admin_password = '';
    public $admin_role = 'hotel-admin';

    protected $subscriptionTypes = ['monthly', 'one_time', 'freemium'];
    protected $subscriptionStatuses = ['active', 'past_due', 'cancelled', 'suspended'];

    public function mount($hotel = null)
    {
        if ($hotel) {
            $this->hotel = $hotel instanceof Hotel ? $hotel : Hotel::find($hotel);
            if (!$this->hotel) {
                session()->flash('error', 'Hotel not found.');
                return $this->redirect(route('ireme.hotels.index'));
            }
            $this->name = $this->hotel->name;
            $this->email = $this->hotel->email ?? '';
            $this->contact = $this->hotel->contact ?? '';
            $this->address = $this->hotel->address ?? '';
            $this->hotel_code = (string) ($this->hotel->hotel_code ?? '');
            $this->subscription_type = $this->hotel->subscription_type ?? 'monthly';
            $this->subscription_status = $this->hotel->subscription_status ?? 'active';
            $this->subscription_amount = $this->hotel->subscription_amount !== null ? (string) $this->hotel->subscription_amount : '';
            $this->subscription_start_date = $this->hotel->subscription_start_date?->format('Y-m-d') ?? '';
            $this->next_due_date = $this->hotel->next_due_date?->format('Y-m-d') ?? '';
            $this->module_ids = $this->hotel->modules()->pluck('modules.id')->all();
            $this->logo_preview = $this->hotel->logo ? Storage::url($this->hotel->logo) : '';
        } else {
            $this->hotel_code = (string) Hotel::generateHotelCode();
        }
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => 'nullable|image|max:2048']);
        if ($this->logo) {
            $this->logo_preview = $this->logo->temporaryUrl();
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|min:2',
            'hotel_code' => 'required|integer|min:100|max:999|unique:hotels,hotel_code,' . ($this->hotel ? $this->hotel->id : ''),
            'subscription_type' => 'required|in:' . implode(',', $this->subscriptionTypes),
            'subscription_status' => 'required|in:' . implode(',', $this->subscriptionStatuses),
            'subscription_amount' => 'nullable|numeric|min:0',
            'subscription_start_date' => 'nullable|date',
            'next_due_date' => 'nullable|date',
            'logo' => 'nullable|image|max:2048',
        ]);

        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        if ($this->hotel) {
            $this->hotel->update([
                'name' => $this->name,
                'email' => $this->email ?: null,
                'contact' => $this->contact ?: null,
                'address' => $this->address ?: null,
                'hotel_code' => (int) $this->hotel_code,
                'subscription_type' => $this->subscription_type,
                'subscription_status' => $this->subscription_status,
                'subscription_amount' => $this->subscription_amount !== '' ? (float) $this->subscription_amount : null,
                'subscription_start_date' => $this->subscription_start_date ?: null,
                'next_due_date' => $this->next_due_date ?: null,
            ]);
            if ($this->logo) {
                $path = $this->logo->store('logos', 'public');
                $this->hotel->update(['logo' => $path]);
            }
            if ($isSuperAdmin) {
                $ids = array_values(array_map('intval', $this->module_ids));
                $this->hotel->modules()->sync($ids);
                $this->hotel->update(['enabled_modules' => $ids]);
            }
            session()->flash('message', 'Hotel updated.');
        } else {
            if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
                session()->flash('error', 'Only Ireme Super Admin can onboard new hotels (create admin and assign modules).');
                return $this->redirect(route('ireme.hotels.index'));
            }
            $this->validate([
                'admin_name' => 'required_if:create_admin,true|string|min:2',
                'admin_email' => 'required_if:create_admin,true|email|unique:users,email',
                'admin_password' => 'required_if:create_admin,true|string|min:8',
            ], [], [
                'admin_name' => 'Admin name',
                'admin_email' => 'Admin email',
                'admin_password' => 'Admin password',
            ]);

            $hotel = Hotel::create([
                'name' => $this->name,
                'email' => $this->email ?: null,
                'contact' => $this->contact ?: null,
                'address' => $this->address ?: null,
                'hotel_code' => (int) $this->hotel_code,
                'subscription_type' => $this->subscription_type,
                'subscription_status' => $this->subscription_status,
                'subscription_amount' => $this->subscription_amount !== '' ? (float) $this->subscription_amount : null,
                'subscription_start_date' => $this->subscription_start_date ?: null,
                'next_due_date' => $this->next_due_date ?: $this->computeInitialNextDueDate(),
                'currency' => 'RWF',
                'timezone' => 'Africa/Kigali',
            ]);
            if ($this->logo) {
                $path = $this->logo->store('logos', 'public');
                $hotel->update(['logo' => $path]);
            }
            $ids = array_values(array_map('intval', $this->module_ids));
            $hotel->modules()->sync($ids);
            $hotel->update(['enabled_modules' => $ids]);

            if ($this->create_admin && $this->admin_email) {
                $role = Role::where('slug', $this->admin_role)->first();
                User::create([
                    'name' => $this->admin_name,
                    'email' => $this->admin_email,
                    'password' => Hash::make($this->admin_password),
                    'hotel_id' => $hotel->id,
                    'role_id' => $role ? $role->id : null,
                    'is_active' => true,
                ]);
            }
            session()->flash('message', 'Hotel onboarded.');
        }
        return $this->redirect(route('ireme.hotels.index'));
    }

    protected function computeInitialNextDueDate(): ?string
    {
        $start = $this->subscription_start_date ? \Carbon\Carbon::parse($this->subscription_start_date) : now();
        if ($this->subscription_type === 'monthly') {
            return $start->copy()->addMonth()->format('Y-m-d');
        }
        if ($this->subscription_type === 'one_time') {
            return $start->format('Y-m-d');
        }
        return $start->format('Y-m-d');
    }

    public function render()
    {
        $modules = Module::where('is_active', true)->orderBy('order')->get();
        $hotelRoles = Role::whereIn('slug', ['hotel-admin', 'director', 'general-manager'])->orderBy('name')->get();
        $canEditModules = auth()->user() && auth()->user()->isSuperAdmin();
        return view('livewire.ireme.ireme-hotel-form', [
            'modules' => $modules,
            'hotelRoles' => $hotelRoles,
            'subscriptionTypes' => $this->subscriptionTypes,
            'subscriptionStatuses' => $this->subscriptionStatuses,
            'canEditModules' => $canEditModules,
        ])->layout('livewire.layouts.ireme-layout', ['title' => $this->hotel ? 'Edit Hotel' : 'Onboard Hotel']);
    }
}
