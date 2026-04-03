<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'line_total',
        'notes',
        'selected_options',
        'ingredient_overrides',
        'preparation_status',
        'preparation_ready_at',
        'sent_to_station_at',
        'printed_at',
        'posted_to_station',
        'voided_at',
        'voided_by_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'preparation_ready_at' => 'datetime',
        'sent_to_station_at' => 'datetime',
        'printed_at' => 'datetime',
        'voided_at' => 'datetime',
        'selected_options' => 'array',
        'ingredient_overrides' => 'array',
    ];

    public const PREPARATION_PENDING = 'pending';
    public const PREPARATION_READY = 'ready';

    public function isReady(): bool
    {
        return $this->preparation_status === self::PREPARATION_READY;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id', 'menu_item_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'order_item_id');
    }

    public function voidRequests(): HasMany
    {
        return $this->hasMany(OrderItemVoidRequest::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_id');
    }

    /** Station this item is (or will be) sent to: waiter override or menu default */
    public function getEffectiveStationAttribute(): ?string
    {
        if ($this->posted_to_station !== null && $this->posted_to_station !== '') {
            return $this->posted_to_station;
        }
        return $this->menuItem?->preparation_station;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Waiter can only remove if item was never posted or printed */
    public function canRemove(): bool
    {
        return ! $this->isVoided() && ! $this->sent_to_station_at && ! $this->printed_at;
    }

    public function isPosted(): bool
    {
        return $this->sent_to_station_at !== null;
    }

    public function isPrinted(): bool
    {
        return $this->printed_at !== null;
    }

    public static function booted()
    {
        static::saving(function (self $item) {
            if ($item->quantity !== null && $item->unit_price !== null) {
                $item->line_total = $item->quantity * $item->unit_price;
            }
        });
    }
}
