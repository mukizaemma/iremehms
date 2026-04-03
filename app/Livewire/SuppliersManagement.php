<?php

namespace App\Livewire;

use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SuppliersManagement extends Component
{
    use WithPagination, ChecksModuleStatus;

    public $suppliers = [];
    public $showSupplierForm = false;
    public $editingSupplierId = null;
    
    // Form fields
    public $name = '';
    public $contact_person = '';
    public $phone = '';
    public $email = '';
    public $default_currency = '';
    public $is_active = true;
    
    // Filters
    public $search = '';
    public $filter_active = '';

    public function mount()
    {
        $user = Auth::user();
        $storeModule = \App\Models\Module::where('slug', 'store')->first();
        $canAccess = $user->isSuperAdmin()
            || $user->isManager()
            || ($storeModule && $user->hasModuleAccess($storeModule->id));

        if (!$canAccess) {
            abort(403, 'Unauthorized. Only Manager, Store Keeper (view), or Super Admin can access suppliers.');
        }

        $this->setDefaultCurrency();
        $this->loadSuppliers();
    }

    /**
     * Only Manager and Super Admin can create, update, delete suppliers and make invoices.
     */
    public function canManageSuppliers(): bool
    {
        $user = Auth::user();
        return $user->isSuperAdmin() || $user->isManager();
    }

    protected function setDefaultCurrency(): void
    {
        $this->default_currency = \App\Models\Hotel::getHotel()->getCurrency();
    }

    public function loadSuppliers()
    {
        $query = Supplier::query();
        
        // Filter by active status
        if ($this->filter_active !== '') {
            $query->where('is_active', $this->filter_active === '1');
        }
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_person', 'like', '%' . $this->search . '%')
                  ->orWhere('phone', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }
        
        $this->suppliers = $query->orderBy('name')->get()->toArray();
    }

    public function openSupplierForm($supplierId = null)
    {
        if (!$this->canManageSuppliers()) {
            return;
        }
        $this->editingSupplierId = $supplierId;
        
        if ($supplierId) {
            $supplier = Supplier::find($supplierId);
            $this->name = $supplier->name;
            $this->contact_person = $supplier->contact_person ?? '';
            $this->phone = $supplier->phone ?? '';
            $this->email = $supplier->email ?? '';
            $this->default_currency = \App\Helpers\CurrencyHelper::getCurrency();
            $this->is_active = $supplier->is_active;
        } else {
            $this->resetForm();
        }
        
        $this->showSupplierForm = true;
    }

    public function closeSupplierForm()
    {
        $this->showSupplierForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingSupplierId = null;
        $this->name = '';
        $this->contact_person = '';
        $this->phone = '';
        $this->email = '';
        $this->default_currency = \App\Models\Hotel::getHotel()->getCurrency();
        $this->is_active = true;
    }

    public function saveSupplier()
    {
        if (!$this->canManageSuppliers()) {
            return;
        }
        $this->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'default_currency' => 'required|string|size:3',
            'is_active' => 'boolean',
        ]);

        $systemCurrency = \App\Helpers\CurrencyHelper::getCurrency();
        if ($this->editingSupplierId) {
            $supplier = Supplier::find($this->editingSupplierId);
            $supplier->update([
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'default_currency' => $systemCurrency,
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'Supplier updated successfully!');
        } else {
            Supplier::create([
                'name' => $this->name,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'default_currency' => $systemCurrency,
                'is_active' => $this->is_active,
            ]);
            session()->flash('message', 'Supplier created successfully!');
        }

        $this->closeSupplierForm();
        $this->loadSuppliers();
    }

    public function deleteSupplier($supplierId)
    {
        if (! $this->canManageSuppliers()) {
            return;
        }
        if (! \Illuminate\Support\Facades\Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete suppliers. You can deactivate instead.');
            return;
        }
        $supplier = Supplier::find($supplierId);
        
        // Check if supplier has associated records
        $hasRequisitions = $supplier->purchaseRequisitions()->exists();
        $hasReceipts = $supplier->goodsReceipts()->exists();
        $hasExpenses = $supplier->expenses()->exists();
        
        if ($hasRequisitions || $hasReceipts || $hasExpenses) {
            session()->flash('error', 'Cannot delete supplier: Has associated purchase requisitions, goods receipts, or expenses. Deactivate instead.');
            return;
        }

        $supplier->delete();
        session()->flash('message', 'Supplier deleted successfully!');
        $this->loadSuppliers();
    }

    public function toggleActive($supplierId)
    {
        if (!$this->canManageSuppliers()) {
            return;
        }
        $supplier = Supplier::find($supplierId);
        $supplier->update(['is_active' => !$supplier->is_active]);
        session()->flash('message', 'Supplier status updated successfully!');
        $this->loadSuppliers();
    }

    public function updatedSearch()
    {
        $this->loadSuppliers();
    }

    public function updatedFilterActive()
    {
        $this->loadSuppliers();
    }

    /**
     * Create a supplier invoice (expense) for an unpaid delivered goods receipt. Manager only.
     */
    public function createInvoiceForReceipt($receiptId)
    {
        if (!$this->canManageSuppliers()) {
            return;
        }
        $receipt = GoodsReceipt::with(['supplier', 'items', 'department'])->find($receiptId);
        if (!$receipt || !$receipt->supplier_id) {
            session()->flash('error', 'Receipt or supplier not found.');
            return;
        }
        $total = $receipt->items->sum('total_cost');
        if ($total <= 0) {
            session()->flash('error', 'Receipt has no cost to invoice.');
            return;
        }
        $hotel = \App\Models\Hotel::getHotel();
        $expense = \App\Models\Expense::create([
            'title' => 'Supplier invoice – Receipt #' . $receipt->receipt_id . ' (' . ($receipt->supplier->name ?? '') . ')',
            'description' => 'Invoice for delivered goods receipt #' . $receipt->receipt_id,
            'amount' => $total,
            'currency' => $hotel->getCurrency(),
            'supplier_id' => $receipt->supplier_id,
            'department_id' => $receipt->department_id,
            'created_by' => Auth::id(),
            'expense_date' => $receipt->business_date ?? now(),
            'business_date' => $receipt->business_date ?? now()->toDateString(),
            'shift_id' => $receipt->shift_id,
        ]);
        session()->flash('message', 'Invoice created for receipt #' . $receipt->receipt_id . '. Expense #' . $expense->id . ' recorded.');
        $this->loadSuppliers();
    }

    public function render()
    {
        $unpaidDelivered = collect();
        if ($this->canManageSuppliers()) {
            $unpaidDelivered = GoodsReceipt::with(['supplier', 'items'])
                ->where('receipt_status', 'COMPLETE')
                ->orderByDesc('receipt_id')
                ->limit(50)
                ->get();
        }

        return view('livewire.suppliers-management', [
            'canManageSuppliers' => $this->canManageSuppliers(),
            'unpaidDeliveredReceipts' => $unpaidDelivered,
        ])->layout('livewire.layouts.app-layout');
    }
}
