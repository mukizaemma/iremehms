<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\OperationalShift;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual operational shifts per module (or one global shift when hotel is configured that way).
 * Each shift is labeled with the hotel-local date when it was opened; it may run past midnight.
 */
class OperationalShiftService
{
    public static function isEnabled(): bool
    {
        return Schema::hasTable('operational_shifts');
    }

    public static function isGlobalScope(Hotel $hotel): bool
    {
        return ($hotel->operational_shift_scope ?? 'per_module') === 'global';
    }

    /**
     * Who can open the Shift management page (operational UI); hotel config still requires manager.
     */
    public static function userCanAccessShiftManagementPage(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->canNavigateModules()) {
            return true;
        }

        return $user->hasPermission('pos_open_shift')
            || $user->hasPermission('pos_close_shift')
            || $user->hasPermission('fo_open_shift')
            || $user->hasPermission('fo_close_shift')
            || $user->hasPermission('stock_open_shift')
            || $user->hasPermission('stock_close_shift')
            || $user->hasPermission('shift_open_global')
            || $user->hasPermission('shift_close_global')
            || $user->hasPermission('fo_check_in_out');
    }

    /**
     * Effective scope for POS: global row or pos row.
     */
    public static function getOpenShiftForPos(Hotel $hotel): ?OperationalShift
    {
        if (! self::isEnabled()) {
            return null;
        }
        if (self::isGlobalScope($hotel)) {
            return self::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL);
        }

        return self::getOpenByScope($hotel, OperationalShift::SCOPE_POS);
    }

    public static function getOpenShiftForFrontOffice(Hotel $hotel): ?OperationalShift
    {
        if (! self::isEnabled()) {
            return null;
        }
        if (self::isGlobalScope($hotel)) {
            return self::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL);
        }

        return self::getOpenByScope($hotel, OperationalShift::SCOPE_FRONT_OFFICE);
    }

    public static function getOpenShiftForStore(Hotel $hotel): ?OperationalShift
    {
        if (! self::isEnabled()) {
            return null;
        }
        if (self::isGlobalScope($hotel)) {
            return self::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL);
        }

        return self::getOpenByScope($hotel, OperationalShift::SCOPE_STORE);
    }

    public static function getOpenByScope(Hotel $hotel, string $moduleScope): ?OperationalShift
    {
        return OperationalShift::where('hotel_id', $hotel->id)
            ->where('module_scope', $moduleScope)
            ->where('status', OperationalShift::STATUS_OPEN)
            ->orderByDesc('opened_at')
            ->first();
    }

    public static function hasOpenShiftForPos(Hotel $hotel): bool
    {
        return self::getOpenShiftForPos($hotel) !== null;
    }

    public static function hasOpenShiftForFrontOffice(Hotel $hotel): bool
    {
        return self::getOpenShiftForFrontOffice($hotel) !== null;
    }

    public static function hasOpenShiftForStore(Hotel $hotel): bool
    {
        return self::getOpenShiftForStore($hotel) !== null;
    }

    /**
     * Open a new operational shift. Closes nothing automatically.
     *
     * @throws \RuntimeException
     */
    public static function openShift(Hotel $hotel, string $moduleScope, int $userId, ?string $openNote = null): OperationalShift
    {
        if (! self::isEnabled()) {
            throw new \RuntimeException('Operational shifts are not available.');
        }

        $allowed = [
            OperationalShift::SCOPE_GLOBAL,
            OperationalShift::SCOPE_POS,
            OperationalShift::SCOPE_FRONT_OFFICE,
            OperationalShift::SCOPE_STORE,
        ];
        if (! in_array($moduleScope, $allowed, true)) {
            throw new \RuntimeException('Invalid module scope.');
        }

        if (self::isGlobalScope($hotel) && $moduleScope !== OperationalShift::SCOPE_GLOBAL) {
            throw new \RuntimeException('Hotel is set to a single global shift: open the global shift only.');
        }

        if (! self::isGlobalScope($hotel) && $moduleScope === OperationalShift::SCOPE_GLOBAL) {
            throw new \RuntimeException('Hotel uses per-module shifts: open POS, Front office, or Store instead of global.');
        }

        if (self::getOpenByScope($hotel, $moduleScope)) {
            throw new \RuntimeException('A shift is already open for this scope. Close it first.');
        }

        $tz = $hotel->getTimezone();
        $now = Carbon::now($tz);
        $referenceDate = $now->format('Y-m-d');

        return OperationalShift::create([
            'hotel_id' => $hotel->id,
            'module_scope' => $moduleScope,
            'reference_date' => $referenceDate,
            'opened_at' => $now,
            'opened_by' => $userId,
            'open_note' => $openNote ? trim($openNote) : null,
            'status' => OperationalShift::STATUS_OPEN,
        ]);
    }

    /**
     * Close shift. closeComment is required (handoff to managers).
     *
     * @throws \RuntimeException
     */
    /**
     * Close an operational shift. Close comment is optional — add one when something must be
     * communicated to managers or supervisors (handover, incidents, exceptions).
     * If provided, it must be at least 3 characters.
     */
    public static function closeShift(OperationalShift $shift, int $userId, ?string $closeComment): void
    {
        $comment = $closeComment !== null ? trim($closeComment) : '';
        if ($comment !== '' && mb_strlen($comment) < 3) {
            throw new \RuntimeException('If you add a close comment, use at least 3 characters (or leave it blank).');
        }

        if ($shift->status !== OperationalShift::STATUS_OPEN) {
            throw new \RuntimeException('This shift is not open.');
        }

        if ($shift->module_scope === OperationalShift::SCOPE_POS) {
            self::assertPosCanClose($shift);
        }
        if ($shift->module_scope === OperationalShift::SCOPE_FRONT_OFFICE) {
            self::assertFrontOfficeCanClose($shift);
        }
        if ($shift->module_scope === OperationalShift::SCOPE_STORE) {
            self::assertStoreCanClose($shift);
        }
        if ($shift->module_scope === OperationalShift::SCOPE_GLOBAL) {
            self::assertPosCanClose($shift);
            self::assertFrontOfficeCanClose($shift);
        }

        $shift->update([
            'closed_at' => now(),
            'closed_by' => $userId,
            'close_comment' => $comment === '' ? null : $comment,
            'status' => OperationalShift::STATUS_CLOSED,
        ]);

        if (Schema::hasColumn('pos_sessions', 'operational_shift_id')) {
            PosSession::where('operational_shift_id', $shift->id)
                ->where('status', 'OPEN')
                ->update([
                    'closed_at' => now(),
                    'status' => 'CLOSED',
                ]);
        }
    }

    /**
     * Block close if unpaid invoices exist for orders in this shift (POS / global).
     */
    protected static function assertPosCanClose(OperationalShift $shift): void
    {
        if (! Schema::hasColumn('pos_sessions', 'operational_shift_id')) {
            return;
        }

        $sessionIds = PosSession::where('operational_shift_id', $shift->id)->pluck('id');
        if ($sessionIds->isEmpty()) {
            return;
        }

        $orderIds = Order::whereIn('session_id', $sessionIds)->pluck('id');
        if ($orderIds->isEmpty()) {
            return;
        }

        $unpaid = Invoice::whereIn('order_id', $orderIds)->where('invoice_status', 'UNPAID')->count();
        if ($unpaid > 0) {
            throw new \RuntimeException("Cannot close: {$unpaid} unpaid invoice(s) still open. Settle, post to room, or assign charges before closing.");
        }
    }

    /**
     * In-house guests with outstanding folio balance must be settled before FO shift closes.
     */
    protected static function assertFrontOfficeCanClose(OperationalShift $shift): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        $hotelId = (int) $shift->hotel_id;
        $checkedIn = Reservation::where('hotel_id', $hotelId)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->get(['id', 'total_amount', 'paid_amount', 'reservation_number', 'guest_name']);

        $withBalance = $checkedIn->filter(function (Reservation $r) {
            $due = (float) ($r->total_amount ?? 0) - (float) ($r->paid_amount ?? 0);

            return $due > 0.02;
        });

        if ($withBalance->isNotEmpty()) {
            $first = $withBalance->first();
            $n = $withBalance->count();
            throw new \RuntimeException(
                "Cannot close Front office shift: {$n} in-house reservation(s) still have a folio balance (e.g. {$first->reservation_number} — {$first->guest_name}). Collect payment or post charges before closing."
            );
        }
    }

    /**
     * Store module: extend with pending requisition checks if needed.
     */
    protected static function assertStoreCanClose(OperationalShift $shift): void
    {
        // Optional: block if critical stock operations are pending — add business rules here.
    }

    /**
     * Recent shifts for audit: opened in the last N days, plus any shift still open
     * (so long-running open shifts always appear in the list).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OperationalShift>
     */
    public static function recentHistory(Hotel $hotel, int $days = 14)
    {
        $since = Carbon::now($hotel->getTimezone())->subDays($days)->startOfDay();

        return OperationalShift::where('hotel_id', $hotel->id)
            ->where(function ($q) use ($since) {
                $q->where('opened_at', '>=', $since)
                    ->orWhere('status', OperationalShift::STATUS_OPEN);
            })
            ->orderByDesc('opened_at')
            ->with(['opener', 'closer'])
            ->limit(200)
            ->get();
    }

    public static function userCanOpenPos(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('pos_open_shift');
    }

    public static function userCanClosePos(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('pos_close_shift');
    }

    public static function userCanOpenFrontOffice(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_open_shift')
            || $user->hasPermission('fo_check_in_out');
    }

    public static function userCanCloseFrontOffice(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_close_shift')
            || $user->hasPermission('fo_check_in_out');
    }

    public static function userCanOpenStore(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('stock_open_shift');
    }

    public static function userCanCloseStore(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('stock_close_shift');
    }

    public static function userCanOpenGlobal(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('shift_open_global');
    }

    public static function userCanCloseGlobal(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('shift_close_global');
    }
}
