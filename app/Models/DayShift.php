<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayShift extends Model
{
    protected $fillable = [
        'business_day_id',
        'shift_template_id',
        'name',
        'start_at',
        'end_at',
        'opened_by',
        'closed_by',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function businessDay(): BelongsTo
    {
        return $this->belongsTo(BusinessDay::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'day_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isClosed(): bool
    {
        return $this->status === 'CLOSED';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }
}
