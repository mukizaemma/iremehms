<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingStockDeduction extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'stock_id',
        'quantity_required',
        'quantity_available_at_sale',
        'status',
        'deducted_at',
        'notes',
    ];

    protected $casts = [
        'quantity_required' => 'decimal:4',
        'quantity_available_at_sale' => 'decimal:4',
        'deducted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_DEDUCTED = 'DEDUCTED';
    public const STATUS_WRITTEN_OFF = 'WRITTEN_OFF';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
