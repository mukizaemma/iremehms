<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalShift extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_POS = 'pos';

    public const SCOPE_FRONT_OFFICE = 'front-office';

    public const SCOPE_STORE = 'store';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'hotel_id',
        'module_scope',
        'reference_date',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'open_note',
        'close_comment',
        'status',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
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
        return $this->hasMany(PosSession::class, 'operational_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function durationHours(): float
    {
        $end = $this->closed_at ?? now();

        return $this->opened_at->diffInMinutes($end) / 60;
    }
}
