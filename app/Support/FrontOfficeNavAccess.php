<?php

namespace App\Support;

use App\Models\Module;
use App\Models\User;

/**
 * Permission checks for Front Office hub tiles — mirrors each target page's mount() rules.
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

    /** Rooms, booking calendar, new reservation, all reservations — module access (see FrontOfficeRooms, AddReservation, ReservationsList). */
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

    /** QuickGroupBooking::mount */
    public static function canViewGroupReservation(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    /** SelfRegisteredList::mount */
    public static function canViewPreArrival(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    /** GuestsReport::mount */
    public static function canViewGuestsReport(User $user): bool
    {
        return self::hasFrontOfficeModule($user);
    }

    /** DailyAccommodationReport::mount */
    public static function canViewRoomsDailyReport(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('fo_collect_payment')
            || $user->isReceptionist();
    }

    /** FrontOfficeReports::mount */
    public static function canViewOtherSalesReport(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_availability')
            || $user->hasPermission('fo_view_guest_bills')
            || $user->hasPermission('reports_view_all')
            || $user->isReceptionist();
    }

    /** GuestCommunications::mount */
    public static function canViewCommunication(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('fo_guest_comms')
            || $user->isReceptionist()
            || $user->isEffectiveGeneralManager()
            || $user->isManager();
    }
}
