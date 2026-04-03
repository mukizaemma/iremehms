<?php

namespace App\Models;

use App\Helpers\CurrencyHelper;
use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->currency)) {
                $expense->currency = CurrencyHelper::getCurrency();
            }
        });
    }

    protected $fillable = [
        'title',
        'description',
        'amount',
        'currency',
        'user_id', // Keep for backward compatibility
        'created_by',
        'department_id',
        'category_id',
        'supplier_id',
        'payment_method',
        'notes',
        'expense_date',
        'business_date',
        'shift_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    /**
     * Expense category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Supplier (if expense is linked to a supplier)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * User who created the expense (preferred field)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who recorded the expense
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Department this expense belongs to
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
            throw new \Exception('Cannot edit expense transaction: Shift is locked.');
        }
        
        return parent::update($attributes, $options);
    }

    /**
     * Override delete to check if shift is locked
     */
    public function delete()
    {
        if (!$this->canBeDeleted()) {
            throw new \Exception('Cannot delete expense transaction: Shift is locked.');
        }
        
        return parent::delete();
    }
}
