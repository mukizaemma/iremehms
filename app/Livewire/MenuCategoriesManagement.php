<?php

namespace App\Livewire;

use App\Models\MenuCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class MenuCategoriesManagement extends Component
{
    public $categories = [];
    public $showCategoryForm = false;
    public $editingCategoryId = null;
    
    // Form fields
    public $name = '';
    public $code = '';
    public $description = '';
    public string $pos_report_column_key = 'other';
    public $display_order = 0;
    public $is_active = true;
    
    // Filters
    public $search = '';

    public function mount()
    {
        // Access: any user with restaurant module + permission, or Manager, or Super Admin
        $user = Auth::user();
        $restaurantModule = \App\Models\Module::where('slug', 'restaurant')->first();
        $canAccess = $user->isSuperAdmin() || $user->isManager() || $user->isRestaurantManager()
            || ($restaurantModule && $user->hasModuleAccess($restaurantModule->id) && $user->hasPermission('back_office_menu_items'));
        if (!$canAccess) {
            abort(403, 'Unauthorized. Menu categories require restaurant access and the "Manage menu items" permission.');
        }

        $this->loadCategories();
    }

    public function loadCategories()
    {
        $query = MenuCategory::query();
        
        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }
        
        $this->categories = $query->orderBy('display_order')->orderBy('name')->get()->toArray();
    }

    public function openCategoryForm($categoryId = null)
    {
        $this->editingCategoryId = $categoryId;
        
        if ($categoryId) {
            $category = MenuCategory::find($categoryId);
            $this->name = $category->name;
            $this->code = $category->code ?? '';
            $this->description = $category->description ?? '';
            $this->pos_report_column_key = $category->pos_report_column_key ?? 'other';
            $this->display_order = $category->display_order ?? 0;
            $this->is_active = $category->is_active;
        } else {
            $this->resetForm();
        }
        
        $this->showCategoryForm = true;
    }

    public function closeCategoryForm()
    {
        $this->showCategoryForm = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingCategoryId = null;
        $this->name = '';
        $this->code = '';
        $this->description = '';
        $this->pos_report_column_key = 'other';
        $this->display_order = 0;
        $this->is_active = true;
    }

    public function saveCategory()
    {
        $hasPosBucketColumn = Schema::hasColumn('menu_categories', 'pos_report_column_key');

        $this->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:menu_categories,code,' . $this->editingCategoryId . ',category_id',
            'description' => 'nullable|string',
            'pos_report_column_key' => 'nullable|string|in:food,beverages,conference_halls,rooms,swimming_pool,sauna,massage,gym,garden,outside_catering,other',
            'display_order' => 'nullable|integer|min:0',
        ]);

        if ($this->editingCategoryId) {
            $category = MenuCategory::find($this->editingCategoryId);
            $payload = [
                'name' => $this->name,
                'code' => $this->code ?: null,
                'description' => $this->description,
                'display_order' => $this->display_order,
                'is_active' => $this->is_active,
            ];

            if ($hasPosBucketColumn) {
                $payload['pos_report_column_key'] = $this->pos_report_column_key ?: 'other';
            }

            $category->update($payload);
            session()->flash('message', 'Menu category updated successfully!');
        } else {
            $payload = [
                'name' => $this->name,
                'code' => $this->code ?: null,
                'description' => $this->description,
                'display_order' => $this->display_order,
                'is_active' => $this->is_active,
            ];

            if ($hasPosBucketColumn) {
                $payload['pos_report_column_key'] = $this->pos_report_column_key ?: 'other';
            }

            MenuCategory::create($payload);
            session()->flash('message', 'Menu category created successfully!');
        }

        $this->loadCategories();
        $this->closeCategoryForm();
    }

    public function deleteCategory($categoryId)
    {
        if (! Auth::user()->isSuperAdmin()) {
            session()->flash('error', 'Only Super Admin can delete categories. You can deactivate instead.');
            return;
        }
        $category = MenuCategory::find($categoryId);
        
        // Check if category has menu items
        if ($category->menuItems()->count() > 0) {
            session()->flash('error', 'Cannot delete category. It has menu items associated with it.');
            return;
        }
        
        $category->delete();
        session()->flash('message', 'Menu category deleted successfully!');
        $this->loadCategories();
    }

    public function toggleActive($categoryId)
    {
        $category = MenuCategory::find($categoryId);
        $category->is_active = !$category->is_active;
        $category->save();
        $this->loadCategories();
    }

    public function updatedSearch()
    {
        $this->loadCategories();
    }

    public function render()
    {
        return view('livewire.menu-categories-management')->layout('livewire.layouts.app-layout');
    }
}
