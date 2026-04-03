<?php

namespace App\Services;

use App\Models\DayShift;
use App\Models\PosSession;
use App\Models\ShiftLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * POS Session enforcement (user-level).
 * - Legacy / day-shift flow: POS ties to an open business day (and optionally day shift).
 * - Operational shifts (when enabled): POS does not require a business day — only an open
 *   POS (or global) operational shift; sessions may have null business_day_id.
 * - STRICT_SHIFT (legacy): require OPEN day shift when not using operational shifts.
 * - NO_SHIFT / OPTIONAL_SHIFT: shift_id optional (legacy).
 */
class PosSessionService
{
    /**
     * Check if POS can operate: when using operational shifts, only an open operational shift
     * is required (no business day). Otherwise: open business day; if STRICT_SHIFT an open shift exists.
     */
    public static function canOpenSession(): bool
    {
        $hotel = \App\Models\Hotel::getHotel();

        if (self::usesNewShiftFlow() && $hotel && OperationalShiftActionGate::requiresOperationalShiftForPos($hotel)) {
            if (! OperationalShiftService::hasOpenShiftForPos($hotel)) {
                return false;
            }
            $op = OperationalShiftService::getOpenShiftForPos($hotel);
            if ($op) {
                try {
                    OperationalShiftActionGate::assertLongShiftAcknowledgedIfNeeded($op);
                } catch (\RuntimeException) {
                    return false;
                }
            }

            return true;
        }

        $businessDay = BusinessDayService::getOpenBusinessDay();
        if (! $businessDay) {
            $businessDay = BusinessDayService::ensureOneOpenBusinessDay();
        }
        if (! $businessDay || $businessDay->status !== 'OPEN') {
            return false;
        }

        if (self::usesNewShiftFlow()) {
            if ($hotel && $hotel->isStrictShiftMode()) {
                $openShift = DayShiftService::getOpenShiftForBusinessDay($businessDay->id);

                return $openShift !== null;
            }
        }

        return true;
    }

    /**
     * Open business day for display / legacy flows. May auto-create an open day when none exists.
     */
    public static function getOpenBusinessDay(): ?\App\Models\BusinessDay
    {
        $open = BusinessDayService::getOpenBusinessDay();
        if ($open) {
            return $open;
        }

        return BusinessDayService::ensureOneOpenBusinessDay();
    }

    /**
     * Current open business day without creating one — for UI when operational shifts replace
     * the business-day requirement for POS.
     */
    public static function getOpenBusinessDayWithoutEnsure(): ?\App\Models\BusinessDay
    {
        return BusinessDayService::getOpenBusinessDay();
    }

    /**
     * Get open day shift for current business day (new flow). Null if NO_SHIFT or no open shift.
     */
    public static function getOpenDayShift(): ?DayShift
    {
        $businessDay = self::getOpenBusinessDay();
        if (!$businessDay) {
            return null;
        }
        $hotel = \App\Models\Hotel::getHotel();
        if ($hotel->isNoShiftMode()) {
            return null;
        }
        return DayShiftService::getOpenShiftForBusinessDay($businessDay->id);
    }

    /**
     * Legacy: Check if there is an open shift (ShiftLog) for current business date.
     */
    public static function hasOpenShift(): bool
    {
        if (self::usesNewShiftFlow()) {
            $hotel = \App\Models\Hotel::getHotel();
            if ($hotel && OperationalShiftActionGate::requiresOperationalShiftForPos($hotel)) {
                return OperationalShiftService::hasOpenShiftForPos($hotel);
            }
            if ($hotel && $hotel->isStrictShiftMode()) {
                return self::getOpenDayShift() !== null;
            }

            return true;
        }
        $businessDate = TimeAndShiftResolver::getCurrentBusinessDate();
        return ShiftLog::where('business_date', $businessDate)
            ->where(function ($q) {
                $q->whereNull('closed_at')->orWhere('is_locked', false);
            })
            ->exists();
    }

    /**
     * Legacy: Get the current open shift log for POS. For new flow returns null; use getOpenDayShift().
     */
    public static function getOpenShiftLog(): ?ShiftLog
    {
        if (self::usesNewShiftFlow()) {
            return null;
        }
        $businessDate = TimeAndShiftResolver::getCurrentBusinessDate();
        return ShiftLog::where('business_date', $businessDate)
            ->where(function ($q) {
                $q->whereNull('closed_at')->orWhere('is_locked', false);
            })
            ->with('shift')
            ->first();
    }

    /**
     * Open a POS session for the current user. One active session per user.
     */
    public static function openSession(): PosSession
    {
        $hotel = \App\Models\Hotel::getHotel();
        $dayShiftId = null;
        $operationalShiftId = null;
        $businessDay = null;

        $operationalOnly = self::usesNewShiftFlow()
            && $hotel
            && OperationalShiftActionGate::requiresOperationalShiftForPos($hotel);

        if ($operationalOnly) {
            $op = OperationalShiftService::getOpenShiftForPos($hotel);
            if (! $op) {
                throw new \RuntimeException('Cannot open POS session: no operational shift is open. Open a shift in Shift management first.');
            }
            OperationalShiftActionGate::assertLongShiftAcknowledgedIfNeeded($op);
            $operationalShiftId = $op->id;
            $dayShiftId = null;
            // Optional link for reporting if a business day happens to be open; not required.
            $businessDay = BusinessDayService::getOpenBusinessDay();
        } else {
            $businessDay = BusinessDayService::getOpenBusinessDay();
            if (! $businessDay) {
                $businessDay = BusinessDayService::ensureOneOpenBusinessDay();
            }
            if (! $businessDay || $businessDay->status !== 'OPEN') {
                throw new \RuntimeException('Cannot open POS session: no business day is open.');
            }

            if (self::usesNewShiftFlow()) {
                if ($hotel->isStrictShiftMode()) {
                    $openShift = DayShiftService::getOpenShiftForBusinessDay($businessDay->id);
                    if (! $openShift) {
                        throw new \RuntimeException('Cannot open POS session: no shift is currently open. Open a shift first.');
                    }
                    $dayShiftId = $openShift->id;
                }
            } else {
                $shiftLog = self::getOpenShiftLog();
                if (! $shiftLog) {
                    throw new \RuntimeException('Cannot open POS session: no shift is currently open.');
                }
            }
        }

        $userId = Auth::id();
        $existing = PosSession::getOpenForUser($userId);
        if ($existing) {
            return $existing;
        }

        if (self::usesNewShiftFlow()) {
            $data = [
                'business_day_id' => $businessDay?->id,
                'day_shift_id' => $dayShiftId,
                'user_id' => $userId,
                'opened_at' => now(),
                'status' => 'OPEN',
            ];
            if (Schema::hasColumn('pos_sessions', 'operational_shift_id')) {
                $data['operational_shift_id'] = $operationalShiftId;
            }

            return PosSession::create($data);
        }

        $shiftLog = self::getOpenShiftLog();
        return PosSession::create([
            'shift_log_id' => $shiftLog->id,
            'user_id' => $userId,
            'opened_at' => now(),
            'status' => 'OPEN',
        ]);
    }

    /**
     * Close the current user's POS session.
     */
    public static function closeSession(): void
    {
        $session = PosSession::getOpenForUser(Auth::id());
        if ($session) {
            $session->update([
                'closed_at' => now(),
                'status' => 'CLOSED',
            ]);
        }
    }

    /**
     * When a shift is closed, close all POS sessions for that shift log (legacy).
     */
    public static function closeSessionsForShiftLog(int $shiftLogId): int
    {
        return PosSession::where('shift_log_id', $shiftLogId)
            ->where('status', 'OPEN')
            ->update([
                'closed_at' => now(),
                'status' => 'CLOSED',
            ]);
    }

    /**
     * When a day shift is closed, close all POS sessions for that shift.
     */
    public static function closeSessionsForDayShift(int $dayShiftId): int
    {
        return PosSession::where('day_shift_id', $dayShiftId)
            ->where('status', 'OPEN')
            ->update([
                'closed_at' => now(),
                'status' => 'CLOSED',
            ]);
    }

    /**
     * When a business day is auto-closed (e.g. after rollover), close all POS sessions for that day.
     */
    public static function closeSessionsForBusinessDay(int $businessDayId): int
    {
        if (!self::usesNewShiftFlow()) {
            return 0;
        }
        return PosSession::where('business_day_id', $businessDayId)
            ->where('status', 'OPEN')
            ->update([
                'closed_at' => now(),
                'status' => 'CLOSED',
            ]);
    }

    private static function usesNewShiftFlow(): bool
    {
        return Schema::hasColumn('pos_sessions', 'business_day_id');
    }
}
