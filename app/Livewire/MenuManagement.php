<?php

namespace App\Livewire;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemType;
use App\Models\PreparationStation;
use App\Models\MenuItemOptionGroup;
use App\Models\MenuItemOption;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class MenuManagement extends Component
{
    use WithFileUploads;

    public $menuItems = [];
    public $categories = [];
    public $menuItemTypes = [];
    public $showMenuItemForm = false;
    public $editingMenuItemId = null;
    
    // Form fields
    public $name = '';
    public $code = '';
    public $description = '';
    public $category_id = null;
    public $menu_item_type_id = null;
    public $sale_price = 0;
    public $menu_cost = null; // cost per unit (optional; used with sale_price for margin)
    public $currency = 'RWF';
    public $sale_unit = 'pcs';
    public $is_active = true;
    public $allows_bom = true;
    public $display_order = 0;
    public $preparation_station = null; // slug (legacy) or id – required; one station from DB
    public $preparation_station_id = null; // preferred: ID from preparation_stations table
    public $image;
    public $imagePreview;

    /** Per-menu-item POS option groups (for waiters to choose from). */
    public array $optionGroups = [];
    
    // Filters
    public $search = '';
    public $filter_category = '';
    public $filter_type = '';
    public $filter_active = '';

    // Available units (same as stock management)
    public $availableUnits = [
        'pcs', 'kg', 'g', 'l', 'ml', 'm', 'cm', 'm²', 'm³', 
        'dozen', 'pair', 'set', 'roll', 'sheet', 'unit', 
        'box', 'bottle', 'can', 'pack', 'bag', 'carton', 
        'case', 'barrel', 'drum', 'gallon', 'ounce', 'pound', 'ton',
        'plate', 'serving', 'portion', 'cup', 'bowl'
    ];

    public function mount()
    {
        // Access: any user with restaurant module + permission, or Manager, or Super Admin
        $user = Auth::user();
        $restaurantModule = \App\Models\Module::where('slug', 'restaurant')->first();
        $canAccess = $user->isSuperAdmin() || $user->isManager() || $user->isRestaurantManager()
            || ($restaurantModule && $user->hasModuleAccess($restaurantModule->id) && $user->hasPermission('back_office_menu_items'));
        if (!$canAccess) {
            abort(403, 'Unauthorized. Menu management requires restaurant access and the "Manage menu items" permission.');
        }

        // Set default currency from system
        $this->currency = CurrencyHelper::getCurrency();

        // Allow initial filter from query string (e.g. from Item Types "View menu items")
        if (request()->has('filter_type')) {
            $this->filter_type = (string) request()->get('filter_type');
        }
        
        $this->loadData();
    }

    public function getUseBomForMenuItemsProperty(): bool
    {
        $hotel = \App\Models\Hotel::getHotel();
        return (bool) ($hotel->use_bom_for_menu_items ?? true);
    }

    public function loadData()
    {
        // Use arrays for consistency with other Livewire lists
        $this->categories = MenuCategory::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->menuItemTypes = MenuItemType::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();
        $this->loadMenuItems();
    }

    public function loadMenuItems()
    {
        $query = MenuItem::with(['category', 'menuItemType', 'activeBillOfMenuRelation']);
        
        // Filter by category
        if ($this->filter_category) {
            $query->where('category_id', $this->filter_category);
        }
        
        // Filter by type
        if ($this->filter_type) {
            $query->where('menu_item_type_id', $this->filter_type);
        }
        
        // Filter by active status
        if ($this->filter_active !== '') {
            $query->where('is_active', $this->filter_active === '1');
        }
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }
        
        $this->menuItems = $query->orderBy('display_order')->orderBy('name')->get()->toArray();
    }

    public function openMenuItemForm($menuItemId = null)
    {
        $this->editingMenuItemId = $menuItemId;
        
        if ($menuItemId) {
            $menuItem = MenuItem::find($menuItemId);
            $this->name = $menuItem->name;
            $this->code = $menuItem->code ?? '';
            $this->description = $menuItem->description ?? '';
            $this->category_id = $menuItem->category_id;
            $this->menu_item_type_id = $menuItem->menu_item_type_id;
            $this->sale_price = $menuItem->sale_price;
            $this->menu_cost = $menuItem->menu_cost !== null ? (float) $menuItem->menu_cost : null;
            $this->currency = $menuItem->currency ?? CurrencyHelper::getCurrency();
            $this->sale_unit = $menuItem->sale_unit;
            $this->is_active = $menuItem->is_active;
            $this->allows_bom = $menuItem->getAttribute('allows_bom') ?? true;
            $this->display_order = $menuItem->display_order ?? 0;
            $this->preparation_station = $menuItem->preparation_station ?? null;
            $firstStation = $menuItem->preparationStations()->first();
            $this->preparation_station_id = $firstStation ? $firstStation->id : null;

            if ($menuItem->image) {
                $this->imagePreview = \Illuminate\Support\Facades\Storage::url($menuItem->image);
            } else {
                $this->imagePreview = null;
            }

            // Load configured POS option groups for this menu item.
            $this->optionGroups = $menuItem->optionGroups()
                ->with('options')
                ->get()
                ->map(function (MenuItemOptionGroup $g) {
                    return [
                        'id' => $g->id,
                        'name' => $g->name,
                        'code' => $g->code,
                        'type' => $g->type,
                        'display_order' => $g->display_order,
                        'options' => $g->options->sortBy('display_order')->values()->map(function (MenuItemOption $o) {
                            return [
                                'id' => $o->id,
                                'label' => $o->label,
                                'value' => $o->value,
                                'price_delta' => (string) $o->price_delta,
                                'is_default' => $o->is_default,
                                'display_order' => $o->display_order,
                            ];
                        })->toArray(),
                    ];
                })
                ->toArray();
        } else {
            $this->resetForm();
        }
        
        $this->showMenuItemForm = true;
    }

    public function updatedImage()
    {
        $this->validate(['image' => 'image|max:2048']);
        $this->imagePreview = $this->image->temporaryUrl();
    }

    public function closeMenuItemForm()
    {
        $this->showMenuItemForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingMenuItemId = null;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->category_id = null;
        $this->menu_item_type_id = null;
        $this->sale_price = 0;
        $this->menu_cost = null;
        $this->currency = CurrencyHelper::getCurrency();
        $this->sale_unit = 'pcs';
        $this->is_active = true;
        $this->allows_bom = true;
        $this->display_order = 0;
        $this->preparation_station = null;
        $this->preparation_station_id = null;
        $this->image = null;
        $this->imagePreview = null;
        $this->optionGroups = [];
    }

    public function addOptionGroup(): void
    {
        $this->optionGroups[] = [
            'name' => '',
            'code' => null,
            'type' => 'single',
            'display_order' => count($this->optionGroups),
            'options' => [],
        ];
    }

    public function removeOptionGroup(int $index): void
    {
        if (! isset($this->optionGroups[$index])) {
            return;
        }
        unset($this->optionGroups[$index]);
        $this->optionGroups = array_values($this->optionGroups);
    }

    public function addOptionToGroup(int $groupIndex): void
    {
        if (! isset($this->optionGroups[$groupIndex])) {
            return;
        }
        $this->optionGroups[$groupIndex]['options'][] = [
            'label' => '',
            'value' => '',
            'price_delta' => '0',
            'is_default' => false,
            'display_order' => count($this->optionGroups[$groupIndex]['options'] ?? []),
        ];
    }

    public function removeOptionFromGroup(int $groupIndex, int $optionIndex): void
    {
        if (! isset($this->optionGroups[$groupIndex]['options'][$optionIndex])) {
            return;
        }
        unset($this->optionGroups[$groupIndex]['options'][$optionIndex]);
        $this->optionGroups[$groupIndex]['options'] = array_values($this->optionGroups[$groupIndex]['options']);
    }

    public function saveMenuItem()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('menu_items', 'code')->ignore($this->editingMenuItemId ?? 0, 'menu_item_id')],
            'description' => 'nullable|string',
            'category_id' => 'required|exists:menu_categories,category_id',
            'menu_item_type_id' => 'required|exists:menu_item_types,type_id',
            'sale_price' => 'required|numeric|min:0',
            'menu_cost' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'sale_unit' => 'required|string|max:50',
            'display_order' => 'nullable|integer|min:0',
            'preparation_station_id' => 'required|exists:preparation_stations,id',
            'preparation_station' => 'nullable|string|max:50',
            'image' => 'nullable|image|max:2048',
        ]);

        $menuItemData = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'menu_item_type_id' => $this->menu_item_type_id,
            'sale_price' => $this->sale_price,
            'menu_cost' => $this->menu_cost !== null && $this->menu_cost !== '' ? (float) $this->menu_cost : null,
            'currency' => CurrencyHelper::getCurrency(),
            'sale_unit' => $this->sale_unit,
            'is_active' => $this->is_active,
            'allows_bom' => $this->allows_bom,
            'display_order' => $this->display_order,
            'preparation_station' => null,
        ];
        $station = PreparationStation::find($this->preparation_station_id);
        if ($station) {
            $menuItemData['preparation_station'] = $station->slug;
        }

        // Handle image upload
        if ($this->image) {
            // Delete old image if exists
            if ($this->editingMenuItemId) {
                $oldMenuItem = MenuItem::find($this->editingMenuItemId);
                if ($oldMenuItem && $oldMenuItem->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldMenuItem->image)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldMenuItem->image);
                }
            }
            
            $imagePath = $this->image->store('menu-items', 'public');
            $menuItemData['image'] = $imagePath;
            $this->imagePreview = \Illuminate\Support\Facades\Storage::url($imagePath);
        }

        if ($this->editingMenuItemId) {
            $menuItem = MenuItem::find($this->editingMenuItemId);
            $menuItem->update($menuItemData);
            $menuItem->preparationStations()->sync($this->preparation_station_id ? [$this->preparation_station_id] : []);
            session()->flash('message', 'Menu item updated successfully!');
        } else {
            $menuItem = MenuItem::create($menuItemData);
            $menuItem->preparationStations()->sync($this->preparation_station_id ? [$this->preparation_station_id] : []);
            session()->flash('message', 'Menu item created successfully!');
        }

        // Persist POS option groups and their options for this menu item.
        $this->saveMenuItemOptions($menuItem);

        $this->loadMenuItems();
        $this->closeMenuItemForm();
    }

    public function deleteMenuItem($menuItemId)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete menu items. You can deactivate instead.');
            return;
        }
        $menuItem = MenuItem::find($menuItemId);
        
        // Check if menu item has BoM
        if ($menuItem->billOfMenus()->count() > 0) {
            session()->flash('error', 'Cannot delete menu item. It has Bill of Menu associated with it. Please delete the BoM first.');
            return;
        }
        
        // Delete image if exists
        if ($menuItem->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($menuItem->image)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($menuItem->image);
        }
        
        $menuItem->delete();
        session()->flash('message', 'Menu item deleted successfully!');
        $this->loadMenuItems();
    }

    public function toggleActive($menuItemId)
    {
        $menuItem = MenuItem::find($menuItemId);
        $menuItem->is_active = !$menuItem->is_active;
        $menuItem->save();
        $this->loadMenuItems();
    }

    public function updatedSearch()
    {
        $this->loadMenuItems();
    }

    public function updatedFilterCategory()
    {
        $this->loadMenuItems();
    }

    public function updatedFilterType()
    {
        $this->loadMenuItems();
    }

    public function updatedFilterActive()
    {
        $this->loadMenuItems();
    }

    public function getPreparationStations(): array
    {
        return PreparationStation::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->keyBy('slug')
            ->map(fn ($s) => $s->name)
            ->all();
    }

    /**
     * Save configured option groups + options for a menu item.
     * For now we take a simple approach: remove existing groups/options and recreate from form arrays.
     */
    protected function saveMenuItemOptions(MenuItem $menuItem): void
    {
        // Wipe existing definitions
        $menuItem->optionGroups()->each(function (MenuItemOptionGroup $group) {
            $group->options()->delete();
            $group->delete();
        });

        foreach ($this->optionGroups as $idx => $groupData) {
            $name = trim((string) ($groupData['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = in_array($groupData['type'] ?? 'single', ['single', 'multi'], true) ? $groupData['type'] : 'single';
            $displayOrder = (int) ($groupData['display_order'] ?? $idx);
            $group = MenuItemOptionGroup::create([
                'menu_item_id' => $menuItem->menu_item_id,
                'name' => $name,
                'code' => $groupData['code'] ?? null,
                'type' => $type,
                'display_order' => $displayOrder,
            ]);

            $options = $groupData['options'] ?? [];
            foreach ($options as $optIdx => $opt) {
                $label = trim((string) ($opt['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $value = trim((string) ($opt['value'] ?? '')) ?: \Illuminate\Support\Str::slug($label, '_');
                $priceDelta = (float) (($opt['price_delta'] ?? '0') ?: 0);
                $isDefault = (bool) ($opt['is_default'] ?? false);
                $optOrder = (int) ($opt['display_order'] ?? $optIdx);
                MenuItemOption::create([
                    'group_id' => $group->id,
                    'label' => $label,
                    'value' => $value,
                    'price_delta' => $priceDelta,
                    'is_default' => $isDefault,
                    'display_order' => $optOrder,
                ]);
            }
        }
    }

    public function render()
    {
        return view('livewire.menu-management', [
            'preparationStations' => $this->getPreparationStations(),
            'preparationStationsList' => PreparationStation::where('is_active', true)->orderBy('display_order')->orderBy('name')->get(),
        ])->layout('livewire.layouts.app-layout');
    }
}
