<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'requisition_id',
        'user_id',
        'body',
    ];

    /**
     * Requisition this comment belongs to
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * User who wrote this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
