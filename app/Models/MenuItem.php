<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'menu_item_id';

    protected $fillable = [
        'category_id',
        'menu_item_type_id',
        'name',
        'code',
        'description',
        'sale_price',
        'currency',
        'sale_unit',
        'is_active',
        'allows_bom',
        'display_order',
        'preparation_station',
        'image',
        'menu_cost',
        'cost_extra',
        'margin_percent',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'menu_cost' => 'decimal:2',
        'cost_extra' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'is_active' => 'boolean',
        'allows_bom' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Category this menu item belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id', 'category_id');
    }

    /**
     * Menu item type
     */
    public function menuItemType(): BelongsTo
    {
        return $this->belongsTo(MenuItemType::class, 'menu_item_type_id', 'type_id');
    }

    /**
     * Bill of Menu (BoM) for this menu item
     */
    public function billOfMenus(): HasMany
    {
        return $this->hasMany(BillOfMenu::class, 'menu_item_id', 'menu_item_id');
    }

    /**
     * Eager-loadable relation: active Bill of Menu (one per menu item)
     */
    public function activeBillOfMenuRelation(): HasOne
    {
        return $this->hasOne(BillOfMenu::class, 'menu_item_id', 'menu_item_id')->where('is_active', true);
    }

    /**
     * Get the active Bill of Menu (instance)
     */
    public function activeBillOfMenu()
    {
        return $this->billOfMenus()->where('is_active', true)->first();
    }

    /**
     * POS option groups configured for this menu item (temperature, extras, etc.).
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuItemOptionGroup::class, 'menu_item_id', 'menu_item_id')->orderBy('display_order');
    }

    /**
     * Check if menu item has an active BoM
     */
    public function hasActiveBom(): bool
    {
        return $this->billOfMenus()->where('is_active', true)->exists();
    }

    /**
     * Check if menu item requires a BoM based on its type
     */
    public function requiresBom(): bool
    {
        return $this->menuItemType && $this->menuItemType->requires_bom;
    }

    /**
     * Check if menu item allows a BoM (per-item flag, or type default)
     */
    public function allowsBom(): bool
    {
        if (array_key_exists('allows_bom', $this->getAttributes())) {
            return (bool) $this->allows_bom;
        }
        return $this->menuItemType && $this->menuItemType->allows_bom;
    }

    /**
     * Preparation stations this item is sent to (many-to-many).
     * When empty, legacy preparation_station (string) on menu_items may be used.
     */
    public function preparationStations(): BelongsToMany
    {
        return $this->belongsToMany(PreparationStation::class, 'menu_item_station', 'menu_item_id', 'preparation_station_id');
    }

    /**
     * Scope for active menu items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
