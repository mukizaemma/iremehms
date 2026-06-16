<?php

namespace App\Support;

use App\Models\Module;
use App\Models\User;
use App\Services\OperationalShiftService;

/**
 * Permission checks for Front Office navigation — mirrors each target page's mount() rules.
 */
final class FrontOfficeNavAccess
{
    public static function frontOfficeModuleId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $id = Module::where('slug', 'front-office')->value('id');
        }

        return $id;
    }

    public static function hasFrontOfficeModule(User $user): bool
    {
        $mid = self::frontOfficeModuleId();

        return $mid !== null && $user->hasModuleAccess($mid);
    }

    public static function canViewRooms(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewBookingCalendar(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewNewReservation(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewAllReservations(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewGroupReservation(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewPreArrival(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewGuestsReport(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canViewRoomsDailyReport(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('fo_collect_payment')
            || $user->isReceptionist();
    }

    public static function canViewOtherSalesReport(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('reports_view_all')
            || $user->isReceptionist();
    }

    public static function canViewGuestBills(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('fo_collect_payment')
            || $user->hasPermission('reports_view_all');
    }

    public static function canViewCommunication(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();
    }

    public static function canManageProforma(User $user): bool
    {
        return self::hasFrontOfficeModule($user) && $user->hasPermission('fo_proforma_manage');
    }

    public static function canManageWellness(User $user): bool
    {
        return self::hasFrontOfficeModule($user) && $user->hasPermission('fo_wellness_manage');
    }

    public static function canConfigureHotelDetails(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('hotel_configure_details');
    }

    public static function canConfigureFoHotelSettings(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager() || $user->canNavigateModules();
    }

    public static function canManageRoomTypes(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isEffectiveGeneralManager()
            || $user->canNavigateModules()
            || $user->hasPermission('back_office_rooms');
    }

    public static function canViewAdditionalCharges(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    public static function canManageAdditionalCharges(User $user): bool
    {
        return $user->isSuperAdmin() || $user->canNavigateModules();
    }

    public static function canManageAmenities(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public static function canAccessHotelSettingsHub(User $user): bool
    {
        return $user->canNavigateModules() || $user->isSuperAdmin();
    }

    public static function canAccessBackOfficeHub(User $user): bool
    {
        return $user->canNavigateModules() || $user->isSuperAdmin();
    }

    public static function canViewGeneralSalesReports(User $user): bool
    {
        return self::canViewOtherSalesReport($user)
            || $user->hasPermission('reports_view_all');
    }

    public static function canViewShiftManagement(User $user): bool
    {
        return OperationalShiftService::userCanAccessShiftManagementPage($user);
    }
}
