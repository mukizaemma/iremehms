<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'is_active',
        'is_system_generated',
        'description',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system_generated' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get shift logs for this shift
     */
    public function shiftLogs(): HasMany
    {
        return $this->hasMany(ShiftLog::class);
    }

    /**
     * Check if a given time falls within this shift's time window
     * Handles shifts that span midnight
     */
    public function isTimeInShift(\DateTime $time): bool
    {
        $timeOnly = $time->format('H:i:s');
        
        // Get start and end times as strings
        $start = $this->getRawOriginal('start_time') ?? $this->attributes['start_time'] ?? '00:00:00';
        $end = $this->getRawOriginal('end_time') ?? $this->attributes['end_time'] ?? '23:59:59';
        
        // If shift spans midnight (end < start)
        if ($end < $start) {
            return $timeOnly >= $start || $timeOnly <= $end;
        }

        // Normal shift (start < end)
        return $timeOnly >= $start && $timeOnly <= $end;
    }

    /**
     * Get active shifts only
     */
    public static function active()
    {
        return static::where('is_active', true)
            ->where('is_system_generated', false)
            ->orderBy('order')
            ->orderBy('start_time');
    }

    /**
     * Create or get system-generated shift for a business date
     */
    public static function getSystemShiftForDate(\DateTime $businessDate): self
    {
        $code = 'SYSTEM_SHIFT_' . $businessDate->format('Y-m-d');
        
        return static::firstOrCreate(
            ['code' => $code],
            [
                'name' => 'System Shift - ' . $businessDate->format('Y-m-d'),
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'is_active' => true,
                'is_system_generated' => true,
                'order' => 999,
            ]
        );
    }

}
