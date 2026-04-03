<?php

namespace App\Models;

use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected $primaryKey = 'receipt_id';

    protected $fillable = [
        'requisition_id',
        'supplier_id',
        'received_by',
        'department_id',
        'business_date',
        'shift_id',
        'receipt_status',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
    ];

    /**
     * Purchase requisition this receipt is based on (if any)
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * User who received the goods
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Items in this receipt
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'receipt_id');
    }

    /**
     * Get total cost of all items
     */
    public function getTotalCostAttribute(): float
    {
        return $this->items->sum('total_cost');
    }
}
