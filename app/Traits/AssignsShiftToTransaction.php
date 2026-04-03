<?php

namespace App\Traits;

use App\Services\TimeAndShiftResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that represent transactions
 * 
 * Ensures every transaction has:
 * - business_date (NOT NULL)
 * - shift_id (NOT NULL)
 * - timestamp (server-generated)
 * - user_id (from authenticated user)
 * 
 * This trait MUST be used by all transaction models (Sales, Expenses, Stocks, etc.)
 */
trait AssignsShiftToTransaction
{
    /**
     * Boot the trait
     */
    protected static function bootAssignsShiftToTransaction()
    {
        // Automatically assign shift and business date before creating
        static::creating(function ($model) {
            if (empty($model->business_date) || empty($model->shift_id)) {
                $resolved = TimeAndShiftResolver::resolve();
                
                $model->business_date = $resolved['business_date'];
                $model->shift_id = $resolved['shift_id'];
            }
            
            // Ensure user_id is set if not provided
            if (empty($model->user_id) && Auth::check()) {
                $model->user_id = Auth::id();
            }
            
            // Ensure timestamp is set (use created_at if available)
            if (empty($model->timestamp) && property_exists($model, 'created_at')) {
                $model->timestamp = now();
            }
        });
    }

    /**
     * Check if this transaction can be edited
     * Transactions in locked shifts cannot be edited
     */
    public function canBeEdited(): bool
    {
        if (empty($this->shift_id) || empty($this->business_date)) {
            return false;
        }
        
        return !TimeAndShiftResolver::isShiftLocked($this->shift_id, $this->business_date);
    }

    /**
     * Check if this transaction can be deleted
     * Transactions in locked shifts cannot be deleted
     */
    public function canBeDeleted(): bool
    {
        return $this->canBeEdited();
    }

    /**
     * Get the shift for this transaction
     */
    public function shift()
    {
        return $this->belongsTo(\App\Models\Shift::class);
    }
}
