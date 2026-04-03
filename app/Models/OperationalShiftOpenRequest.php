<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalShiftOpenRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'hotel_id',
        'requested_by',
        'module_scope',
        'note',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
