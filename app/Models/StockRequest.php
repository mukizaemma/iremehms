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
            self::TYPE_TRANSFER_SUBSTOCK => 'Stock transfer (main → sub-location)',
            self::TYPE_TRANSFER_DEPARTMENT => 'Stock transfer (department)',
            self::TYPE_ISSUE_DEPARTMENT => 'Issue from main stock (department)',
            self::TYPE_ISSUE_BAR_RESTAURANT => 'Issue from main stock (Bar & Restaurant)',
            self::TYPE_ITEM_EDIT => 'Master data: item edit',
        ];
    }

    /** @return 'transfer'|'issue'|'master_data' */
    public static function typeGroup(string $type): string
    {
        return match ($type) {
            self::TYPE_TRANSFER_SUBSTOCK, self::TYPE_TRANSFER_DEPARTMENT => 'transfer',
            self::TYPE_ISSUE_DEPARTMENT, self::TYPE_ISSUE_BAR_RESTAURANT => 'issue',
            self::TYPE_ITEM_EDIT => 'master_data',
            default => 'other',
        };
    }

    public function getTypeGroup(): string
    {
        return self::typeGroup($this->type);
    }

    /**
     * Whether this request is a Bar & Restaurant requisition (approved by Super Admin or approve_bar_restaurant_requisitions).
     */
    public function isBarRestaurantRequisition(): bool
    {
        return $this->type === self::TYPE_ISSUE_BAR_RESTAURANT;
    }

    /**
     * Where the request sits after approval (inventory still moves only after Stock Out issue).
     *
     * @return 'pending_approval'|'rejected'|'awaiting_issue'|'awaiting_purchase'|'partial_issue'|'complete'|'in_progress'
     */
    public function fulfillmentPhase(): string
    {
        if ($this->isPending()) {
            return 'pending_approval';
        }
        if ($this->isRejected()) {
            return 'rejected';
        }
        if (! $this->isApproved()) {
            return 'pending_approval';
        }

        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        if ($items->isEmpty()) {
            return 'awaiting_issue';
        }

        $total = $items->count();
        $fullyIssued = 0;
        $onRequisition = 0;
        $needsIssue = 0;

        foreach ($items as $item) {
            if ($item->isOnRequisition()) {
                $onRequisition++;

                continue;
            }
            $qty = (float) $item->quantity;
            $issued = (float) ($item->quantity_issued ?? 0);
            if ($issued + 0.0001 >= $qty) {
                $fullyIssued++;
            } elseif ($issued > 0) {
                $needsIssue++;

                continue;
            } else {
                $needsIssue++;
            }
        }

        if ($fullyIssued === $total) {
            return 'complete';
        }
        if ($onRequisition === $total) {
            return 'awaiting_purchase';
        }
        if ($needsIssue === $total && $fullyIssued === 0 && $onRequisition === 0) {
            return 'awaiting_issue';
        }
        if ($fullyIssued > 0 && $fullyIssued < $total) {
            return 'partial_issue';
        }

        return 'in_progress';
    }

    public function fulfillmentLabel(): string
    {
        return match ($this->fulfillmentPhase()) {
            'pending_approval' => 'Awaiting approval',
            'rejected' => 'Rejected',
            'awaiting_issue' => 'Approved — awaiting issue (Stock out)',
            'awaiting_purchase' => 'Awaiting purchase / receipt',
            'partial_issue' => 'Partially issued',
            'complete' => 'Fully issued',
            'in_progress' => 'In progress',
            default => '—',
        };
    }

    public function fulfillmentBadgeClass(): string
    {
        return match ($this->fulfillmentPhase()) {
            'pending_approval' => 'warning',
            'rejected' => 'danger',
            'awaiting_issue' => 'primary',
            'awaiting_purchase' => 'info',
            'partial_issue' => 'secondary',
            'complete' => 'success',
            'in_progress' => 'secondary',
            default => 'secondary',
        };
    }
}
