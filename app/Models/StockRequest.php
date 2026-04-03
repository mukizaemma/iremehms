<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockRequest extends Model
{
    public const TYPE_TRANSFER_SUBSTOCK = 'transfer_substock';
    public const TYPE_TRANSFER_DEPARTMENT = 'transfer_department';
    public const TYPE_ISSUE_DEPARTMENT = 'issue_department';
    public const TYPE_ISSUE_BAR_RESTAURANT = 'issue_bar_restaurant';
    public const TYPE_ITEM_EDIT = 'item_edit';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'type',
        'status',
        'requested_by_id',
        'approved_by_id',
        'approved_at',
        'rejected_reason',
        'notes',
        'to_stock_location_id',
        'to_department_id',
        'deletion_requested_at',
        'deletion_requested_by_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function toStockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'to_stock_location_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(StockRequestComment::class)->orderBy('created_at');
    }

    public function deletionRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deletion_requested_by_id');
    }

    public function isDeletionRequested(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_TRANSFER_SUBSTOCK => 'Transfer to sub-location',
            self::TYPE_TRANSFER_DEPARTMENT => 'Transfer to department',
            self::TYPE_ISSUE_DEPARTMENT => 'Issue to department',
            self::TYPE_ISSUE_BAR_RESTAURANT => 'Bar & Restaurant (from main stock)',
            self::TYPE_ITEM_EDIT => 'Item edit',
        ];
    }

    /**
     * Whether this request is a Bar & Restaurant requisition (approved by Super Admin or approve_bar_restaurant_requisitions).
     */
    public function isBarRestaurantRequisition(): bool
    {
        return $this->type === self::TYPE_ISSUE_BAR_RESTAURANT;
    }
}
