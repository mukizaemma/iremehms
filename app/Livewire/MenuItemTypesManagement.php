<?php

namespace App\Livewire;

use App\Models\MenuItem;
use App\Models\MenuItemType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MenuItemTypesManagement extends Component
{
    public $types = [];
    public $showTypeForm = false;
    public $editingTypeId = null;

    /** @var bool Show modal listing menu items for a type */
    public $showMenuItemsModal = false;
    /** @var array Menu items for the selected type (for modal) */
    public $menuItemsForType = [];
    /** @var string Name of the type whose menu items we're viewing */
    public $viewingTypeName = '';
    /** @var int|null Type ID for linking to Menu Items filtered by this type */
    public $viewingTypeId = null;

    // Form fields
    public $code = '';
    public $name = '';
    public $description = '';
    public $requires_bom = false;
    public $allows_bom = true;
    public $affects_stock = true;
    public $is_active = true;

    // Filters
    public $search = '';

    public function mount()
    {
        $user = Auth::user();
        $restaurantModule = \App\Models\Module::where('slug', 'restaurant')->first();
        $canAccess = $user->isSuperAdmin() || $user->isManager() || $user->isRestaurantManager()
            || ($restaurantModule && $user->hasModuleAccess($restaurantModule->id) && $user->hasPermission('back_office_menu_items'));
        if (!$canAccess) {
            abort(403, 'Unauthorized. Menu item types require restaurant access and the "Manage menu items" permission.');
        }

        $this->loadTypes();
    }

    public function loadTypes()
    {
        $query = MenuItemType::withCount('menuItems');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('code', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        $this->types = $query->orderBy('code')->get()->toArray();
    }

    public function openTypeForm($typeId = null)
    {
        $this->editingTypeId = $typeId;

        if ($typeId) {
            $type = MenuItemType::find($typeId);
            $this->code = $type->code;
            $this->name = $type->name;
            $this->description = $type->description ?? '';
            $this->requires_bom = $type->requires_bom;
            $this->allows_bom = $type->allows_bom;
            $this->affects_stock = $type->affects_stock;
            $this->is_active = $type->is_active;
        } else {
            $this->resetForm();
        }

        $this->showTypeForm = true;
    }

    public function closeTypeForm()
    {
        $this->showTypeForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingTypeId = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->requires_bom = false;
        $this->allows_bom = true;
        $this->affects_stock = true;
        $this->is_active = true;
    }

    public function saveType()
    {
        $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('menu_item_types', 'code')->ignore($this->editingTypeId ?? 0, 'type_id')],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'requires_bom' => $this->requires_bom,
            'allows_bom' => $this->allows_bom,
            'affects_stock' => $this->affects_stock,
            'is_active' => $this->is_active,
        ];

        if ($this->editingTypeId) {
            MenuItemType::find($this->editingTypeId)->update($data);
            session()->flash('message', 'Menu item type updated successfully!');
        } else {
            MenuItemType::create($data);
            session()->flash('message', 'Menu item type created successfully!');
        }

        $this->loadTypes();
        $this->closeTypeForm();
    }

    public function deleteType($typeId)
    {
        $type = MenuItemType::find($typeId);
        if ($type->menuItems()->count() > 0) {
            session()->flash('error', 'Cannot delete type. It has menu items associated with it.');
            return;
        }
        $type->delete();
        session()->flash('message', 'Menu item type deleted successfully!');
        $this->loadTypes();
    }

    public function toggleActive($typeId)
    {
        $type = MenuItemType::find($typeId);
        $type->is_active = !$type->is_active;
        $type->save();
        $this->loadTypes();
    }

    /**
     * Open modal to view menu items for the given item type.
     */
    public function viewMenuItems(int $typeId): void
    {
        $type = MenuItemType::find($typeId);
        if (!$type) {
            return;
        }
        $this->viewingTypeId = $typeId;
        $this->viewingTypeName = $type->name . ' (' . $type->code . ')';
        $this->menuItemsForType = MenuItem::with(['category', 'menuItemType'])
            ->where('menu_item_type_id', $typeId)
            ->orderBy('name')
            ->get()
            ->toArray();
        $this->showMenuItemsModal = true;
    }

    public function closeMenuItemsModal(): void
    {
        $this->showMenuItemsModal = false;
        $this->menuItemsForType = [];
        $this->viewingTypeName = '';
        $this->viewingTypeId = null;
    }

    public function updatedSearch()
    {
        $this->loadTypes();
    }

    public function render()
    {
        return view('livewire.menu-item-types-management')->layout('livewire.layouts.app-layout');
    }
}
