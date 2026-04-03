<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'business_date',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'open_type',
        'close_type',
        'opening_cash',
        'closing_cash',
        'notes',
        'is_locked',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'is_locked' => 'boolean',
        'reopened_at' => 'datetime',
    ];

    /**
     * The shift this log belongs to
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * User who opened the shift
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * User who closed the shift
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * User who reopened the shift
     */
    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Check if shift is closed
     */
    public function isClosed(): bool
    {
        return $this->closed_at !== null && $this->is_locked;
    }

    /**
     * Check if shift is open
     */
    public function isOpen(): bool
    {
        return $this->closed_at === null || !$this->is_locked;
    }

    /**
     * POS sessions opened under this shift log
     */
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'shift_log_id');
    }
}
