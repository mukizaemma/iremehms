<?php

namespace App\Livewire;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * System configuration – Super Admin only. POS & Stock, Receipt, Currency, Departments, Feature Control.
 */
class SystemConfiguration extends Component
{
    public $enabledModules = [];
    public $enabledDepartments = [];
    public $availableModules = [];
    public $availableDepartments = [];
    public $posEnforceStockOnPayment = true;
    public $receiptShowVat = false;

    public bool $reportsShowVat = false;
    public $receiptThankYouText = '';
    public $receiptMomoLabel = '';
    public $receiptMomoValue = '';
    public $currency = 'RWF';
    public $useBomForMenuItems = true;

    public function mount(): void
    {
        if (! Auth::user()->isEffectiveSuperAdmin()) {
            abort(403, 'Only Super Admin can access System configuration.');
        }

        $hotel = Hotel::getHotel();
        $this->availableModules = Module::where('is_active', true)->get();
        $this->availableDepartments = Department::all();
        $this->enabledModules = $hotel->getEnabledModuleIds();
        $this->enabledDepartments = $hotel->enabled_departments ?? [];
        $this->posEnforceStockOnPayment = (bool) ($hotel->pos_enforce_stock_on_payment ?? true);
        $this->receiptShowVat = (bool) ($hotel->receipt_show_vat ?? false);
        $this->reportsShowVat = Schema::hasColumn('hotels', 'reports_show_vat')
            ? (bool) ($hotel->reports_show_vat ?? false)
            : false;
        $this->receiptThankYouText = $hotel->receipt_thank_you_text ?? '';
        $this->receiptMomoLabel = $hotel->receipt_momo_label ?? '';
        $this->receiptMomoValue = $hotel->receipt_momo_value ?? '';
        $this->currency = $hotel->currency ?? 'RWF';
        $this->useBomForMenuItems = (bool) ($hotel->use_bom_for_menu_items ?? true);
    }

    public function save(): void
    {
        $this->validate([
            'currency' => 'required|string|size:3',
        ]);

        $payload = [
            'enabled_modules' => $this->enabledModules,
            'enabled_departments' => $this->enabledDepartments,
            'pos_enforce_stock_on_payment' => $this->posEnforceStockOnPayment,
            'receipt_show_vat' => $this->receiptShowVat,
            'receipt_thank_you_text' => $this->receiptThankYouText ?: null,
            'receipt_momo_label' => $this->receiptMomoLabel ?: null,
            'receipt_momo_value' => $this->receiptMomoValue ?: null,
            'currency' => $this->currency,
            'use_bom_for_menu_items' => $this->useBomForMenuItems,
        ];
        if (Schema::hasColumn('hotels', 'reports_show_vat')) {
            $payload['reports_show_vat'] = $this->reportsShowVat;
        }
        $hotel = Hotel::getHotel();
        $hotel->update($payload);

        // Keep hotel_module pivot aligned with enabled_modules so getEnabledModuleIds() stays consistent
        $moduleIds = array_values(array_unique(array_filter(array_map('intval', $this->enabledModules ?? []))));
        $hotel->modules()->sync($moduleIds);
        $hotel->unsetRelation('modules');

        session()->flash('message', 'System configuration saved successfully.');
    }

    public function render()
    {
        return view('livewire.system-configuration')->layout('livewire.layouts.app-layout');
    }
}
