<?php

namespace App\Models;

use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected $fillable = [
        'stock_id',
        'movement_type',
        'quantity',
        'unit_price',
        'total_value',
        'from_department_id',
        'to_department_id',
        'reason',
        'user_id',
        'shift_id',
        'business_date',
        'notes',
        'goods_receipt_item_id',
        'order_item_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'business_date' => 'date',
    ];

    /**
     * The stock this movement belongs to
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * User who created this movement
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Goods receipt item that created this movement (if any)
     */
    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    /**
     * Order item that created this movement (POS sale deduction)
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Shift this movement belongs to
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Source department (for transfers)
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * Destination department (for transfers)
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * Check if this is an IN movement (adds to stock)
     */
    public function isInMovement(): bool
    {
        return in_array($this->movement_type, ['OPENING', 'PURCHASE', 'TRANSFER']) && $this->quantity > 0;
    }

    /**
     * Check if this is an OUT movement (reduces stock)
     */
    public function isOutMovement(): bool
    {
        return in_array($this->movement_type, ['WASTE', 'ADJUST', 'SALE', 'TRANSFER']) && $this->quantity < 0;
    }
}
