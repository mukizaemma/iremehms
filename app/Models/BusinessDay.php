<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'business_date',
        'calendar_date_start',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'calendar_date_start' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Hotel this business day belongs to
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * User who opened the business day
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * User who closed the business day
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get shift logs for this business day (legacy)
     */
    public function shiftLogs(): HasMany
    {
        return $this->hasMany(ShiftLog::class, 'business_date', 'business_date');
    }

    /**
     * Day shifts (instances) for this business day
     */
    public function dayShifts(): HasMany
    {
        return $this->hasMany(DayShift::class);
    }

    /**
     * POS sessions for this business day
     */
    public function posSessions(): HasMany
    {
        return $this->hasMany(PosSession::class, 'business_day_id');
    }

    /**
     * Check if business day is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'CLOSED' || $this->closed_at !== null;
    }

    /**
     * Check if business day is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'OPEN' && $this->closed_at === null;
    }

    /**
     * Get or create business day for a given date
     */
    public static function getOrCreateForDate(\DateTime $date): self
    {
        $businessDate = static::calculateBusinessDate($date);
        
        return static::firstOrCreate(
            ['business_date' => $businessDate],
            [
                'calendar_date_start' => $date->format('Y-m-d'),
                'opened_at' => now(),
            ]
        );
    }

    /**
     * Calculate business date based on rollover time (default 03:00 AM)
     */
    public static function calculateBusinessDate(\DateTime $date, ?string $rolloverTime = '03:00:00'): string
    {
        $currentTime = $date->format('H:i:s');
        
        // Parse rollover time
        $rolloverParts = explode(':', $rolloverTime);
        $rolloverHour = (int)($rolloverParts[0] ?? 3);
        $rolloverMinute = (int)($rolloverParts[1] ?? 0);
        $rolloverSecond = (int)($rolloverParts[2] ?? 0);
        
        // Get current time components
        $currentHour = (int)$date->format('H');
        $currentMinute = (int)$date->format('i');
        $currentSecond = (int)$date->format('s');
        
        // Calculate current time in seconds since midnight
        $currentSeconds = $currentHour * 3600 + $currentMinute * 60 + $currentSecond;
        $rolloverSeconds = $rolloverHour * 3600 + $rolloverMinute * 60 + $rolloverSecond;
        
        // If current time is before rollover, business date is previous calendar date
        if ($currentSeconds < $rolloverSeconds) {
            $businessDate = clone $date;
            $businessDate->modify('-1 day');
            return $businessDate->format('Y-m-d');
        }
        
        // Otherwise, business date is current calendar date
        return $date->format('Y-m-d');
    }
}
