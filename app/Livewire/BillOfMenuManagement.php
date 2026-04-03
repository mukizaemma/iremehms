<?php

namespace App\Livewire;

use App\Models\BillOfMenu;
use App\Models\BillOfMenuItem;
use App\Models\MenuItem;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BillOfMenuManagement extends Component
{
    public $menuItems = [];
    public $selectedMenuItemId = null;
    public $selectedMenuItem = null;
    public $billOfMenus = [];
    public $selectedBomId = null;
    public $selectedBom = null;
    public $bomItems = [];
    public $availableStocks = [];
    
    public $showBomForm = false;
    public $showBomItemForm = false;
    public $editingBomItemIndex = null;
    
    // BoM form fields
    public $bom_notes = '';
    public $bom_version = 1;
    
    // BoM Item form fields
    public $bom_item_stock_id = null;
    public $bom_item_quantity = 0;
    public $bom_item_unit = 'pcs';
    public $bom_item_is_primary = false;
    public $bom_item_notes = '';
    
    // Available units
    public $availableUnits = [
        'pcs', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm²', 'm³', 
        'dozen', 'pair', 'set', 'roll', 'sheet', 'unit', 
        'box', 'bottle', 'can', 'pack', 'bag', 'carton', 
        'case', 'barrel', 'drum', 'gallon', 'ounce', 'pound', 'ton'
    ];

    public function mount()
    {
        // Access: any user with restaurant module + BoM permission, or Manager, or Super Admin
        $user = Auth::user();
        $restaurantModule = \App\Models\Module::where('slug', 'restaurant')->first();
        $canAccess = $user->isSuperAdmin() || $user->isManager()
            || ($restaurantModule && $user->hasModuleAccess($restaurantModule->id) && $user->hasPermission('back_office_bom'));
        if (!$canAccess) {
            abort(403, 'Unauthorized. Bill of Menu requires restaurant access and the "Manage Bill of Menu" permission.');
        }

        $this->loadMenuItems();

        // Optional: pre-select menu item from query ?menu=id
        $menuId = request()->query('menu');
        if ($menuId && MenuItem::find($menuId)) {
            $this->selectMenuItem((int) $menuId);
        }
    }

    public function loadMenuItems()
    {
        $this->menuItems = MenuItem::with(['category', 'menuItemType'])
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function selectMenuItem($menuItemId)
    {
        $this->selectedMenuItemId = $menuItemId;
        $this->selectedMenuItem = MenuItem::with(['category', 'menuItemType'])->find($menuItemId);
        $this->loadBillOfMenus();
        $this->resetBomForm();
    }

    public function loadBillOfMenus()
    {
        if (!$this->selectedMenuItemId) {
            return;
        }
        
        $this->billOfMenus = BillOfMenu::where('menu_item_id', $this->selectedMenuItemId)
            ->with(['items.stockItem', 'createdBy'])
            ->orderBy('version', 'desc')
            ->get()
            ->toArray();
    }

    public function selectBom($bomId)
    {
        $this->selectedBomId = $bomId;
        $this->selectedBom = BillOfMenu::with(['items.stockItem'])->find($bomId);
        $this->bomItems = $this->selectedBom->items->toArray();
        $this->bom_notes = $this->selectedBom->notes ?? '';
        $this->bom_version = $this->selectedBom->version;
        
        // Load available stocks
        $this->loadAvailableStocks();
    }

    public function loadAvailableStocks()
    {
        // Prefer consumable stocks; fallback to all stocks if none consumable
        $query = Stock::orderBy('name');
        $consumable = Stock::whereHas('itemType', function ($q) {
            $q->where('is_consumable', true);
        })->count();
        if ($consumable > 0) {
            $query->whereHas('itemType', function ($q) {
                $q->where('is_consumable', true);
            });
        }
        $this->availableStocks = $query->get()->toArray();
    }

    public function openBomForm()
    {
        if (!$this->selectedMenuItemId) {
            session()->flash('error', 'Please select a menu item first.');
            return;
        }
        
        $menuItem = MenuItem::find($this->selectedMenuItemId);
        
        // Check if menu item type allows BoM
        if (!$menuItem->allowsBom()) {
            session()->flash('error', 'This menu item type does not allow Bill of Menu.');
            return;
        }
        
        // Get next version
        $this->bom_version = BillOfMenu::getNextVersion($this->selectedMenuItemId);
        $this->bom_notes = '';
        $this->bomItems = [];
        $this->loadAvailableStocks();
        $this->showBomForm = true;
    }

    public function closeBomForm()
    {
        $this->showBomForm = false;
        $this->resetBomForm();
    }

    public function resetBomForm()
    {
        $this->selectedBomId = null;
        $this->selectedBom = null;
        $this->bomItems = [];
        $this->bom_notes = '';
        $this->bom_version = 1;
    }

    public function addBomItem()
    {
        $this->validate([
            'bom_item_stock_id' => 'required|exists:stocks,id',
            'bom_item_quantity' => 'required|numeric|min:0.0001',
            'bom_item_unit' => 'required|string|max:50',
        ]);

        $stock = Stock::find($this->bom_item_stock_id);
        
        $this->bomItems[] = [
            'stock_item_id' => $this->bom_item_stock_id,
            'stock_name' => $stock->name,
            'stock_unit' => $stock->qty_unit ?? $stock->unit ?? 'pcs',
            'quantity' => $this->bom_item_quantity,
            'unit' => $this->bom_item_unit,
            'is_primary' => $this->bom_item_is_primary,
            'notes' => $this->bom_item_notes,
        ];

        $this->resetBomItemForm();
    }

    public function editBomItem($index)
    {
        $item = $this->bomItems[$index];
        $this->editingBomItemIndex = $index;
        $this->bom_item_stock_id = $item['stock_item_id'];
        $this->bom_item_quantity = $item['quantity'];
        $this->bom_item_unit = $item['unit'];
        $this->bom_item_is_primary = $item['is_primary'] ?? false;
        $this->bom_item_notes = $item['notes'] ?? '';
        $this->showBomItemForm = true;
    }

    public function updateBomItem()
    {
        $this->validate([
            'bom_item_stock_id' => 'required|exists:stocks,id',
            'bom_item_quantity' => 'required|numeric|min:0.0001',
            'bom_item_unit' => 'required|string|max:50',
        ]);

        $stock = Stock::find($this->bom_item_stock_id);
        
        $this->bomItems[$this->editingBomItemIndex] = [
            'stock_item_id' => $this->bom_item_stock_id,
            'stock_name' => $stock->name,
            'stock_unit' => $stock->qty_unit ?? $stock->unit ?? 'pcs',
            'quantity' => $this->bom_item_quantity,
            'unit' => $this->bom_item_unit,
            'is_primary' => $this->bom_item_is_primary,
            'notes' => $this->bom_item_notes,
        ];

        $this->resetBomItemForm();
    }

    public function removeBomItem($index)
    {
        unset($this->bomItems[$index]);
        $this->bomItems = array_values($this->bomItems);
    }

    public function resetBomItemForm()
    {
        $this->editingBomItemIndex = null;
        $this->bom_item_stock_id = null;
        $this->bom_item_quantity = 0;
        $this->bom_item_unit = 'pcs';
        $this->bom_item_is_primary = false;
        $this->bom_item_notes = '';
        $this->showBomItemForm = false;
    }

    public function openAddBomItemForm()
    {
        $this->editingBomItemIndex = null;
        $this->bom_item_stock_id = null;
        $this->bom_item_quantity = 0;
        $this->bom_item_unit = 'pcs';
        $this->bom_item_is_primary = false;
        $this->bom_item_notes = '';
        $this->showBomItemForm = true;
    }

    public function saveBom()
    {
        if (!$this->selectedMenuItemId) {
            session()->flash('error', 'Please select a menu item first.');
            return;
        }

        $menuItem = MenuItem::find($this->selectedMenuItemId);
        
        // Validate BoM requirements based on menu item type
        if ($menuItem->requiresBom() && empty($this->bomItems)) {
            session()->flash('error', 'This menu item type requires a Bill of Menu with at least one item.');
            return;
        }

        // For FINISHED_GOOD, BoM should have exactly 1 line
        if ($menuItem->menuItemType->code === 'FINISHED_GOOD' && count($this->bomItems) !== 1) {
            session()->flash('error', 'Finished Good items must have exactly one BoM line.');
            return;
        }

        // For SERVICE, BoM is not allowed
        if ($menuItem->menuItemType->code === 'SERVICE' && !empty($this->bomItems)) {
            session()->flash('error', 'Service items cannot have a Bill of Menu.');
            return;
        }

        // Validate that all items have valid stock
        foreach ($this->bomItems as $item) {
            if (!Stock::find($item['stock_item_id'])) {
                session()->flash('error', 'Invalid stock item selected.');
                return;
            }
        }

        // Create BoM
        $bom = BillOfMenu::create([
            'menu_item_id' => $this->selectedMenuItemId,
            'version' => $this->bom_version,
            'is_active' => false, // New BoM is inactive by default
            'created_by' => Auth::id(),
            'notes' => $this->bom_notes,
        ]);

        // Create BoM items
        foreach ($this->bomItems as $index => $item) {
            BillOfMenuItem::create([
                'bom_id' => $bom->bom_id,
                'stock_item_id' => $item['stock_item_id'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'is_primary' => $item['is_primary'] ?? false,
                'notes' => $item['notes'] ?? null,
                'display_order' => $index,
            ]);
        }

        session()->flash('message', 'Bill of Menu created successfully! You can activate it after review.');
        $this->loadBillOfMenus();
        $this->closeBomForm();
    }

    public function activateBom($bomId)
    {
        $bom = BillOfMenu::find($bomId);
        
        if (!$bom) {
            session()->flash('error', 'Bill of Menu not found.');
            return;
        }

        // Check if BoM has items
        if ($bom->items()->count() === 0) {
            session()->flash('error', 'Cannot activate BoM without items.');
            return;
        }

        // Activate this BoM (will deactivate others)
        $bom->activate();
        
        session()->flash('message', 'Bill of Menu activated successfully!');
        $this->loadBillOfMenus();
        if ($this->selectedBomId == $bomId) {
            $this->selectBom($bomId);
        }
    }

    public function deactivateBom($bomId)
    {
        $bom = BillOfMenu::find($bomId);
        
        if (!$bom) {
            session()->flash('error', 'Bill of Menu not found.');
            return;
        }

        $bom->deactivate();
        
        session()->flash('message', 'Bill of Menu deactivated successfully!');
        $this->loadBillOfMenus();
    }

    public function deleteBom($bomId)
    {
        if (! \Illuminate\Support\Facades\Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete Bill of Menu. You can deactivate instead.');
            return;
        }
        $bom = BillOfMenu::find($bomId);
        
        if (!$bom) {
            session()->flash('error', 'Bill of Menu not found.');
            return;
        }

        // Check if BoM can be deleted (not used in sales)
        // TODO: Implement check for sales usage
        if ($bom->is_active) {
            session()->flash('error', 'Cannot delete active Bill of Menu. Please deactivate it first.');
            return;
        }

        $bom->delete();
        
        session()->flash('message', 'Bill of Menu deleted successfully!');
        $this->loadBillOfMenus();
        $this->resetBomForm();
    }

    public function render()
    {
        return view('livewire.bill-of-menu-management')->layout('livewire.layouts.app-layout');
    }
}
