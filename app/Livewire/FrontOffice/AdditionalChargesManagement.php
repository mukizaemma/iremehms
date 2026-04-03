<?php

namespace App\Livewire\FrontOffice;

use App\Models\AdditionalCharge;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdditionalChargesManagement extends Component
{
    public $charges = [];
    public $showForm = false;
    public $editingId = null;

    public $name = '';
    public $type = 'service';
    public $code = '';
    public $description = '';
    public $default_amount = '';
    public $charge_rule = 'per_instance';
    public $is_tax_inclusive = false;
    public $is_active = true;

    public $search = '';

    public function mount()
    {
        session(['selected_module' => 'front-office']);
        $this->loadCharges();
    }

    /** Front office can view; only Manager, Director, GM and Super Admin can create/update/delete. */
    public function canManageCharges(): bool
    {
        return Auth::user()->isSuperAdmin() || Auth::user()->canNavigateModules();
    }

    protected function authorizeManageCharges(): void
    {
        if (! $this->canManageCharges()) {
            abort(403, 'Unauthorized. Only Manager and Super Admin can create, update, or delete charges.');
        }
    }

    public function loadCharges()
    {
        $hotel = Hotel::getHotel();
        $query = AdditionalCharge::where('hotel_id', $hotel->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $this->charges = $query->orderBy('name')->get()->toArray();
    }

    public function openForm($id = null)
    {
        $this->authorizeManageCharges();
        $this->editingId = $id;
        if ($id) {
            $c = AdditionalCharge::findOrFail($id);
            $this->name = $c->name;
            $this->type = $c->type ?? AdditionalCharge::TYPE_SERVICE;
            $this->code = $c->code ?? '';
            $this->description = $c->description ?? '';
            $this->default_amount = $c->default_amount !== null ? (string) $c->default_amount : '';
            $this->charge_rule = $c->charge_rule ?? 'per_instance';
            $this->is_tax_inclusive = $c->is_tax_inclusive;
            $this->is_active = $c->is_active;
        } else {
            $this->resetForm();
        }
        $this->showForm = true;
    }

    public function closeForm()
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = AdditionalCharge::TYPE_SERVICE;
        $this->code = '';
        $this->description = '';
        $this->default_amount = '';
        $this->charge_rule = 'per_instance';
        $this->is_tax_inclusive = false;
        $this->is_active = true;
    }

    public function save()
    {
        $this->authorizeManageCharges();
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:service,equipment,late-checkout,extra_bed,extra_person',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_amount' => 'nullable|numeric|min:0',
            'charge_rule' => 'required|in:per_instance,per_night,per_person,per_adult,per_child,per_booking,per_quantity',
            'is_tax_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $hotel = Hotel::getHotel();
        $data = [
            'hotel_id' => $hotel->id,
            'name' => $this->name,
            'type' => $this->type,
            'code' => $this->code ?: null,
            'description' => $this->description ?: null,
            'default_amount' => $this->default_amount !== '' ? $this->default_amount : null,
            'charge_rule' => $this->charge_rule,
            'is_tax_inclusive' => $this->is_tax_inclusive,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            AdditionalCharge::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Additional charge updated successfully.');
        } else {
            AdditionalCharge::create($data);
            session()->flash('message', 'Additional charge created successfully.');
        }

        $this->loadCharges();
        $this->closeForm();
    }

    public function deleteCharge($id)
    {
        $this->authorizeManageCharges();
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete additional charges. You can deactivate instead.');
            return;
        }
        AdditionalCharge::findOrFail($id)->delete();
        session()->flash('message', 'Additional charge deleted.');
        $this->loadCharges();
    }

    public function toggleActive($id)
    {
        $this->authorizeManageCharges();
        $c = AdditionalCharge::findOrFail($id);
        $c->is_active = ! $c->is_active;
        $c->save();
        $this->loadCharges();
    }

    public function updatedSearch()
    {
        $this->loadCharges();
    }

    public function getChargeRules(): array
    {
        return AdditionalCharge::CHARGE_RULES;
    }

    public function render()
    {
        return view('livewire.front-office.additional-charges-management', [
            'chargeRules' => $this->getChargeRules(),
            'chargeTypes' => AdditionalCharge::TYPES,
        ])->layout('livewire.layouts.app-layout');
    }
}
