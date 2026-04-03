<?php

namespace App\Services;

use App\Models\BusinessDay;
use App\Models\BusinessDaySetting;
use App\Models\DayShift;
use App\Models\Hotel;
use App\Models\ShiftLog;
use App\Models\ShiftTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Business Day Engine (mandatory for POS).
 * - Only ONE open business day allowed.
 * - All "current" time uses hotel timezone so business day follows local (hotel) time.
 */
class BusinessDayService
{
    /**
     * Current moment in the hotel's timezone (for business date and display).
     */
    public static function getNowInHotelTz(): Carbon
    {
        $hotel = Hotel::getHotel();
        return Carbon::now($hotel->getTimezone());
    }

    /**
     * Get day start time (server-side only). From business_day_settings or hotel fallback.
     */
    public static function getDayStartTime(): string
    {
        $hotel = Hotel::getHotel();
        $settings = BusinessDaySetting::where('hotel_id', $hotel->id)->first();
        if ($settings && $settings->day_start_time) {
            return is_string($settings->day_start_time)
                ? $settings->day_start_time
                : $settings->day_start_time->format('H:i:s');
        }
        return $hotel->business_day_rollover_time?->format('H:i:s') ?? '03:00:00';
    }

    /**
     * Calculate logical business date from hotel local time (timezone-aware).
     */
    public static function getLogicalDate(?Carbon $now = null): string
    {
        $now = $now ?? self::getNowInHotelTz();
        return BusinessDay::calculateBusinessDate($now->toDateTime(), self::getDayStartTime());
    }

    /**
     * Get the current OPEN business day (if any). Returns null if none or all closed.
     * If an open business day exists but its date is before the current logical date
     * (rollover has passed), it is auto-closed and null is returned so the manager
     * must open the new day.
     */
    public static function getOpenBusinessDay(): ?BusinessDay
    {
        $hotel = Hotel::getHotel();

        $query = BusinessDay::where('status', 'OPEN')
            ->whereNull('closed_at')
            ->orderByDesc('business_date');

        // Scope by hotel when available (hotel app). For legacy/console contexts where no hotel
        // is resolved, fall back to system-wide behaviour.
        if ($hotel) {
            $query->where('hotel_id', $hotel->id);
        }

        $open = $query->first();

        if (!$open) {
            return null;
        }

        $currentLogicalDate = self::getLogicalDate();
        if ($open->business_date->format('Y-m-d') < $currentLogicalDate) {
            // Rollover has passed; this business day should be closed
            self::autoCloseStaleBusinessDay($open);
            return null;
        }

        return $open;
    }

    /**
     * Auto-close a business day that is in the past (past rollover).
     * Closes all POS sessions for that day and marks the business day CLOSED.
     * Ensures closed_at is never before opened_at (data integrity).
     */
    public static function autoCloseStaleBusinessDay(BusinessDay $businessDay): void
    {
        \App\Services\PosSessionService::closeSessionsForBusinessDay($businessDay->id);
        $closedAt = now();
        if ($businessDay->opened_at && $closedAt->lt($businessDay->opened_at)) {
            $closedAt = $businessDay->opened_at->copy();
        }
        $businessDay->update([
            'closed_at' => $closedAt,
            'closed_by' => null,
            'status' => 'CLOSED',
        ]);
    }

    /**
     * Auto-close any open shifts (legacy ShiftLogs and DayShifts) from previous business dates.
     * Ensures no "past active shifts" when opening or ensuring a new business day.
     */
    public static function closeStaleShiftsBeforeNewDay(string $logicalDateForNewDay): array
    {
        $closed = ['shift_logs' => 0, 'day_shifts' => 0, 'pos_sessions' => 0];

        // Legacy: close open shift_logs for business_date < logicalDateForNewDay
        $staleLogs = ShiftLog::where('business_date', '<', $logicalDateForNewDay)
            ->where(function ($q) {
                $q->whereNull('closed_at')->orWhere('is_locked', false);
            })
            ->get();
        foreach ($staleLogs as $log) {
            $log->update(['closed_at' => now(), 'close_type' => 'auto', 'is_locked' => true]);
            $closed['pos_sessions'] += \App\Services\PosSessionService::closeSessionsForShiftLog($log->id);
            $closed['shift_logs']++;
        }

        // New flow: close open day_shifts whose business_day is before the new day
        $staleDayShifts = DayShift::where('status', 'OPEN')
            ->whereHas('businessDay', fn ($q) => $q->where('business_date', '<', $logicalDateForNewDay))
            ->get();
        foreach ($staleDayShifts as $ds) {
            $ds->update(['status' => 'CLOSED', 'closed_by' => null]);
            $closed['pos_sessions'] += \App\Services\PosSessionService::closeSessionsForDayShift($ds->id);
            $closed['day_shifts']++;
        }

        return $closed;
    }

    /**
     * When switching to NO_SHIFT, close all open legacy shift logs and open day shifts (and their POS sessions).
     */
    public static function closeAllOpenShiftsWhenSwitchingToNoShift(): array
    {
        $closed = ['shift_logs' => 0, 'day_shifts' => 0, 'pos_sessions' => 0];

        $staleLogs = ShiftLog::whereNull('closed_at')->orWhere('is_locked', false)->get();
        foreach ($staleLogs as $log) {
            $log->update(['closed_at' => now(), 'close_type' => 'auto', 'is_locked' => true]);
            $closed['pos_sessions'] += \App\Services\PosSessionService::closeSessionsForShiftLog($log->id);
            $closed['shift_logs']++;
        }

        $openDayShifts = DayShift::where('status', 'OPEN')->get();
        foreach ($openDayShifts as $ds) {
            $ds->update(['status' => 'CLOSED']);
            $closed['pos_sessions'] += \App\Services\PosSessionService::closeSessionsForDayShift($ds->id);
            $closed['day_shifts']++;
        }

        return $closed;
    }

    /**
     * Ensure one open business day exists for the logical date (e.g. on first POS request or cron).
     * Creates only if no OPEN business day exists. Auto-closes stale shifts from previous dates.
     */
    public static function ensureOneOpenBusinessDay(): ?BusinessDay
    {
        $open = self::getOpenBusinessDay();
        if ($open) {
            return $open;
        }

        $logicalDate = self::getLogicalDate();
        $hotel = Hotel::getHotel();

        $existingQuery = BusinessDay::where('business_date', $logicalDate);
        if ($hotel) {
            $existingQuery->where('hotel_id', $hotel->id);
        }
        $existing = $existingQuery->first();
        if ($existing) {
            if ($existing->status === 'CLOSED') {
                return null;
            }
            return $existing;
        }

        self::closeStaleShiftsBeforeNewDay($logicalDate);

        return DB::transaction(function () use ($logicalDate, $hotel) {
            $nowHotel = self::getNowInHotelTz();
            $businessDay = BusinessDay::create([
                'hotel_id' => $hotel?->id,
                'business_date' => $logicalDate,
                'calendar_date_start' => $nowHotel->format('Y-m-d'),
                'opened_at' => now(),
                'status' => 'OPEN',
            ]);

            $hotel = Hotel::getHotel();
            if (!$hotel->isNoShiftMode()) {
                self::createDayShiftsForBusinessDay($businessDay);
            }

            return $businessDay;
        });
    }

    /**
     * Create day shift instances from templates when business day opens.
     */
    public static function createDayShiftsForBusinessDay(BusinessDay $businessDay): void
    {
        $templates = ShiftTemplate::active()->orderBy('display_order')->orderBy('start_time')->get();
        $baseDate = Carbon::parse($businessDay->business_date);

        foreach ($templates as $tpl) {
            $startTime = is_string($tpl->start_time) ? $tpl->start_time : $tpl->start_time->format('H:i:s');
            $endTime = is_string($tpl->end_time) ? $tpl->end_time : $tpl->end_time->format('H:i:s');

            $startAt = $baseDate->copy()->setTimeFromTimeString($startTime);
            $endAt = $baseDate->copy()->setTimeFromTimeString($endTime);
            if ($endTime < $startTime) {
                $endAt->addDay();
            }

            DayShift::create([
                'business_day_id' => $businessDay->id,
                'shift_template_id' => $tpl->id,
                'name' => $tpl->name,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'PENDING',
            ]);
        }
    }

    /**
     * Open a business day (Admin/Manager). Only one open at a time.
     */
    public static function openBusinessDay(int $openedBy): BusinessDay
    {
        if (self::getOpenBusinessDay()) {
            throw new \RuntimeException('Another business day is already open. Close it first.');
        }

        $logicalDate = self::getLogicalDate();
        $hotel = Hotel::getHotel();

        $existingQuery = BusinessDay::where('business_date', $logicalDate);
        if ($hotel) {
            $existingQuery->where('hotel_id', $hotel->id);
        }
        $existing = $existingQuery->first();
        if ($existing) {
            if ($existing->status === 'CLOSED') {
                throw new \RuntimeException('Cannot reopen a closed business day.');
            }
            return $existing;
        }

        $closed = self::closeStaleShiftsBeforeNewDay($logicalDate);
        $totalStale = $closed['shift_logs'] + $closed['day_shifts'];
        if ($totalStale > 0 && session()->isStarted()) {
            session()->flash('message', 'Business day opened. ' . $totalStale . ' previous open shift(s) were closed automatically.');
        }

        return DB::transaction(function () use ($logicalDate, $openedBy, $hotel) {
            $nowHotel = self::getNowInHotelTz();
            $businessDay = BusinessDay::create([
                'hotel_id' => $hotel?->id,
                'business_date' => $logicalDate,
                'calendar_date_start' => $nowHotel->format('Y-m-d'),
                'opened_at' => now(),
                'opened_by' => $openedBy,
                'status' => 'OPEN',
            ]);

            $hotel = Hotel::getHotel();
            if (!$hotel->isNoShiftMode()) {
                self::createDayShiftsForBusinessDay($businessDay);
            }

            return $businessDay;
        });
    }

    /**
     * Close the open business day (Admin/Manager). No reopening.
     * Ensures closed_at is never before opened_at.
     */
    public static function closeBusinessDay(int $closedBy): void
    {
        $open = self::getOpenBusinessDay();
        if (!$open) {
            throw new \RuntimeException('No open business day to close.');
        }

        $closedAt = now();
        if ($open->opened_at && $closedAt->lt($open->opened_at)) {
            $closedAt = $open->opened_at->copy();
        }
        $open->update([
            'closed_at' => $closedAt,
            'closed_by' => $closedBy,
            'status' => 'CLOSED',
        ]);
    }
}
