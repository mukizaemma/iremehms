<?php

namespace App\Services;

use App\Models\DayShift;
use App\Models\Invoice;
use App\Models\PosSession;

/**
 * Shift opening & closing rules (align with POS).
 */
class DayShiftService
{
    /**
     * Open a shift. Block if previous shift in same day is still OPEN.
     */
    public static function openShift(int $dayShiftId, int $userId): DayShift
    {
        $shift = DayShift::with('businessDay')->findOrFail($dayShiftId);
        if ($shift->status !== 'PENDING') {
            throw new \RuntimeException('Shift is not in PENDING status.');
        }

        $previousOpen = DayShift::where('business_day_id', $shift->business_day_id)
            ->where('id', '!=', $shift->id)
            ->where('status', 'OPEN')
            ->exists();
        if ($previousOpen) {
            throw new \RuntimeException('Another shift is still open. Close it first.');
        }

        $shift->update([
            'status' => 'OPEN',
            'opened_by' => $userId,
        ]);

        return $shift;
    }

    /**
     * Close a shift. Block if open POS sessions or unpaid invoices exist for this shift.
     */
    public static function closeShift(int $dayShiftId, int $userId): DayShift
    {
        $shift = DayShift::findOrFail($dayShiftId);
        if ($shift->status !== 'OPEN') {
            throw new \RuntimeException('Shift is not open.');
        }

        $openSessions = PosSession::where('day_shift_id', $dayShiftId)->where('status', 'OPEN')->count();
        if ($openSessions > 0) {
            throw new \RuntimeException('Cannot close shift: ' . $openSessions . ' POS session(s) still open. Close them first.');
        }

        $orderIds = \App\Models\Order::whereIn('session_id', $shift->posSessions()->pluck('id'))->pluck('id');
        $unpaidInvoices = Invoice::whereIn('order_id', $orderIds)->where('invoice_status', 'UNPAID')->count();
        if ($unpaidInvoices > 0) {
            throw new \RuntimeException('Cannot close shift: there are unpaid invoices. Settle them first.');
        }

        $shift->update([
            'status' => 'CLOSED',
            'closed_by' => $userId,
        ]);

        PosSessionService::closeSessionsForDayShift($dayShiftId);

        return $shift;
    }

    /**
     * Get the current OPEN day shift for the given business day (if any).
     */
    public static function getOpenShiftForBusinessDay(int $businessDayId): ?DayShift
    {
        return DayShift::where('business_day_id', $businessDayId)
            ->where('status', 'OPEN')
            ->first();
    }
}
