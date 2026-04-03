<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMenu extends Model
{
    use HasFactory;

    protected $primaryKey = 'bom_id';

    protected $table = 'bill_of_menu';

    protected $fillable = [
        'menu_item_id',
        'version',
        'is_active',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Menu item this BoM belongs to
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id', 'menu_item_id');
    }

    /**
     * User who created this BoM
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * BoM lines (ingredients)
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillOfMenuItem::class, 'bom_id', 'bom_id');
    }

    /**
     * Activate this BoM (deactivates other active BoMs for the same menu item)
     */
    public function activate()
    {
        // Deactivate all other BoMs for this menu item
        static::where('menu_item_id', $this->menu_item_id)
            ->where('bom_id', '!=', $this->bom_id)
            ->update(['is_active' => false]);

        // Activate this BoM
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate this BoM
     */
    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Check if this BoM can be edited (not used in sales)
     */
    public function canBeEdited(): bool
    {
        // TODO: Check if this BoM has been used in any sales
        // For now, allow editing if not active
        return !$this->is_active;
    }

    /**
     * Get next version number for this menu item
     */
    public static function getNextVersion($menuItemId): int
    {
        $maxVersion = static::where('menu_item_id', $menuItemId)->max('version');
        return ($maxVersion ?? 0) + 1;
    }
}
