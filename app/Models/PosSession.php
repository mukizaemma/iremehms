<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    protected $fillable = [
        'business_day_id',
        'day_shift_id',
        'operational_shift_id',
        'shift_log_id',
        'user_id',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function businessDay(): BelongsTo
    {
        return $this->belongsTo(BusinessDay::class);
    }

    public function dayShift(): BelongsTo
    {
        return $this->belongsTo(DayShift::class, 'day_shift_id');
    }

    public function operationalShift(): BelongsTo
    {
        return $this->belongsTo(OperationalShift::class, 'operational_shift_id');
    }

    /** @deprecated Legacy: use businessDay/dayShift */
    public function shiftLog(): BelongsTo
    {
        return $this->belongsTo(ShiftLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public static function getOpenForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->where('status', 'OPEN')
            ->first();
    }
}
