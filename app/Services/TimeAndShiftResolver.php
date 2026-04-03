<?php

namespace App\Services;

use App\Models\BusinessDay;
use App\Models\Hotel;
use App\Services\BusinessDayService;
use App\Models\Shift;
use App\Models\ShiftLog;
use App\Services\PosSessionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Time & Shift Resolver
 * 
 * This service is the single source of truth for:
 * - Business date calculation
 * - Shift assignment
 * - System shift fallback
 * 
 * All modules MUST use this service for transaction accountability.
 */
class TimeAndShiftResolver
{
    /**
     * Resolve business date and shift for a given timestamp
     * 
     * @param \DateTime|null $timestamp If null, uses current time
     * @return array ['business_date' => string, 'shift_id' => int, 'shift' => Shift]
     * @throws \Exception
     */
    public static function resolve(\DateTime $timestamp = null): array
    {
        $hotel = Hotel::getHotel();
        $timestamp = $timestamp ? Carbon::instance($timestamp) : Carbon::now($hotel->getTimezone());
        
        // 1. Calculate business date (system-controlled, cannot be disabled)
        $businessDate = BusinessDay::calculateBusinessDate(
            $timestamp->toDateTime(),
            $hotel->business_day_rollover_time?->format('H:i:s') ?? '03:00:00'
        );
        
        // Ensure business day record exists
        BusinessDay::getOrCreateForDate($timestamp->toDateTime());
        
        // 2. Resolve shift assignment
        $shift = static::resolveShift($timestamp, $businessDate, $hotel);
        
        // 3. Ensure shift log exists and is open
        static::ensureShiftLog($shift, $businessDate, $timestamp);
        
        return [
            'business_date' => $businessDate,
            'shift_id' => $shift->id,
            'shift' => $shift,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Resolve which shift should be assigned for a given timestamp
     * 
     * @param Carbon $timestamp
     * @param string $businessDate
     * @param Hotel $hotel
     * @return Shift
     */
    protected static function resolveShift(Carbon $timestamp, string $businessDate, Hotel $hotel): Shift
    {
        // If shifts are disabled, use system shift
        if (!$hotel->shifts_enabled) {
            return Shift::getSystemShiftForDate(Carbon::parse($businessDate));
        }
        
        // Find active shift that matches the timestamp
        $activeShifts = Shift::active()->get();
        
        foreach ($activeShifts as $shift) {
            if ($shift->isTimeInShift($timestamp->toDateTime())) {
                return $shift;
            }
        }
        
        // No matching shift found - fallback to system shift
        // This ensures NO transaction is unassigned
        Log::warning("No active shift found for time {$timestamp->format('H:i:s')}, using system shift", [
            'timestamp' => $timestamp->toDateTimeString(),
            'business_date' => $businessDate,
        ]);
        
        return Shift::getSystemShiftForDate(Carbon::parse($businessDate));
    }

    /**
     * Ensure shift log exists and is open for the given shift and business date
     * 
     * @param Shift $shift
     * @param string $businessDate
     * @param Carbon $timestamp
     * @return ShiftLog
     */
    protected static function ensureShiftLog(Shift $shift, string $businessDate, Carbon $timestamp): ShiftLog
    {
        // Check if shift log already exists
        $shiftLog = ShiftLog::where('shift_id', $shift->id)
            ->where('business_date', $businessDate)
            ->first();
        
        if ($shiftLog) {
            // If shift is closed, we still return it (transactions can't be added, but we have accountability)
            return $shiftLog;
        }
        
        // Create new shift log (auto-opened)
        $shiftLog = ShiftLog::create([
            'shift_id' => $shift->id,
            'business_date' => $businessDate,
            'opened_at' => $timestamp,
            'open_type' => 'auto',
            'is_locked' => false,
        ]);
        
        Log::info("Shift log auto-opened", [
            'shift_id' => $shift->id,
            'shift_name' => $shift->name,
            'business_date' => $businessDate,
        ]);
        
        return $shiftLog;
    }

    /**
     * Get current business date (uses hotel timezone).
     *
     * @return string Y-m-d format
     */
    public static function getCurrentBusinessDate(): string
    {
        return BusinessDayService::getLogicalDate();
    }

    /**
     * Get current shift
     * 
     * @return Shift
     */
    public static function getCurrentShift(): Shift
    {
        $resolved = static::resolve();
        return $resolved['shift'];
    }

    /**
     * Check if a shift is locked (closed and transactions cannot be modified)
     * 
     * @param int $shiftId
     * @param string $businessDate
     * @return bool
     */
    public static function isShiftLocked(int $shiftId, string $businessDate): bool
    {
        $shiftLog = ShiftLog::where('shift_id', $shiftId)
            ->where('business_date', $businessDate)
            ->first();
        
        return $shiftLog && $shiftLog->isClosed();
    }

    /**
     * Auto-close shifts that have passed their end time
     * This should be called periodically (e.g., via scheduled task)
     * 
     * @return int Number of shifts closed
     */
    public static function autoCloseExpiredShifts(): int
    {
        $hotel = Hotel::getHotel();
        $now = Carbon::now($hotel->getTimezone());
        $businessDate = static::getCurrentBusinessDate();
        $closedCount = 0;
        
        // Get all open shift logs for current and previous business days
        $openShiftLogs = ShiftLog::where('is_locked', false)
            ->whereNull('closed_at')
            ->whereIn('business_date', [
                $businessDate,
                Carbon::parse($businessDate)->subDay()->format('Y-m-d'),
            ])
            ->with('shift')
            ->get();
        
        foreach ($openShiftLogs as $shiftLog) {
            $shift = $shiftLog->shift;
            
            // System shifts are never auto-closed (they cover the full day)
            if ($shift->is_system_generated) {
                continue;
            }
            
            // Check if shift end time has passed
            $endTime = $shift->getRawOriginal('end_time') ?? $shift->attributes['end_time'] ?? '23:59:59';
            $startTime = $shift->getRawOriginal('start_time') ?? $shift->attributes['start_time'] ?? '00:00:00';
            
            $shiftEnd = Carbon::parse($shiftLog->business_date . ' ' . $endTime);
            
            // If shift spans midnight, add a day to end time
            if ($endTime < $startTime) {
                $shiftEnd->addDay();
            }
            
            // Also check if we've passed rollover time (03:00 AM)
            $rolloverTime = Carbon::parse($now->format('Y-m-d') . ' ' . ($hotel->business_day_rollover_time?->format('H:i:s') ?? '03:00:00'));
            
            if ($now->greaterThanOrEqualTo($shiftEnd) || $now->greaterThanOrEqualTo($rolloverTime)) {
                $shiftLog->update([
                    'closed_at' => $now,
                    'close_type' => 'auto',
                    'is_locked' => true,
                ]);
                PosSessionService::closeSessionsForShiftLog($shiftLog->id);
                $closedCount++;
                
                Log::info("Shift auto-closed", [
                    'shift_id' => $shift->id,
                    'shift_name' => $shift->name,
                    'business_date' => $shiftLog->business_date,
                ]);
            }
        }
        
        return $closedCount;
    }
}
