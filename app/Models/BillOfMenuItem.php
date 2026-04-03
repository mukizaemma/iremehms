<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillOfMenuItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'bom_line_id';

    protected $table = 'bill_of_menu_items';

    protected $fillable = [
        'bom_id',
        'stock_item_id',
        'quantity',
        'unit',
        'is_primary',
        'notes',
        'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_primary' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Bill of Menu this line belongs to
     */
    public function billOfMenu(): BelongsTo
    {
        return $this->belongsTo(BillOfMenu::class, 'bom_id', 'bom_id');
    }

    /**
     * Stock item (ingredient) used
     */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_item_id');
    }

    /**
     * Convert quantity to base unit of stock item
     * This is a placeholder - actual conversion logic will be implemented
     */
    public function getQuantityInBaseUnit(): float
    {
        // TODO: Implement unit conversion logic
        // For now, return quantity as-is
        // This should convert based on stock item's base unit
        return (float) $this->quantity;
    }
}
