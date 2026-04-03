<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'table_id',
        'waiter_id',
        'session_id',
        'order_status',
        'order_ticket_printed_at',
        'transferred_from_id',
        'transfer_comment',
    ];

    protected $casts = [
        'order_ticket_printed_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(OrderTransferLog::class);
    }

    public function isOpen(): bool
    {
        return $this->order_status === 'OPEN';
    }

    public function isPaid(): bool
    {
        return $this->order_status === 'PAID';
    }

    public function isConfirmed(): bool
    {
        return $this->order_status === 'CONFIRMED';
    }

    public function canEditItems(): bool
    {
        return $this->order_status === 'OPEN';
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->orderItems()->sum('line_total');
    }
}
