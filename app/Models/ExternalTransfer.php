<?php

namespace App\Models;

use App\Traits\AssignsShiftToTransaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalTransfer extends Model
{
    use HasFactory, AssignsShiftToTransaction;

    protected $fillable = [
        'substock_id',
        'transfer_type',
        'recipient_name',
        'recipient_details',
        'items',
        'total_amount',
        'notes',
        'user_id',
        'shift_id',
        'business_date',
        'transfer_date',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'business_date' => 'date',
        'transfer_date' => 'datetime',
    ];

    /**
     * The substock this transfer is from
     */
    public function substock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'substock_id');
    }

    /**
     * User who created this transfer
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shift this transfer belongs to
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
