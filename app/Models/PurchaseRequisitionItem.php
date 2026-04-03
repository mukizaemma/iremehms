<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'line_id';

    protected $fillable = [
        'requisition_id',
        'item_id',
        'quantity_requested',
        'unit_id',
        'estimated_unit_cost',
        'notes',
        'stock_request_item_id',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
    ];

    /**
     * Requisition this item belongs to
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * Stock item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'item_id');
    }

    /**
     * Stock request item this PR line was created from (when "add to requisition" from Stock Out)
     */
    public function stockRequestItem(): BelongsTo
    {
        return $this->belongsTo(StockRequestItem::class, 'stock_request_item_id');
    }
}
