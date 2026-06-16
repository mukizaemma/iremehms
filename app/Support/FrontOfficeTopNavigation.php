<?php

namespace App\Support;

use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Front office primary bar (Dashboard, Settings, Reports) and shortcut strip.
 * Used for every hotel user with Front Office module access (permission-filtered).
 */
final class FrontOfficeTopNavigation
{
    /**
     * All front-office module users (except accountants on accountant hub).
     */
    public static function usesFrontOfficePrimaryBar(
        ?User $user,
        bool $hasFrontOffice,
        bool $showBackend,
        bool $isAccountant,
        bool $isManagerLike,
    ): bool {
        if (! $user || ! $hasFrontOffice || $isAccountant) {
            return false;
        }

        if ($user->isIremeUser() && ! $user->hotel_id) {
            return false;
        }

        return FrontOfficeNavAccess::hasFrontOfficeModule($user);
    }

    /**
     * Simple dashboard home (not the room summary board).
     */
    public static function usesFrontOfficeDashboardHome(
        ?User $user,
        bool $hasFrontOffice,
        bool $isAccountant,
        bool $isManagerLike,
        ?string $effectiveRoleSlug,
    ): bool {
        if (! self::usesFrontOfficePrimaryBar($user, $hasFrontOffice, false, $isAccountant, $isManagerLike)) {
            return false;
        }

        if ($isManagerLike || in_array($effectiveRoleSlug, ['manager', 'director', 'general-manager', 'hotel-admin'], true)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array{label: string, href?: string, icon: string, active: bool, children?: list<array{label: string, href: string, active: bool}>}>
     */
    public static function primaryItems(
        ?User $user,
        Request $request,
        bool $hasRestaurant = false,
        bool $hasStore = false,
        bool $showBackend = false,
    ): array {
        if (! $user) {
            return [];
        }

        $items = [
            [
                'label' => 'Dashboard',
                'href' => route('dashboard'),
                'icon' => 'fa-home',
                'active' => $request->routeIs('dashboard'),
            ],
        ];

        $settingsChildren = self::settingsMenuItems($user, $request);
        if ($settingsChildren !== []) {
            $items[] = [
                'label' => 'Settings',
                'icon' => 'fa-cog',
                'active' => self::isSettingsRoute($request),
                'children' => $settingsChildren,
            ];
        }

        $reportsChildren = self::reportsMenuItems($user, $request);
        if ($reportsChildren !== []) {
            $items[] = [
                'label' => 'Reports',
                'icon' => 'fa-chart-bar',
                'active' => self::isReportsRoute($request),
                'children' => $reportsChildren,
            ];
        }

        $communicationChildren = self::communicationMenuItems($user, $request);
        if ($communicationChildren !== []) {
            $items[] = [
                'label' => 'Communication',
                'icon' => 'fa-comments',
                'active' => self::isCommunicationRoute($request),
                'children' => $communicationChildren,
            ];
        }

        $proformaChildren = self::proformaMenuItems($user, $request);
        if ($proformaChildren !== []) {
            $items[] = [
                'label' => 'Proforma',
                'icon' => 'fa-file-signature',
                'active' => self::isProformaRoute($request),
                'children' => $proformaChildren,
            ];
        }

        if ($hasRestaurant && self::canAccessPos($user)) {
            $items[] = [
                'label' => 'POS',
                'href' => route('pos.products'),
                'icon' => 'fa-utensils',
                'active' => $request->routeIs('pos.*') || $request->routeIs('restaurant.*') || $request->routeIs('menu.*'),
            ];
        }

        if ($hasStore && self::canAccessStock($user)) {
            $items[] = [
                'label' => 'Stock',
                'href' => route('goods.receipts'),
                'icon' => 'fa-archive',
                'active' => $request->routeIs(['goods.*', 'stock.*', 'suppliers.*', 'purchase.*']),
            ];
        }

        if ($showBackend && FrontOfficeNavAccess::canAccessBackOfficeHub($user)) {
            $items[] = [
                'label' => 'Back office',
                'href' => route('back-office.hub'),
                'icon' => 'fa-briefcase',
                'active' => $request->routeIs('back-office.*') || $request->routeIs(['subscription', 'hotel-details', 'departments.*', 'approvals', 'permission-requests.*', 'recovery.*']),
            ];
        }

        if ($showBackend && FrontOfficeNavAccess::canAccessHotelSettingsHub($user)) {
            $items[] = [
                'label' => 'Hotel settings',
                'href' => route('hotel-settings.hub'),
                'icon' => 'fa-hotel',
                'active' => $request->routeIs('hotel-settings.*') || $request->routeIs(['users.index', 'pos.tables']),
            ];
        }

        return $items;
    }

    /**
     * Shortcut icons for front office users (dashboard + all FO pages).
     *
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    public static function shortcutItems(?User $user, Request $request): array
    {
        if (! $user || ! FrontOfficeNavAccess::hasFrontOfficeModule($user)) {
            return [];
        }

        $tiles = FrontOfficeHubPermissions::tileVisibility($user, FrontOfficeNavAccess::frontOfficeModuleId());
        $items = [];

        if (FrontOfficeNavAccess::canViewRooms($user)) {
            $items[] = self::action(
                'Summary',
                route('dashboard'),
                'fa-th-large',
                'primary',
                $request->routeIs('dashboard') || $request->routeIs('front-office.dashboard'),
            );
        }
        if ($tiles['booking_calendar'] ?? false) {
            $items[] = self::action(
                'Booking calendar',
                route('module.show', 'front-office'),
                'fa-calendar-alt',
                'info',
                $request->routeIs('module.show') && $request->route('module') === 'front-office',
            );
        }
        if ($tiles['new_reservation'] ?? false) {
            $items[] = self::action('New reservation', route('front-office.add-reservation'), 'fa-calendar-plus', 'danger', $request->routeIs('front-office.add-reservation'));
        }
        if ($tiles['group_reservation'] ?? false) {
            $items[] = self::action('Group reservation', route('front-office.quick-group-booking'), 'fa-users', 'warning', $request->routeIs('front-office.quick-group-booking'));
        }
        if ($tiles['pre_arrival'] ?? false) {
            $items[] = self::action('Pre-arrival', route('front-office.self-registered'), 'fa-clipboard-list', 'info', $request->routeIs('front-office.self-registered'));
        }
        if ($tiles['all_reservations'] ?? false) {
            $items[] = self::action(
                'All reservations',
                route('front-office.reservations', ['tab' => 'all']),
                'fa-list',
                'primary',
                self::reservationsTabActive($request, 'all'),
            );
            $items[] = self::action(
                'Expected arrivals',
                route('front-office.reservations', ['tab' => 'arrivals']),
                'fa-plane-arrival',
                'success',
                self::reservationsTabActive($request, 'arrivals'),
            );
            $items[] = self::action(
                'Expected departures',
                route('front-office.reservations', ['tab' => 'departures']),
                'fa-plane-departure',
                'secondary',
                self::reservationsTabActive($request, 'departures'),
            );
            $items[] = self::action(
                'In-house guests',
                route('front-office.reservations', ['tab' => 'in_house']),
                'fa-bed',
                'primary',
                self::reservationsTabActive($request, 'in_house'),
            );
            $items[] = self::action(
                'No show',
                route('front-office.reservations', ['tab' => 'no_show']),
                'fa-user-times',
                'danger',
                self::reservationsTabActive($request, 'no_show'),
            );
            $items[] = self::action(
                'Cancelled',
                route('front-office.reservations', ['tab' => 'cancelled']),
                'fa-ban',
                'warning',
                self::reservationsTabActive($request, 'cancelled'),
            );
            $items[] = self::action(
                'Checked out today',
                route('front-office.reservations', ['tab' => 'checked_out_today']),
                'fa-sign-out-alt',
                'secondary',
                self::reservationsTabActive($request, 'checked_out_today'),
            );
        }
        if ($tiles['wellness'] ?? false) {
            $items[] = self::action('Wellness', route('front-office.wellness'), 'fa-spa', 'success', $request->routeIs('front-office.wellness'));
        }
        if ($tiles['restaurant'] ?? false) {
            $items[] = self::action('Restaurant', route('front-office.restaurant'), 'fa-utensils', 'warning', $request->routeIs('front-office.restaurant'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function settingsMenuItems(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $items = [];

        if (FrontOfficeNavAccess::canConfigureHotelDetails($user)) {
            $items[] = self::menu('General', route('hotel-details'), $request->routeIs('hotel-details') && $request->query('tab', 'general') === 'general');
            $items[] = self::menu('Currencies', route('hotel-details', ['tab' => 'general']), false);
        }
        if (FrontOfficeNavAccess::canConfigureFoHotelSettings($user)) {
            $items[] = self::menu('Business sources', route('front-office-hotel-settings'), $request->routeIs('front-office-hotel-settings'));
        }
        if (FrontOfficeNavAccess::canAccessHotelSettingsHub($user)) {
            $items[] = self::menu('Payment methods', route('hotel-settings.hub'), $request->routeIs('hotel-settings.hub'));
        }
        if (FrontOfficeNavAccess::canViewAdditionalCharges($user)) {
            $items[] = self::menu('Packages', route('additional-charges.index'), $request->routeIs('additional-charges.index'));
        }
        if (FrontOfficeNavAccess::canManageRoomTypes($user)) {
            $items[] = self::menu('Rate types', route('room-types.index'), $request->routeIs('room-types.index'));
            $items[] = self::menu('Room types', route('room-types.index'), $request->routeIs('room-types.index'));
        }
        if (FrontOfficeNavAccess::canViewRooms($user)) {
            $items[] = self::menu('Rooms', route('front-office.rooms'), $request->routeIs('front-office.rooms'));
        }
        if (FrontOfficeNavAccess::canManageAmenities($user)) {
            $items[] = self::menu('Amenities', route('amenities.index'), $request->routeIs('amenities.index'));
        }
        if (FrontOfficeNavAccess::canViewShiftManagement($user)) {
            $items[] = self::menu('Shifts', route('shift.management'), $request->routeIs('shift.management'));
        }
        $items[] = self::menu('My account', route('profile'), $request->routeIs('profile') || $request->routeIs('account.hub'));

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function communicationMenuItems(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $tiles = FrontOfficeHubPermissions::tileVisibility($user, FrontOfficeNavAccess::frontOfficeModuleId());
        if (! ($tiles['communication'] ?? false)) {
            return [];
        }

        $tab = $request->query('tab');

        return [
            self::menu(
                'Guest messages',
                route('front-office.communications', ['tab' => 'guests']),
                $request->routeIs('front-office.communications') && $tab === 'guests',
            ),
            self::menu(
                'Staff messages',
                route('front-office.communications', ['tab' => 'staff']),
                $request->routeIs('front-office.communications') && $tab === 'staff',
            ),
            self::menu(
                'Communication log',
                route('front-office.communications'),
                $request->routeIs('front-office.communications') && ! in_array($tab, ['guests', 'staff'], true),
            ),
        ];
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function proformaMenuItems(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $tiles = FrontOfficeHubPermissions::tileVisibility($user, FrontOfficeNavAccess::frontOfficeModuleId());
        if (! ($tiles['proforma'] ?? false)) {
            return [];
        }

        $items = [
            self::menu(
                'Proforma invoices',
                route('front-office.proforma-invoices'),
                $request->routeIs('front-office.proforma-invoices')
                    && ! $request->routeIs(['front-office.proforma-invoices.create', 'front-office.proforma-invoices.edit']),
            ),
            self::menu(
                'New proforma',
                route('front-office.proforma-invoices.create'),
                $request->routeIs('front-office.proforma-invoices.create'),
            ),
        ];

        if (FrontOfficeNavAccess::canManageProforma($user)) {
            $items[] = self::menu(
                'Proforma defaults',
                route('front-office.proforma-line-defaults'),
                $request->routeIs('front-office.proforma-line-defaults'),
            );
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function reportsMenuItems(?User $user, Request $request): array
    {
        if (! $user) {
            return [];
        }

        $tiles = FrontOfficeHubPermissions::tileVisibility($user, FrontOfficeNavAccess::frontOfficeModuleId());
        $items = [];

        if ($tiles['guests_report'] ?? false) {
            $items[] = self::menu('Guests report', route('front-office.guests-report'), $request->routeIs('front-office.guests-report'));
        }
        if ($tiles['all_reservations'] ?? false) {
            $items[] = self::menu('All reservations', route('front-office.reservations', ['tab' => 'all']), self::reservationsTabActive($request, 'all'));
            $items[] = self::menu('In-house guests', route('front-office.reservations', ['tab' => 'in_house']), self::reservationsTabActive($request, 'in_house'));
            $items[] = self::menu('No show', route('front-office.reservations', ['tab' => 'no_show']), self::reservationsTabActive($request, 'no_show'));
            $items[] = self::menu('Cancelled', route('front-office.reservations', ['tab' => 'cancelled']), self::reservationsTabActive($request, 'cancelled'));
        }
        if ($tiles['rooms_daily_report'] ?? false) {
            $items[] = self::menu('Rooms daily report', route('front-office.daily-accommodation-report'), $request->routeIs('front-office.daily-accommodation-report'));
        }
        if ($tiles['operational_audit'] ?? false) {
            $items[] = self::menu(
                'Day-end audit',
                route('front-office.operational-day-audit'),
                $request->routeIs('front-office.operational-day-audit'),
            );
            $items[] = self::menu(
                'Checked out today',
                route('front-office.reservations', ['tab' => 'checked_out_today']),
                self::reservationsTabActive($request, 'checked_out_today'),
            );
        }
        if ($tiles['other_sales_report'] ?? false) {
            $items[] = self::menu('Sales report', route('front-office.reports'), $request->routeIs('front-office.reports'));
            $items[] = self::menu('Other sales', route('front-office.reports'), $request->routeIs('front-office.reports'));
            $items[] = self::menu('Reservation channels', route('front-office.reports'), $request->routeIs('front-office.reports'));
            $items[] = self::menu('Complimentary services', route('front-office.reports.complementary'), $request->routeIs(['front-office.reports.complementary', 'front-office.reports.complementary.print']));
            $items[] = self::menu('Payments', route('front-office.reports'), false);
        }
        if (FrontOfficeNavAccess::canViewGuestBills($user)) {
            $items[] = self::menu('Bills', route('front-office.reports'), $request->routeIs('front-office.reports'));
        }
        if (FrontOfficeNavAccess::canViewGeneralSalesReports($user)) {
            if ($user->canNavigateModules() || $user->isSuperAdmin()) {
                $items[] = self::menu('Daily sales summary', route('general.daily-sales-summary'), $request->routeIs('general.daily-sales-summary'));
            }
        }
        if (FrontOfficeNavAccess::canViewShiftManagement($user)) {
            $items[] = self::menu('My shift', route('shift.management'), $request->routeIs('shift.management'));
        }

        return $items;
    }

    public static function isSettingsRoute(Request $request): bool
    {
        return $request->routeIs([
            'hotel-details',
            'front-office-hotel-settings',
            'room-types.index',
            'front-office.rooms',
            'additional-charges.index',
            'amenities.index',
            'shift.management',
            'profile',
            'account.hub',
            'hotel-settings.hub',
        ]);
    }

    public static function isReportsRoute(Request $request): bool
    {
        return $request->routeIs([
            'front-office.reports',
            'front-office.guests-report',
            'front-office.daily-accommodation-report',
            'front-office.operational-day-audit',
            'front-office.reservations',
            'general.daily-sales-summary',
            'general.monthly-sales-summary',
        ]);
    }

    public static function isCommunicationRoute(Request $request): bool
    {
        return $request->routeIs('front-office.communications');
    }

    public static function isProformaRoute(Request $request): bool
    {
        return $request->routeIs([
            'front-office.proforma-invoices',
            'front-office.proforma-invoices.create',
            'front-office.proforma-invoices.edit',
            'front-office.proforma-line-defaults',
        ]);
    }

    private static function canAccessPos(User $user): bool
    {
        $mid = Module::where('slug', 'restaurant')->value('id');

        return $mid && $user->hasModuleAccess($mid);
    }

    private static function canAccessStock(User $user): bool
    {
        $mid = Module::where('slug', 'store')->value('id');

        return $mid && $user->hasModuleAccess($mid);
    }

    private static function reservationsTabActive(Request $request, string $tab): bool
    {
        if (! $request->routeIs('front-office.reservations')) {
            return false;
        }

        $current = (string) $request->query('tab', 'all');

        return $current === $tab;
    }

    /**
     * @return array{label: string, href: string, active: bool}
     */
    private static function menu(string $label, string $href, bool $active): array
    {
        return compact('label', 'href', 'active');
    }

    /**
     * @return array{label: string, href: string, icon: string, tone: string, active: bool}
     */
    private static function action(string $label, string $href, string $icon, string $tone, bool $active): array
    {
        return compact('label', 'href', 'icon', 'tone', 'active');
    }
}
