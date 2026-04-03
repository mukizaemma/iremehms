<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_consumable',
        'is_sellable',
        'allows_waste',
        'allows_transfer',
        'allows_adjustment',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_consumable' => 'boolean',
        'is_sellable' => 'boolean',
        'allows_waste' => 'boolean',
        'allows_transfer' => 'boolean',
        'allows_adjustment' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get all stocks of this item type
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Check if movement type is allowed for this item type
     */
    public function allowsMovementType(string $movementType): bool
    {
        return match($movementType) {
            'OPENING', 'PURCHASE', 'TRANSFER' => true,
            'WASTE' => $this->allows_waste,
            'ADJUST' => $this->allows_adjustment,
            'SALE' => $this->is_sellable, // Phase 3: No sales yet
            default => false,
        };
    }

    /**
     * Get active item types
     */
    public static function active()
    {
        return static::where('is_active', true)->orderBy('order');
    }
}
