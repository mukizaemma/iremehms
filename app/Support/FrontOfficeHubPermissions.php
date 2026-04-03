<?php

namespace App\Support;

use App\Models\User;
use App\Services\OperationalShiftService;

/**
 * Which Front Office hub tiles a user may use — mirrors mount() rules on each Livewire page.
 */
final class FrontOfficeHubPermissions
{
    public static function hasFrontOfficeModuleAccess(?User $u, ?int $frontOfficeModuleId): bool
    {
        if (! $u) {
            return false;
        }
        if (! $frontOfficeModuleId) {
            return $u->isSuperAdmin();
        }

        return $u->hasModuleAccess($frontOfficeModuleId);
    }

    /**
     * @return array<string, bool>
     */
    public static function tileVisibility(User $u, ?int $frontOfficeModuleId): array
    {
        $fo = self::hasFrontOfficeModuleAccess($u, $frontOfficeModuleId);

        // FrontOfficeRooms, module calendar, AddReservation, ReservationsList, QuickGroupBooking, SelfRegisteredList, GuestsReport
        $coreFo = $fo;

        // DailyAccommodationReport::mount
        $roomsDaily = $fo && (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('fo_availability')
            || $u->hasPermission('fo_view_guest_bills')
            || $u->hasPermission('fo_collect_payment')
            || $u->isReceptionist()
        );

        // FrontOfficeReports::mount
        $otherSales = $fo && (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('fo_availability')
            || $u->hasPermission('fo_view_guest_bills')
            || $u->hasPermission('reports_view_all')
            || $u->isReceptionist()
        );

        // GuestCommunications::mount
        $communication = $fo && (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('fo_guest_comms')
            || $u->isReceptionist()
            || $u->isEffectiveGeneralManager()
            || $u->isManager()
        );

        $proforma = $fo && $u->hasPermission('fo_proforma_manage');
        $wellness = $fo && $u->hasPermission('fo_wellness_manage');

        // Operational shifts (FO / global) — same access as Shift management page for FO context
        $shiftManagement = $fo && (
            $u->isSuperAdmin()
            || $u->canNavigateModules()
            || $u->hasPermission('fo_open_shift')
            || $u->hasPermission('fo_close_shift')
            || $u->hasPermission('shift_open_global')
            || $u->hasPermission('shift_close_global')
            || ($u->hasPermission('fo_check_in_out') && OperationalShiftService::isEnabled())
        );

        return [
            'rooms' => $coreFo,
            'booking_calendar' => $coreFo,
            'new_reservation' => $coreFo,
            'all_reservations' => $coreFo,
            'group_reservation' => $coreFo,
            'pre_arrival' => $coreFo,
            'guests_report' => $coreFo,
            'rooms_daily_report' => $roomsDaily,
            'other_sales_report' => $otherSales,
            'communication' => $communication,
            'proforma' => $proforma,
            'wellness' => $wellness,
            'shift_management' => $shiftManagement,
        ];
    }
}
