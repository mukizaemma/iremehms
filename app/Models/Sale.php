<?php

namespace App\Models;

use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'department_id',
        'total_amount',
        'discount',
        'tax',
        'final_amount',
        'payment_method',
        'notes',
        'sale_date',
        'business_date',
        'shift_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'sale_date' => 'datetime',
    ];

    /**
     * User who made the sale
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Department this sale belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Override update to check if shift is locked
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (!$this->canBeEdited()) {
            throw new \Exception('Cannot edit sale transaction: Shift is locked.');
        }
        
        return parent::update($attributes, $options);
    }

    /**
     * Override delete to check if shift is locked
     */
    public function delete()
    {
        if (!$this->canBeDeleted()) {
            throw new \Exception('Cannot delete sale transaction: Shift is locked.');
        }
        
        return parent::delete();
    }
}
