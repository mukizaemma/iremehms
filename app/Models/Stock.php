<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'department_id',
        'item_type_id',
        'is_sellable',
        'is_consumable',
        'tracking_method',
        'expected_lifespan',
        'reorder_level',
        'reorder_quantity',
        'location',
        'stock_location_id',
        // New fields
        'use_barcode',
        'barcode',
        'package_unit',
        'package_size',
        'qty_unit',
        'purchase_price',
        'sale_price',
        'tax_type',
        'beginning_stock_qty',
        'current_stock',
        'safety_stock',
        'use_expiration',
        'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'package_size' => 'decimal:4',
        'beginning_stock_qty' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'safety_stock' => 'decimal:2',
        'is_sellable' => 'boolean',
        'is_consumable' => 'boolean',
        'use_barcode' => 'boolean',
        'use_expiration' => 'boolean',
        'expected_lifespan' => 'integer',
        'reorder_level' => 'decimal:2',
        'reorder_quantity' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    /**
     * Item type this stock belongs to
     */
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    /**
     * Department this stock belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Stock location this item is stored in
     */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    /**
     * Get effective is_sellable (from item type or override)
     */
    public function getEffectiveIsSellable(): bool
    {
        if ($this->is_sellable !== null) {
            return $this->is_sellable;
        }
        return $this->itemType?->is_sellable ?? false;
    }

    /**
     * Get effective is_consumable (from item type or override)
     */
    public function getEffectiveIsConsumable(): bool
    {
        if ($this->is_consumable !== null) {
            return $this->is_consumable;
        }
        return $this->itemType?->is_consumable ?? true;
    }

    /**
     * Check if movement type is allowed for this stock
     */
    public function allowsMovementType(string $movementType): bool
    {
        if (!$this->itemType) {
            return false; // Item type is mandatory
        }
        return $this->itemType->allowsMovementType($movementType);
    }

    /**
     * Check if stock can go negative
     * Phase 3: Hard block for all item types
     */
    public function canGoNegative(): bool
    {
        return false; // Hard block - no negative stock allowed
    }

}
