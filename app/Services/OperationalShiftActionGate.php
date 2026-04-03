<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\OperationalShift;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side rules for operational shifts: open shift required for selling / FO / store actions,
 * long-running shifts (>24h) require explicit "continue" acknowledgment (session),
 * and optional UI snooze (cache) for the reminder banner.
 */
class OperationalShiftActionGate
{
    public const LONG_SHIFT_HOURS = 24.0;

    public const PROMPT_SNOOZE_HOURS = 10;

    public static function appliesOperationalShiftWorkflow(Hotel $hotel): bool
    {
        return OperationalShiftService::isEnabled() && ! $hotel->isNoShiftMode();
    }

    /**
     * When operational shifts are enabled (and not NO_SHIFT), POS must use an open operational shift
     * even if hotel shift_mode is OPTIONAL_SHIFT.
     */
    public static function requiresOperationalShiftForPos(Hotel $hotel): bool
    {
        if (! OperationalShiftService::isEnabled()) {
            return $hotel->isStrictShiftMode();
        }

        return ! $hotel->isNoShiftMode();
    }

    protected static function snoozeCacheKey(int $userId, int $shiftId): string
    {
        return 'operational_shift_prompt_snooze:'.$userId.':'.$shiftId;
    }

    public static function isPromptSnoozed(int $userId, int $shiftId): bool
    {
        return Cache::has(self::snoozeCacheKey($userId, $shiftId));
    }

    public static function snoozePromptTenHours(int $userId, int $shiftId): void
    {
        $until = now()->addHours(self::PROMPT_SNOOZE_HOURS);
        Cache::put(self::snoozeCacheKey($userId, $shiftId), true, $until);
    }

    /**
     * @return array<int, int> shift_id => acknowledged unix timestamp
     */
    public static function getLongShiftAcknowledgments(): array
    {
        return session('operational_shift_long_ack', []);
    }

    public static function hasLongShiftAcknowledgment(int $shiftId): bool
    {
        $ack = self::getLongShiftAcknowledgments();

        return ! empty($ack[$shiftId]);
    }

    public static function setLongShiftAcknowledgment(int $shiftId): void
    {
        $ack = self::getLongShiftAcknowledgments();
        $ack[$shiftId] = now()->timestamp;
        session(['operational_shift_long_ack' => $ack]);
    }

    public static function requiresLongShiftAcknowledgment(OperationalShift $shift): bool
    {
        if (! $shift->isOpen()) {
            return false;
        }

        return $shift->durationHours() >= self::LONG_SHIFT_HOURS;
    }

    public static function assertLongShiftAcknowledgedIfNeeded(OperationalShift $shift): void
    {
        if (! self::requiresLongShiftAcknowledgment($shift)) {
            return;
        }
        if (self::hasLongShiftAcknowledgment($shift->id)) {
            return;
        }
        throw new \RuntimeException(
            'This operational shift has been open for over '.(int) self::LONG_SHIFT_HOURS.' hours. '
            .'Use the shift reminder at the top to confirm you are continuing with this shift, or close it in Shift management.'
        );
    }

    public static function assertFrontOfficeActionAllowed(Hotel $hotel): void
    {
        if (! self::appliesOperationalShiftWorkflow($hotel)) {
            return;
        }
        $shift = OperationalShiftService::getOpenShiftForFrontOffice($hotel);
        if (! $shift) {
            throw new \RuntimeException(
                'Front office actions require an open operational shift. Open or continue a shift in Shift management.'
            );
        }
        self::assertLongShiftAcknowledgedIfNeeded($shift);
    }

    public static function assertStoreActionAllowed(Hotel $hotel): void
    {
        if (! self::appliesOperationalShiftWorkflow($hotel)) {
            return;
        }
        $shift = OperationalShiftService::getOpenShiftForStore($hotel);
        if (! $shift) {
            throw new \RuntimeException(
                'Recording stock requires an open Store operational shift. Open or continue a shift in Shift management.'
            );
        }
        self::assertLongShiftAcknowledgedIfNeeded($shift);
    }

    public static function assertPosOperationalShiftAllowed(Hotel $hotel): void
    {
        if (! self::appliesOperationalShiftWorkflow($hotel)) {
            return;
        }
        $shift = OperationalShiftService::getOpenShiftForPos($hotel);
        if (! $shift) {
            throw new \RuntimeException(
                'POS requires an open operational shift. Open or continue a shift in Shift management.'
            );
        }
        self::assertLongShiftAcknowledgedIfNeeded($shift);
    }

    /**
     * Open operational shifts this user should be reminded about (not snoozed).
     *
     * @return array<int, OperationalShift>
     */
    public static function getVisibleOpenShiftsForUser(Hotel $hotel, User $user): array
    {
        if (! self::appliesOperationalShiftWorkflow($hotel)) {
            return [];
        }

        $candidates = self::collectRelevantOpenShifts($hotel, $user);
        $visible = [];
        foreach ($candidates as $shift) {
            if (! self::isPromptSnoozed((int) $user->id, (int) $shift->id)) {
                $visible[] = $shift;
            }
        }

        return $visible;
    }

    /**
     * @return array<int, OperationalShift>
     */
    protected static function collectRelevantOpenShifts(Hotel $hotel, User $user): array
    {
        if ($user->isSuperAdmin() || $user->canNavigateModules()) {
            return self::allOpenShiftsForHotel($hotel);
        }

        $modules = $user->getAccessibleModules()->pluck('slug');
        $out = [];
        if ($hotel->isGlobalOperationalShiftScope()) {
            $g = OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL);
            if ($g && ($modules->contains('restaurant') || $modules->contains('front-office') || $modules->contains('store'))) {
                $out[] = $g;
            }

            return $out;
        }

        $map = [
            OperationalShift::SCOPE_POS => 'restaurant',
            OperationalShift::SCOPE_FRONT_OFFICE => 'front-office',
            OperationalShift::SCOPE_STORE => 'store',
        ];
        foreach ($map as $scope => $slug) {
            if (! $modules->contains($slug)) {
                continue;
            }
            $s = OperationalShiftService::getOpenByScope($hotel, $scope);
            if ($s) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * @return array<int, OperationalShift>
     */
    protected static function allOpenShiftsForHotel(Hotel $hotel): array
    {
        $out = [];
        if ($hotel->isGlobalOperationalShiftScope()) {
            $g = OperationalShiftService::getOpenByScope($hotel, OperationalShift::SCOPE_GLOBAL);
            if ($g) {
                $out[] = $g;
            }

            return $out;
        }
        foreach ([OperationalShift::SCOPE_POS, OperationalShift::SCOPE_FRONT_OFFICE, OperationalShift::SCOPE_STORE] as $scope) {
            $s = OperationalShiftService::getOpenByScope($hotel, $scope);
            if ($s) {
                $out[] = $s;
            }
        }

        return $out;
    }

    public static function labelForScope(string $scope): string
    {
        return match ($scope) {
            OperationalShift::SCOPE_GLOBAL => 'Global (whole hotel)',
            OperationalShift::SCOPE_POS => 'POS',
            OperationalShift::SCOPE_FRONT_OFFICE => 'Front office',
            OperationalShift::SCOPE_STORE => 'Store',
            default => $scope,
        };
    }

    /**
     * Module scopes this user should see status for (open vs closed), for banners and shift awareness.
     *
     * @return list<string>
     */
    public static function relevantModuleScopesForUser(Hotel $hotel, User $user): array
    {
        if (! self::appliesOperationalShiftWorkflow($hotel)) {
            return [];
        }

        if ($user->isSuperAdmin() || $user->canNavigateModules()) {
            if ($hotel->isGlobalOperationalShiftScope()) {
                return [OperationalShift::SCOPE_GLOBAL];
            }

            return [
                OperationalShift::SCOPE_POS,
                OperationalShift::SCOPE_FRONT_OFFICE,
                OperationalShift::SCOPE_STORE,
            ];
        }

        $modules = $user->getAccessibleModules()->pluck('slug');
        if ($hotel->isGlobalOperationalShiftScope()) {
            if ($modules->contains('restaurant') || $modules->contains('front-office') || $modules->contains('store')) {
                return [OperationalShift::SCOPE_GLOBAL];
            }

            return [];
        }

        $map = [
            OperationalShift::SCOPE_POS => 'restaurant',
            OperationalShift::SCOPE_FRONT_OFFICE => 'front-office',
            OperationalShift::SCOPE_STORE => 'store',
        ];
        $out = [];
        foreach ($map as $scope => $slug) {
            if ($modules->contains($slug)) {
                $out[] = $scope;
            }
        }

        return $out;
    }
}
