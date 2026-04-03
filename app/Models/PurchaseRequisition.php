<?php

namespace App\Models;

use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseRequisition extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected $primaryKey = 'requisition_id';

    protected $fillable = [
        'supplier_id',
        'requested_by',
        'department_id',
        'status',
        'business_date',
        'shift_id',
        'total_estimated_cost',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'total_estimated_cost' => 'decimal:2',
    ];

    /**
     * Supplier for this requisition
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * User who requested this requisition
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Department this requisition belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Items in this requisition
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id');
    }

    /**
     * Comments on this requisition (any status)
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionComment::class, 'requisition_id')->orderBy('created_at');
    }

    /**
     * Approval for this requisition (if approved)
     */
    public function approval(): HasOne
    {
        return $this->hasOne(PurchaseApproval::class, 'requisition_id');
    }

    /**
     * Goods receipts created from this requisition
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'requisition_id');
    }

    /**
     * Boot the model – purchase_requisitions uses requested_by, not user_id.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            // Trait sets user_id but this table has requested_by instead; remove it so INSERT doesn't fail
            $model->offsetUnset('user_id');
        });
    }

    /**
     * Check if requisition can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['DRAFT', 'SUBMITTED']);
    }

    /**
     * Check if requisition is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * Override update to prevent editing approved/rejected requisitions
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->canBeEdited()) {
            throw new \Exception('Cannot edit requisition: Status is ' . $this->status . '. Only DRAFT and SUBMITTED requisitions can be edited.');
        }
        
        return parent::update($attributes, $options);
    }
}
