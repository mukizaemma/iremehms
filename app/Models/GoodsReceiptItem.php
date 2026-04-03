<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'line_id';

    protected $fillable = [
        'receipt_id',
        'item_id',
        'location_id',
        'quantity_received',
        'unit_id',
        'unit_cost',
        'total_cost',
        'notes',
        'purchase_requisition_item_id',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Goods receipt this item belongs to
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'receipt_id');
    }

    /**
     * Stock item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'item_id');
    }

    /**
     * Stock location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    /**
     * Purchase requisition line this receipt line came from (when from Stock Out requisition)
     */
    public function purchaseRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'purchase_requisition_item_id', 'line_id');
    }

    /**
     * Stock movements created from this receipt item
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'goods_receipt_item_id');
    }

    /**
     * Calculate total cost
     */
    public function calculateTotalCost(): void
    {
        $this->total_cost = $this->quantity_received * $this->unit_cost;
    }
}
