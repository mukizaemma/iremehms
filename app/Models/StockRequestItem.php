<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    protected $fillable = [
        'stock_request_id',
        'stock_id',
        'quantity',
        'quantity_issued',
        'issue_status',
        'to_stock_location_id',
        'to_department_id',
        'edit_data',
        'purchase_requisition_item_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_issued' => 'decimal:4',
        'edit_data' => 'array',
    ];

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function toStockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_stock_location_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function purchaseRequisitionItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'purchase_requisition_item_id', 'line_id');
    }

    public function isPendingIssue(): bool
    {
        return $this->issue_status === 'pending' || $this->issue_status === 'partial';
    }

    public function isFullyIssued(): bool
    {
        return $this->issue_status === 'issued' || (float) $this->quantity_issued >= (float) $this->quantity;
    }

    public function isOnRequisition(): bool
    {
        return $this->issue_status === 'on_requisition';
    }
}
