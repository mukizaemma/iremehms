<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemType extends Model
{
    use HasFactory;

    protected $primaryKey = 'type_id';

    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_bom',
        'allows_bom',
        'affects_stock',
        'is_active',
    ];

    protected $casts = [
        'requires_bom' => 'boolean',
        'allows_bom' => 'boolean',
        'affects_stock' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Menu items of this type
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_item_type_id', 'type_id');
    }

    /**
     * Check if this type requires a BoM
     */
    public function requiresBom(): bool
    {
        return $this->requires_bom;
    }

    /**
     * Check if this type allows a BoM
     */
    public function allowsBom(): bool
    {
        return $this->allows_bom;
    }

    /**
     * Check if this type affects stock
     */
    public function affectsStock(): bool
    {
        return $this->affects_stock;
    }
}
