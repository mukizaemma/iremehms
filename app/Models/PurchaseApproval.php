<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseApproval extends Model
{
    use HasFactory;

    protected $primaryKey = 'approval_id';

    protected $fillable = [
        'requisition_id',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /**
     * Requisition this approval belongs to
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * User who approved
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
