<?php

namespace App\Helpers;

use App\Models\Hotel;
use App\Models\OrderItemVoidRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Shared POS header navigation (quick links + void badge) used across POS Livewire views.
 */
class PosNavHelper
{
    /**
     * Pending void requests for the current hotel (same rules as POS void-requests list).
     */
    public static function pendingVoidRequestsCount(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        $hotel = Hotel::getHotel();
        $query = OrderItemVoidRequest::query()
            ->where('status', OrderItemVoidRequest::STATUS_PENDING);

        if ($hotel) {
            $query->whereHas('orderItem.order.waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            });
        }

        if (! $user->hasPermission('pos_approve_void')) {
            $query->where('requested_by_id', $user->id);
        }

        return (int) $query->count();
    }

    public static function canViewPosReportsNav(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $slug = $user->getEffectiveRole()?->slug;

        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('reports_view_all')
            || in_array($slug, ['waiter', 'cashier'], true)
            || $user->isRestaurantManager();
    }
}
