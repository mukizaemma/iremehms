<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\OperationalShift;
use App\Models\OperationalShiftOpenRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff request that a module operational shift be opened; managers / shift-capable users fulfill by opening the shift.
 */
class OperationalShiftOpenRequestService
{
    public static function isEnabled(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('operational_shift_open_requests');
    }

    public static function pendingForHotel(Hotel $hotel)
    {
        return OperationalShiftOpenRequest::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->with(['requester']);
    }

    /**
     * Pending request from this user for this scope (if any).
     */
    public static function pendingForUserScope(Hotel $hotel, User $user, string $moduleScope): ?OperationalShiftOpenRequest
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OperationalShiftOpenRequest::query()
            ->where('hotel_id', $hotel->id)
            ->where('requested_by', $user->id)
            ->where('module_scope', $moduleScope)
            ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
            ->first();
    }

    /**
     * Any pending requests for this hotel + scope (from anyone).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OperationalShiftOpenRequest>
     */
    public static function pendingForScope(Hotel $hotel, string $moduleScope)
    {
        return OperationalShiftOpenRequest::query()
            ->where('hotel_id', $hotel->id)
            ->where('module_scope', $moduleScope)
            ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->with(['requester'])
            ->get();
    }

    public static function pendingCountForScope(Hotel $hotel, string $moduleScope): int
    {
        if (! self::isEnabled()) {
            return 0;
        }

        return OperationalShiftOpenRequest::query()
            ->where('hotel_id', $hotel->id)
            ->where('module_scope', $moduleScope)
            ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
            ->count();
    }

    /**
     * @throws \RuntimeException
     */
    public static function createRequest(Hotel $hotel, User $requester, string $moduleScope, ?string $note = null): OperationalShiftOpenRequest
    {
        if (! self::isEnabled()) {
            throw new \RuntimeException('Shift open requests are not available.');
        }
        if (! OperationalShiftActionGate::appliesOperationalShiftWorkflow($hotel)) {
            throw new \RuntimeException('Operational shifts are not enforced for this hotel.');
        }

        self::assertScopeMatchesHotel($hotel, $moduleScope);

        if (OperationalShiftService::getOpenByScope($hotel, $moduleScope)) {
            throw new \RuntimeException('A shift is already open for this area. You can start working.');
        }

        $existing = self::pendingForUserScope($hotel, $requester, $moduleScope);
        if ($existing) {
            throw new \RuntimeException('You already have a pending request for this shift. Please wait for a supervisor to open the shift.');
        }

        return OperationalShiftOpenRequest::create([
            'hotel_id' => $hotel->id,
            'requested_by' => $requester->id,
            'module_scope' => $moduleScope,
            'note' => $note ? trim($note) : null,
            'status' => OperationalShiftOpenRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Open the operational shift (if still needed) and mark all pending requests for that scope fulfilled.
     *
     * @throws \RuntimeException
     */
    public static function fulfillWithOpenShift(OperationalShiftOpenRequest $request, User $resolver, ?string $openNote = null): void
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('This request is no longer pending.');
        }

        $hotel = $request->hotel;
        if (! $hotel) {
            throw new \RuntimeException('Hotel not found.');
        }

        self::assertResolverCanOpen($resolver, $request->module_scope);

        DB::transaction(function () use ($request, $resolver, $hotel, $openNote) {
            $scope = $request->module_scope;

            if (! OperationalShiftService::getOpenByScope($hotel, $scope)) {
                OperationalShiftService::openShift($hotel, $scope, $resolver->id, $openNote);
            }

            $now = now();
            OperationalShiftOpenRequest::query()
                ->where('hotel_id', $hotel->id)
                ->where('module_scope', $scope)
                ->where('status', OperationalShiftOpenRequest::STATUS_PENDING)
                ->update([
                    'status' => OperationalShiftOpenRequest::STATUS_FULFILLED,
                    'resolved_by' => $resolver->id,
                    'resolved_at' => $now,
                    'resolution_note' => null,
                ]);
        });
    }

    /**
     * Reject a single pending request (others for same scope remain pending).
     *
     * @throws \RuntimeException
     */
    public static function reject(OperationalShiftOpenRequest $request, User $resolver, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw new \RuntimeException('This request is no longer pending.');
        }

        if (! self::userCanResolveRequests($resolver)) {
            throw new \RuntimeException('You are not allowed to resolve shift open requests.');
        }

        $request->update([
            'status' => OperationalShiftOpenRequest::STATUS_REJECTED,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'resolution_note' => $note ? trim($note) : null,
        ]);
    }

    public static function userCanResolveRequests(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || OperationalShiftService::userCanAccessShiftManagementPage($user);
    }

    public static function userCanOpenOperationalScope(User $user, string $moduleScope): bool
    {
        return match ($moduleScope) {
            OperationalShift::SCOPE_GLOBAL => OperationalShiftService::userCanOpenGlobal($user),
            OperationalShift::SCOPE_POS => OperationalShiftService::userCanOpenPos($user),
            OperationalShift::SCOPE_FRONT_OFFICE => OperationalShiftService::userCanOpenFrontOffice($user),
            OperationalShift::SCOPE_STORE => OperationalShiftService::userCanOpenStore($user),
            default => false,
        };
    }

    /**
     * @throws \RuntimeException
     */
    protected static function assertResolverCanOpen(User $user, string $moduleScope): void
    {
        if (! self::userCanOpenOperationalScope($user, $moduleScope)) {
            throw new \RuntimeException('You do not have permission to open this shift. Ask another supervisor.');
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected static function assertScopeMatchesHotel(Hotel $hotel, string $moduleScope): void
    {
        $allowed = [
            OperationalShift::SCOPE_GLOBAL,
            OperationalShift::SCOPE_POS,
            OperationalShift::SCOPE_FRONT_OFFICE,
            OperationalShift::SCOPE_STORE,
        ];
        if (! in_array($moduleScope, $allowed, true)) {
            throw new \RuntimeException('Invalid shift scope.');
        }
        if (OperationalShiftService::isGlobalScope($hotel) && $moduleScope !== OperationalShift::SCOPE_GLOBAL) {
            throw new \RuntimeException('This hotel uses a single global shift. Request the global shift.');
        }
        if (! OperationalShiftService::isGlobalScope($hotel) && $moduleScope === OperationalShift::SCOPE_GLOBAL) {
            throw new \RuntimeException('This hotel uses per-module shifts. Request POS, Front office, or Store.');
        }
    }
}
