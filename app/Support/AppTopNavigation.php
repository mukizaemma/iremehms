<?php

namespace App\Support;

use App\Models\Hotel;
use App\Models\Module;
use App\Models\User;
use App\Services\OperationalShiftService;
use Illuminate\Http\Request;

/**
 * Builds primary (top bar) and shortcut (icon strip) navigation for the app layout.
 */
final class AppTopNavigation
{
    public static function detectSection(Request $request): string
    {
        $name = $request->route()?->getName() ?? '';

        if ($request->routeIs('front-office.*')
            || ($request->routeIs('module.show') && $request->route('module') === 'front-office')
            || $request->routeIs('room-types.*')
            || $request->routeIs('additional-charges.*')
            || $request->routeIs('amenities.*')
            || $request->routeIs('front-office-hotel-settings')
            || str_contains($name, 'accountant.front-office')) {
            return 'front-office';
        }

        if ($request->routeIs('pos.*')
            || $request->routeIs('restaurant.*')
            || $request->routeIs('menu.*')
            || str_contains($name, 'accountant.pos')) {
            return 'pos';
        }

        if ($request->routeIs('goods.*')
            || $request->routeIs('stock.*')
            || $request->routeIs('suppliers.*')
            || $request->routeIs('purchase.*')
            || str_contains($name, 'accountant.stock')
            || str_contains($name, 'accountant.purchases')
            || str_contains($name, 'accountant.requisitions')) {
            return 'stock';
        }

        if ($request->routeIs('back-office.*')
            || $request->routeIs('subscription')
            || $request->routeIs('hotel-details')
            || $request->routeIs('departments.*')
            || $request->routeIs('shift.management')
            || $request->routeIs('approvals')
            || $request->routeIs('permission-requests.*')
            || $request->routeIs('recovery.*')) {
            return 'back-office';
        }

        if ($request->routeIs('hotel-settings.*')
            || $request->routeIs('users.index')) {
            return 'hotel-settings';
        }

        if ($request->routeIs('accountant.general-report.*')
            || $request->routeIs('general.*')
            || str_contains($name, 'accountant.communications')) {
            return 'reports';
        }

        if ($request->routeIs('system.*') || $request->routeIs('reset-actions')) {
            return 'system';
        }

        return 'general';
    }

    /**
     * @return list<array{label: string, href: string, icon: string, active: bool}>
     */
    public static function primaryItems(
        ?User $user,
        Request $request,
        bool $hasFrontOffice,
        bool $hasRestaurant,
        bool $hasStore,
        bool $showBackend,
        bool $isAccountant,
        bool $isManagerLike,
        bool $isSuperAdmin,
        bool $canViewActivityLog,
    ): array {
        if (! $user) {
            return [];
        }

        $section = self::detectSection($request);

        if (FrontOfficeTopNavigation::usesFrontOfficePrimaryBar($user, $hasFrontOffice, $showBackend, $isAccountant, $isManagerLike)) {
            return FrontOfficeTopNavigation::primaryItems($user, $request, $hasRestaurant, $hasStore, $showBackend);
        }

        $items = [];
        $items[] = self::item('Dashboard', route('dashboard'), 'fa-home', $request->routeIs('dashboard'));

        if ($isAccountant || $isManagerLike) {
            if ($hasRestaurant) {
                $href = $isAccountant ? route('accountant.pos.hub') : route('pos.hub');
                $items[] = self::item('POS', $href, 'fa-utensils', $section === 'pos' || $request->routeIs(['pos.hub', 'accountant.pos.hub']));
            }
            if ($hasFrontOffice) {
                $href = $isAccountant ? route('accountant.front-office.hub') : route('front-office.hub');
                $items[] = self::item('Front office', $href, 'fa-concierge-bell', $section === 'front-office' || $request->routeIs(['front-office.hub', 'accountant.front-office.hub']));
            }
            if ($hasStore) {
                if ($isAccountant) {
                    $items[] = self::item('Stock', route('accountant.stock.hub'), 'fa-archive', str_contains($request->route()->getName() ?? '', 'accountant.stock'));
                    $items[] = self::item('Purchases', route('accountant.purchases.hub'), 'fa-truck-loading', str_contains($request->route()->getName() ?? '', 'accountant.purchases'));
                    $items[] = self::item('Requisitions', route('accountant.requisitions.hub'), 'fa-clipboard-list', str_contains($request->route()->getName() ?? '', 'accountant.requisitions'));
                } else {
                    $items[] = self::item('Stock', route('stock.hub'), 'fa-archive', $section === 'stock' || $request->routeIs('stock.hub'));
                }
            }
            if ($hasFrontOffice && $isAccountant) {
                $items[] = self::item('General report', route('accountant.general-report.hub'), 'fa-file-invoice', $section === 'reports' || $request->routeIs('accountant.general-report.hub'));
                $items[] = self::item('Communications', route('accountant.communications.hub'), 'fa-envelope-open-text', $request->routeIs('accountant.communications.hub'));
            }
            if ($showBackend) {
                $items[] = self::item('Back office', route('back-office.hub'), 'fa-briefcase', $section === 'back-office' || $request->routeIs('back-office.hub'));
                $items[] = self::item('Hotel settings', route('hotel-settings.hub'), 'fa-cog', $section === 'hotel-settings' || $request->routeIs('hotel-settings.hub'));
            }
        } elseif ($showBackend) {
            if ($hasFrontOffice) {
                $items[] = self::item('Front office', route('front-office.hub'), 'fa-concierge-bell', $section === 'front-office' || $request->routeIs('front-office.hub'));
            }
            if ($hasRestaurant) {
                $items[] = self::item('POS', route('pos.hub'), 'fa-utensils', $section === 'pos' || $request->routeIs('pos.hub'));
            }
            if ($hasStore) {
                $items[] = self::item('Stock', route('stock.hub'), 'fa-archive', $section === 'stock' || $request->routeIs('stock.hub'));
            }
            $items[] = self::item('Back office', route('back-office.hub'), 'fa-briefcase', $section === 'back-office' || $request->routeIs('back-office.hub'));
            $items[] = self::item('Hotel settings', route('hotel-settings.hub'), 'fa-cog', $section === 'hotel-settings' || $request->routeIs('hotel-settings.hub'));
        } else {
            if ($hasFrontOffice) {
                $foHome = FrontOfficeNavAccess::canViewRooms($user)
                    ? route('dashboard')
                    : route('front-office.rooms');
                $items[] = self::item('Front office', $foHome, 'fa-concierge-bell', $section === 'front-office');
            }
            if ($hasRestaurant) {
                $items[] = self::item('POS', route('pos.products'), 'fa-utensils', $section === 'pos');
            }
            if ($hasStore) {
                $items[] = self::item('Stock', route('goods.receipts'), 'fa-archive', $section === 'stock');
            }
            if ($isSuperAdmin) {
                $items[] = self::item('Front office hub', route('front-office.hub'), 'fa-th-large', $request->routeIs('front-office.hub'));
            }
        }

        if ($canViewActivityLog && ($isAccountant || $isManagerLike || $showBackend)) {
            $items[] = self::item('Activity log', route('activity-log'), 'fa-history', $request->routeIs('activity-log'));
        }

        if ($isSuperAdmin) {
            $items[] = self::item('System', route('system.configuration'), 'fa-server', $section === 'system');
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    public static function actionItems(
        ?User $user,
        Request $request,
        bool $hasFrontOffice,
        bool $hasRestaurant,
        bool $hasStore,
        bool $showBackend,
        bool $isAccountant,
        bool $isManagerLike,
        bool $isSuperAdmin,
        bool $canProformaNav,
        bool $canWellnessNav,
    ): array {
        if (! $user) {
            return [];
        }

        $section = self::detectSection($request);

        if (FrontOfficeTopNavigation::usesFrontOfficePrimaryBar($user, $hasFrontOffice, $showBackend, $isAccountant, $isManagerLike)) {
            return FrontOfficeTopNavigation::shortcutItems($user, $request);
        }

        if ($isAccountant || $isManagerLike || $showBackend) {
            return match ($section) {
                'front-office' => self::frontOfficeActions($user, $request, $canProformaNav, $canWellnessNav),
                'pos' => self::posActions($user, $request),
                'stock' => self::stockActions($user, $request, $isAccountant),
                'back-office' => self::backOfficeActions($user, $request, $isSuperAdmin, $user->hasPermission('hotel_configure_details'), $user->hasPermission('hotel_manage_users')),
                'hotel-settings' => self::hotelSettingsActions($hasRestaurant, $hasFrontOffice, $hasStore, $user->hasPermission('hotel_manage_users'), $request),
                'reports' => self::reportActions($request),
                'system' => self::systemActions($request),
                default => [],
            };
        }

        $items = [];
        if ($hasFrontOffice && ! $hasRestaurant && ! $hasStore) {
            return self::frontOfficeActions($user, $request, $canProformaNav, $canWellnessNav);
        }
        if ($hasRestaurant && ! $hasFrontOffice && ! $hasStore) {
            return self::posActions($user, $request);
        }
        if ($hasStore && ! $hasFrontOffice && ! $hasRestaurant) {
            return self::stockActions($user, $request, false);
        }

        if ($hasFrontOffice) {
            $items = array_merge($items, self::frontOfficeActions($user, $request, $canProformaNav, $canWellnessNav));
        }
        if ($hasRestaurant) {
            $items = array_merge($items, self::posActions($user, $request));
        }
        if ($hasStore) {
            $items = array_merge($items, self::stockActions($user, $request, false));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function frontOfficeActions(User $user, Request $request, bool $canProforma, bool $canWellness): array
    {
        $foModuleId = Module::where('slug', 'front-office')->value('id');
        $tiles = FrontOfficeHubPermissions::tileVisibility($user, $foModuleId);
        $items = [];

        $map = [
            'dashboard' => ['Dashboard', route('front-office.dashboard'), 'fa-tachometer-alt', 'primary', $request->routeIs('front-office.dashboard'), true],
            'rooms' => ['Rooms', route('front-office.rooms'), 'fa-bed', 'success', $request->routeIs('front-office.rooms'), $tiles['rooms'] ?? false],
            'booking_calendar' => ['Booking calendar', route('module.show', 'front-office'), 'fa-calendar-alt', 'info', $request->routeIs('module.show') && $request->route('module') === 'front-office', $tiles['booking_calendar'] ?? false],
            'new_reservation' => ['New reservation', route('front-office.add-reservation'), 'fa-calendar-plus', 'danger', $request->routeIs('front-office.add-reservation'), $tiles['new_reservation'] ?? false],
            'all_reservations' => ['All reservations', route('front-office.reservations'), 'fa-list', 'primary', $request->routeIs('front-office.reservations'), $tiles['all_reservations'] ?? false],
            'group_reservation' => ['Group reservation', route('front-office.quick-group-booking'), 'fa-users', 'warning', $request->routeIs('front-office.quick-group-booking'), $tiles['group_reservation'] ?? false],
            'pre_arrival' => ['Pre-arrival', route('front-office.self-registered'), 'fa-clipboard-list', 'secondary', $request->routeIs('front-office.self-registered'), $tiles['pre_arrival'] ?? false],
            'guests_report' => ['Guests report', route('front-office.guests-report'), 'fa-list-alt', 'info', $request->routeIs('front-office.guests-report'), $tiles['guests_report'] ?? false],
            'rooms_daily_report' => ['Rooms daily report', route('front-office.daily-accommodation-report'), 'fa-file-invoice-dollar', 'primary', $request->routeIs('front-office.daily-accommodation-report'), $tiles['rooms_daily_report'] ?? false],
            'other_sales_report' => ['Other sales report', route('front-office.reports'), 'fa-chart-bar', 'warning', $request->routeIs('front-office.reports'), $tiles['other_sales_report'] ?? false],
            'communication' => ['Communication', route('front-office.communications'), 'fa-envelope-open-text', 'info', $request->routeIs('front-office.communications'), $tiles['communication'] ?? false],
            'proforma' => ['Proforma invoices', route('front-office.proforma-invoices'), 'fa-file-signature', 'secondary', $request->routeIs(['front-office.proforma-invoices', 'front-office.proforma-invoices.create', 'front-office.proforma-invoices.edit']), $canProforma && ($tiles['proforma'] ?? false)],
            'wellness' => ['Wellness', route('front-office.wellness'), 'fa-spa', 'success', $request->routeIs('front-office.wellness'), $canWellness && ($tiles['wellness'] ?? false)],
            'restaurant' => ['Restaurant', route('front-office.restaurant'), 'fa-utensils', 'warning', $request->routeIs('front-office.restaurant'), $tiles['restaurant'] ?? false],
            'shift_management' => ['Shift management', route('shift.management'), 'fa-clock', 'dark', $request->routeIs('shift.management'), $tiles['shift_management'] ?? false],
        ];

        foreach ($map as $key => [$label, $href, $icon, $tone, $active, $visible]) {
            if ($key === 'dashboard') {
                if (FrontOfficeNavAccess::canViewRooms($user)) {
                    $items[] = self::action($label, $href, $icon, $tone, $active);
                }
                continue;
            }
            if ($visible) {
                $items[] = self::action($label, $href, $icon, $tone, $active);
            }
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function posActions(User $user, Request $request): array
    {
        $effectiveSlug = $user->getEffectiveRole()?->slug;
        $canReports = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('reports_view_all')
            || in_array($effectiveSlug, ['waiter', 'cashier'], true)
            || $user->isRestaurantManager();

        $showReceiptMod = $user->hasPermission('pos_approve_receipt_modification') || $user->canNavigateModules();

        $items = [
            self::action('POS', route('pos.products'), 'fa-cash-register', 'primary', $request->routeIs('pos.products')),
            self::action('Orders', route('pos.orders'), 'fa-shopping-cart', 'info', $request->routeIs('pos.orders')),
            self::action('Invoices', route('pos.order-history'), 'fa-file-invoice', 'secondary', $request->routeIs('pos.order-history')),
            self::action('Void requests', route('pos.void-requests'), 'fa-ban', 'danger', $request->routeIs('pos.void-requests')),
            self::action('My sales', route('pos.my-sales'), 'fa-chart-line', 'success', $request->routeIs('pos.my-sales')),
        ];

        if ($showReceiptMod) {
            $items[] = self::action('Receipt modification', route('pos.receipt-modification-requests'), 'fa-edit', 'warning', $request->routeIs('pos.receipt-modification-requests'));
        }
        if ($canReports) {
            $items[] = self::action('POS reports', route('pos.reports'), 'fa-chart-pie', 'primary', $request->routeIs('pos.reports'));
        }
        if ($user->canNavigateModules() || $user->isRestaurantManager()) {
            $items[] = self::action('Menu management', route('menu.items'), 'fa-utensils', 'warning', $request->routeIs('menu.*'));
            $items[] = self::action('Aging orders', route('pos.aging-orders'), 'fa-clock', 'dark', $request->routeIs('pos.aging-orders'));
            $items[] = self::action('Orders & stations', route('pos.orders-stations-overview'), 'fa-th-large', 'info', $request->routeIs('pos.orders-stations-overview'));
            $items[] = self::action('Waiters', route('restaurant.waiters'), 'fa-user-tie', 'secondary', $request->routeIs('restaurant.waiters'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function stockActions(User $user, Request $request, bool $isAccountant): array
    {
        $items = [
            self::action('Stock-in', route('goods.receipts'), 'fa-truck-loading', 'primary', $request->routeIs('goods.receipts')),
            self::action('Stock-out', route('stock.out'), 'fa-truck', 'danger', $request->routeIs('stock.out')),
            self::action('Requisitions', route('stock.requisitions'), 'fa-clipboard-list', 'info', $request->routeIs('stock.requisitions')),
            self::action('Requests', route('stock.requests'), 'fa-paper-plane', 'warning', $request->routeIs('stock.requests')),
            self::action('Movements', route('stock.movements'), 'fa-exchange-alt', 'secondary', $request->routeIs('stock.movements')),
        ];

        if ($user->canManageStockItems()) {
            $items[] = self::action('Stock management', route('stock.management'), 'fa-boxes', 'success', $request->routeIs('stock.management'));
        }
        if ($user->canManageStockLocations()) {
            $items[] = self::action('Stock locations', route('stock.locations'), 'fa-map-marker-alt', 'info', $request->routeIs('stock.locations'));
        }
        if ($user->canViewStockReports()) {
            $items[] = self::action('Stock reports', route('stock.reports'), 'fa-chart-bar', 'primary', $request->routeIs('stock.reports'));
            $items[] = self::action('Opening / closing', route('stock.opening-closing-report'), 'fa-box-open', 'secondary', $request->routeIs('stock.opening-closing-report'));
            $items[] = self::action('Stock by location', route('stock.location-activity-report'), 'fa-warehouse', 'dark', $request->routeIs('stock.location-activity-report'));
            $items[] = self::action('General report', route('general.monthly-sales-summary'), 'fa-file-invoice', 'warning', $request->routeIs('general.monthly-sales-summary'));
        }
        if ($user->canNavigateModules() || $isAccountant) {
            $items[] = self::action('Suppliers', route('suppliers.index'), 'fa-industry', 'info', $request->routeIs('suppliers.index'));
            $items[] = self::action('Purchase requisitions', route('purchase.requisitions'), 'fa-file-invoice', 'primary', $request->routeIs('purchase.requisitions'));
            $items[] = self::action('Stock dashboard', route('stock.dashboard'), 'fa-th-large', 'success', $request->routeIs('stock.dashboard'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function backOfficeActions(User $user, Request $request, bool $isSuperAdmin, bool $canConfigure, bool $canManageUsers): array
    {
        $items = [];
        if ($isSuperAdmin) {
            $items[] = self::action('Subscription', route('subscription'), 'fa-credit-card', 'primary', $request->routeIs('subscription'));
        }
        if ($canConfigure) {
            $items[] = self::action('Hotel details', route('hotel-details'), 'fa-hotel', 'info', $request->routeIs('hotel-details'));
        }
        if (OperationalShiftService::userCanAccessShiftManagementPage($user)) {
            $items[] = self::action('Shift management', route('shift.management'), 'fa-clock', 'warning', $request->routeIs('shift.management'));
        }
        if ($canConfigure || $canManageUsers) {
            $items[] = self::action('Departments', route('departments.index'), 'fa-building', 'secondary', $request->routeIs('departments.index'));
        }
        $items[] = self::action('Approvals', route('approvals'), 'fa-check-double', 'success', $request->routeIs('approvals'));
        $items[] = self::action('Permission requests', route('permission-requests.index'), 'fa-key', 'danger', $request->routeIs('permission-requests.index'));
        $items[] = self::action('Recovery', route('recovery.dashboard'), 'fa-hand-holding-usd', 'primary', $request->routeIs('recovery.dashboard'));

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function hotelSettingsActions(bool $hasRestaurant, bool $hasFrontOffice, bool $hasStore, bool $canManageUsers, Request $request): array
    {
        $items = [];
        if ($hasRestaurant) {
            $items[] = self::action('Restaurant tables', route('pos.tables'), 'fa-utensils', 'primary', $request->routeIs('pos.tables'));
            $items[] = self::action('Menu items', route('menu.items'), 'fa-hamburger', 'warning', $request->routeIs('menu.items'));
            $items[] = self::action('Menu categories', route('menu.categories'), 'fa-folder-open', 'info', $request->routeIs('menu.categories'));
            $items[] = self::action('Order stations', route('restaurant.preparation-stations'), 'fa-fire', 'danger', $request->routeIs(['restaurant.preparation-stations', 'restaurant.posting-stations']));
        }
        if ($hasFrontOffice) {
            $items[] = self::action('Room types', route('room-types.index'), 'fa-door-open', 'success', $request->routeIs('room-types.index'));
            $items[] = self::action('Additional charges', route('additional-charges.index'), 'fa-plus-circle', 'primary', $request->routeIs('additional-charges.index'));
            $items[] = self::action('Amenities', route('amenities.index'), 'fa-star', 'warning', $request->routeIs('amenities.index'));
            $items[] = self::action('FO settings', route('front-office-hotel-settings'), 'fa-sliders-h', 'info', $request->routeIs('front-office-hotel-settings'));
        }
        if ($hasStore) {
            $items[] = self::action('Stock management', route('stock.management'), 'fa-boxes', 'secondary', $request->routeIs('stock.management'));
            $items[] = self::action('Deductions', route('stock.pending-deductions'), 'fa-minus-circle', 'danger', $request->routeIs('stock.pending-deductions'));
        }
        if ($canManageUsers) {
            $items[] = self::action('Users', route('users.index'), 'fa-users', 'dark', $request->routeIs('users.index'));
        }

        return $items;
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function reportActions(Request $request): array
    {
        return [
            self::action('Monthly sales', route('general.monthly-sales-summary'), 'fa-file-invoice', 'primary', $request->routeIs('general.monthly-sales-summary')),
            self::action('Daily sales', route('general.daily-sales-summary'), 'fa-chart-line', 'info', $request->routeIs('general.daily-sales-summary')),
            self::action('FO reports', route('front-office.reports'), 'fa-chart-bar', 'warning', $request->routeIs('front-office.reports')),
            self::action('Guests report', route('front-office.guests-report'), 'fa-list-alt', 'success', $request->routeIs('front-office.guests-report')),
        ];
    }

    /**
     * @return list<array{label: string, href: string, icon: string, tone: string, active: bool}>
     */
    private static function systemActions(Request $request): array
    {
        return [
            self::action('Configuration', route('system.configuration'), 'fa-cog', 'primary', $request->routeIs('system.configuration')),
            self::action('Reset actions', route('reset-actions'), 'fa-trash-alt', 'danger', $request->routeIs('reset-actions')),
        ];
    }

    public static function businessDateLabel(): string
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return now()->format('l j, F Y');
        }

        $today = Hotel::getTodayForHotel();

        return \Carbon\Carbon::parse($today, $hotel->getTimezone())->format('l j, F Y');
    }

    /**
     * @return array{arrivals: int, departures: int}
     */
    public static function arrivalDepartureCounts(): array
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return ['arrivals' => 0, 'departures' => 0];
        }

        $today = Hotel::getTodayForHotel();

        $arrivals = \App\Models\Reservation::where('hotel_id', $hotel->id)
            ->where('check_in_date', $today)
            ->where('status', \App\Models\Reservation::STATUS_CONFIRMED)
            ->count();

        $departures = \App\Models\Reservation::where('hotel_id', $hotel->id)
            ->where('check_out_date', $today)
            ->whereIn('status', [\App\Models\Reservation::STATUS_CHECKED_IN, \App\Models\Reservation::STATUS_CONFIRMED])
            ->count();

        return ['arrivals' => $arrivals, 'departures' => $departures];
    }

    /**
     * @return array{label: string, href: string, icon: string, active: bool}
     */
    private static function item(string $label, string $href, string $icon, bool $active): array
    {
        return compact('label', 'href', 'icon', 'active');
    }

    /**
     * @return array{label: string, href: string, icon: string, tone: string, active: bool}
     */
    private static function action(string $label, string $href, string $icon, string $tone, bool $active): array
    {
        return compact('label', 'href', 'icon', 'tone', 'active');
    }
}
