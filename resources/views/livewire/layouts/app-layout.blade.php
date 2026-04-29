@php
    // Always get fresh user data to ensure profile image is up to date
    $user = Auth::user();
    $hotel = \App\Models\Hotel::getHotel();
    $hotelLogoUrl = $hotel && $hotel->logo ? \Illuminate\Support\Facades\Storage::url($hotel->logo) : null;
    // Hotel app: do not show Ireme branding to hotel users (Director, GM, etc.)
    $sidebarBrandLogoUrl = $hotelLogoUrl;
    $defaultUserAvatarUrl = $hotelLogoUrl ?: asset('admintemplates/img/user.jpg');
    $modules = $modules ?? ($user ? $user->getAccessibleModules() : collect());
    $selectedModule = $selectedModule ?? session('selected_module', '');
    // Single module: no dropdown; use that module as context so sidebar shows its menu directly
    if ($modules->count() === 1) {
        if (! $selectedModule) {
            $selectedModule = $modules->first()->slug;
            session(['selected_module' => $selectedModule]);
        }
    }
    $availableDepartments = $availableDepartments ?? collect();
    $availableUsers = $availableUsers ?? collect();
    // Effective role (for Super Admin "view as" another role)
    $effectiveRole = $user ? $user->getEffectiveRole() : null;
    $isEffectiveSuperAdmin = $effectiveRole && $effectiveRole->slug === 'super-admin';
    $isEffectiveManager = $effectiveRole && $effectiveRole->slug === 'manager';
    $isEffectiveDirector = $effectiveRole && $effectiveRole->slug === 'director';
    $isEffectiveGeneralManager = $effectiveRole && $effectiveRole->slug === 'general-manager';
    $canNavigateModules = $user && $user->canNavigateModules();
    $canManageHotelUsers = $user && $user->hasPermission('hotel_manage_users');
    $canConfigureHotel = $user && $user->hasPermission('hotel_configure_details');
    // Activity log: everyone sees their own actions; management also filters by user/module (see ActivityLogViewer).
    $canViewActivityLogNav = (bool) $user;
    $canProformaNav = $user && $user->hasPermission('fo_proforma_manage');
    $canWellnessNav = $user && $user->hasPermission('fo_wellness_manage');

    $hasRestaurant = $modules->contains('slug', 'restaurant');
    $hasStore = $modules->contains('slug', 'store');
    $hasFrontOffice = $modules->contains('slug', 'front-office');
    // Backend section (Subscription, Reports, Rooms management, Additional charges, etc.) only for roles that can configure/navigate modules
    $showBackend = $canNavigateModules;

    $moduleSlugs = $modules->pluck('slug');
    if ($selectedModule && ! $moduleSlugs->contains($selectedModule)) {
        $selectedModule = $modules->count() === 1 ? $modules->first()->slug : '';
        session(['selected_module' => $selectedModule]);
    }
    $isEffectiveWaiter = $effectiveRole && $effectiveRole->slug === 'waiter';
    $isEffectiveReceptionist = $effectiveRole && $effectiveRole->slug === 'receptionist';
    $isEffectiveCashier = $effectiveRole && $effectiveRole->slug === 'cashier';
    $isEffectiveStoreKeeper = $effectiveRole && $effectiveRole->slug === 'store-keeper';
    $isEffectiveAccountant = $effectiveRole && $effectiveRole->slug === 'accountant';
    $isEffectiveManagerLike = $effectiveRole && in_array($effectiveRole->slug, ['manager', 'director', 'general-manager', 'hotel-admin'], true);
    $isEffectiveRestaurantManager = $effectiveRole && $effectiveRole->slug === 'restaurant-manager';
    $activePreparationStations = \App\Models\PreparationStation::getActiveForPos();

    $canViewPosReportsSidebar = $hasRestaurant && (
        $user->isSuperAdmin()
        || $user->canNavigateModules()
        || $user->hasPermission('pos_audit')
        || $user->hasPermission('pos_full_oversight')
        || $user->hasPermission('reports_view_all')
        || $isEffectiveWaiter
        || $isEffectiveCashier
        || $isEffectiveRestaurantManager
    );

    $canViewPosMySalesSidebar = $canViewPosReportsSidebar;
    // Super Admin role switcher (real role only)
    $allRolesForSwitch = $user && $user->isSuperAdmin() ? \App\Models\Role::where('slug', '!=', 'department-user')->orderBy('name')->get() : collect();
    $actingAsRoleId = session('acting_as_role_id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Hotel Management') }} - @yield('title', 'Dashboard')</title>

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="{{ asset('admintemplates/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admintemplates/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css') }}" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="{{ asset('admintemplates/css/bootstrap.min.css') }}" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="{{ asset('admintemplates/css/style.css') }}" rel="stylesheet">
    <style>
        /* Remove gap between sidebar and content */
        .sidebar { padding-right: 0 !important; }
        .content { margin-left: 250px; }
        @media (max-width: 991.98px) { .content { margin-left: 0; } }
        /* Sidebar brand & user: prevent overflow */
        .sidebar .navbar-brand { min-width: 0; max-width: 100%; }
        .sidebar .sidebar-hotel-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 1rem; line-height: 1.3; }
        .sidebar .sidebar-user-role-line { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.8rem; }
        /* Stock Management submenu chevron */
        .sidebar [data-bs-toggle="collapse"][aria-expanded="true"] .collapse-chevron { transform: rotate(180deg); }
        .sidebar .transition-all { transition: transform 0.2s ease; }
        /* Print: hide app chrome so report pages (general report, FO reports, etc.) print content only */
        @media print {
            #spinner,
            .sidebar,
            .sidebar-toggler,
            .content > nav.navbar,
            .back-to-top {
                display: none !important;
            }
            .content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            body {
                background: #fff !important;
            }
            /* Full width for embedded general report (dashboard) and standalone report routes */
            body:has(.general-report-print-root) .content > div.container-fluid.pt-4.px-4:first-of-type {
                padding: 0 !important;
            }
        }
    </style>

    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

    @livewireStyles
    @stack('styles')
</head>
<body>
    <div class="container-xxl position-relative bg-white d-flex p-0">
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Sidebar Start -->
        <div class="sidebar pb-3">
            <nav class="navbar bg-light navbar-light">
                <a href="{{ route('dashboard') }}" class="navbar-brand mx-4 mb-2 d-flex flex-column align-items-start text-decoration-none" style="min-width: 0;">
                    <div class="d-flex align-items-center w-100" style="min-width: 0;">
                        @if($sidebarBrandLogoUrl)
                            <img src="{{ $sidebarBrandLogoUrl }}" alt="{{ $hotel ? $hotel->name : config('app.name') }}" class="sidebar-brand-mark" style="max-height: 36px; width: auto; max-width: 100%; object-fit: contain;">
                        @else
                            <span class="text-primary fw-bold sidebar-hotel-name" title="{{ $hotel ? $hotel->name : config('app.name') }}">{{ $hotel ? $hotel->name : config('app.name') }}</span>
                        @endif
                    </div>
                    <span class="small text-muted sidebar-user-role-line mt-1" title="{{ $user->name }} · {{ $effectiveRole ? $effectiveRole->name : ($user->role->name ?? 'No Role') }}">{{ $user->name }} · {{ $effectiveRole ? $effectiveRole->name : ($user->role->name ?? 'No Role') }}</span>
                </a>
                <div class="navbar-nav w-100">
                    <a href="{{ route('dashboard') }}" class="nav-item nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>

                    @if($isEffectiveAccountant || $isEffectiveManagerLike)
                        @if($hasRestaurant)
                            <a href="{{ route('accountant.pos.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.pos.hub') ? 'active' : '' }}"><i class="fa fa-utensils me-2"></i>POS</a>
                        @endif

                        @if($hasFrontOffice)
                            <a href="{{ route('accountant.front-office.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.front-office.hub') ? 'active' : '' }}"><i class="fa fa-concierge-bell me-2"></i>Front office</a>
                        @endif

                        @if($hasStore)
                            <a href="{{ route('accountant.stock.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.stock.hub') ? 'active' : '' }}"><i class="fa fa-archive me-2"></i>Stock</a>
                            <a href="{{ route('accountant.purchases.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.purchases.hub') ? 'active' : '' }}"><i class="fa fa-truck-loading me-2"></i>Purchasses</a>
                            <a href="{{ route('accountant.requisitions.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.requisitions.hub') ? 'active' : '' }}"><i class="fa fa-clipboard-list me-2"></i>Requisitions</a>
                        @endif

                        @if($hasFrontOffice)
                            <a href="{{ route('accountant.general-report.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.general-report.hub') ? 'active' : '' }}"><i class="fa fa-file-invoice me-2"></i>General report</a>
                            <a href="{{ route('accountant.communications.hub') }}" class="nav-item nav-link {{ request()->routeIs('accountant.communications.hub') ? 'active' : '' }}"><i class="fa fa-envelope-open-text me-2"></i>Communications</a>
                        @endif

                        @if($showBackend)
                            <a href="{{ route('back-office.hub') }}" class="nav-item nav-link {{ request()->routeIs('back-office.hub') ? 'active' : '' }}"><i class="fa fa-briefcase me-2"></i>Back Office</a>
                            <a href="{{ route('hotel-settings.hub') }}" class="nav-item nav-link {{ request()->routeIs('hotel-settings.hub') ? 'active' : '' }}"><i class="fa fa-cog me-2"></i>Hotel Settings</a>
                        @endif

                        @if($canViewActivityLogNav)
                            <a href="{{ route('activity-log') }}" class="nav-item nav-link {{ request()->routeIs('activity-log') ? 'active' : '' }}"><i class="fa fa-history me-2"></i>Activity log</a>
                        @endif
                        <a href="{{ route('account.hub') }}" class="nav-item nav-link {{ request()->routeIs('account.hub') ? 'active' : '' }}"><i class="fa fa-user me-2"></i>My Account</a>
                    @endif

                    {{-- Manager / director / GM / hotel-admin: compact sidebar → hub grids (tablet-friendly). Stock sidebar omits stock.management / pending-deductions (Hotel settings). --}}
                    @if(! ($isEffectiveAccountant || $isEffectiveManagerLike) && $showBackend)
                        @php
                            $foNavActive = $hasFrontOffice && (
                                request()->routeIs('front-office.*')
                                || (request()->routeIs('module.show') && request()->route('module') === 'front-office')
                            );
                            $posNavActive = $hasRestaurant && (
                                (request()->routeIs('pos.*') && ! request()->routeIs('pos.tables'))
                                || request()->routeIs('restaurant.*')
                            );
                            $stockNavActive = $hasStore && (
                                request()->routeIs('goods.receipts')
                                || request()->routeIs('suppliers.index')
                                || request()->routeIs('purchase.requisitions')
                                || (
                                    request()->routeIs('stock.*')
                                    && ! request()->routeIs('stock.management')
                                    && ! request()->routeIs('stock.pending-deductions')
                                )
                            );
                            $backOfficeNavActive = request()->routeIs('back-office.hub')
                                || ($isEffectiveSuperAdmin && request()->routeIs('subscription'))
                                || request()->routeIs('hotel-details')
                                || request()->routeIs('departments.index')
                                || request()->routeIs('shift.management')
                                || request()->routeIs('approvals')
                                || request()->routeIs('permission-requests.index')
                                || request()->routeIs('recovery.dashboard');
                            $hotelSettingsNavActive = request()->routeIs('hotel-settings.hub')
                                || request()->routeIs('pos.tables')
                                || request()->routeIs('room-types.index')
                                || request()->routeIs('additional-charges.index')
                                || request()->routeIs('amenities.index')
                                || request()->routeIs('front-office-hotel-settings')
                                || request()->routeIs('restaurant.preparation-stations')
                                || request()->routeIs('restaurant.posting-stations')
                                || request()->routeIs('stock.pending-deductions')
                                || request()->routeIs('stock.management')
                                || request()->routeIs('menu.items')
                                || request()->routeIs('menu.item-types')
                                || request()->routeIs('menu.categories')
                                || request()->routeIs('menu.bill-of-menu')
                                || request()->routeIs('users.index');
                            $accountNavActive = request()->routeIs('account.hub')
                                || request()->routeIs('profile')
                                || request()->routeIs('activity-log');
                        @endphp
                        @if($hasFrontOffice)
                            <a href="{{ route('front-office.hub') }}" class="nav-item nav-link mt-2 {{ $foNavActive ? 'active' : '' }}"><i class="fa fa-concierge-bell me-2"></i>Front office</a>
                        @endif
                        @if($hasRestaurant)
                            <a href="{{ route('pos.hub') }}" class="nav-item nav-link {{ $posNavActive ? 'active' : '' }}"><i class="fa fa-utensils me-2"></i>POS</a>
                        @endif
                        @if($hasStore)
                            <a href="{{ route('stock.hub') }}" class="nav-item nav-link {{ $stockNavActive ? 'active' : '' }}"><i class="fa fa-archive me-2"></i>Stock</a>
                        @endif
                        <a href="{{ route('back-office.hub') }}" class="nav-item nav-link {{ $backOfficeNavActive ? 'active' : '' }}"><i class="fa fa-briefcase me-2"></i>Back office</a>
                        <a href="{{ route('hotel-settings.hub') }}" class="nav-item nav-link {{ $hotelSettingsNavActive ? 'active' : '' }}"><i class="fa fa-cog me-2"></i>Hotel settings</a>
                        @if($canViewActivityLogNav)
                            <a href="{{ route('activity-log') }}" class="nav-item nav-link {{ request()->routeIs('activity-log') ? 'active' : '' }}"><i class="fa fa-history me-2"></i>Activity log</a>
                        @endif
                        <a href="{{ route('account.hub') }}" class="nav-item nav-link {{ $accountNavActive ? 'active' : '' }}"><i class="fa fa-user me-2"></i>My account</a>
                    @endif

                    {{-- Front office: super admin (hub) or receptionist (flat links) --}}
                    @if(! ($isEffectiveAccountant || $isEffectiveManagerLike) && ! $showBackend && $hasFrontOffice)
                        @if($isEffectiveSuperAdmin)
                            <a href="{{ route('front-office.hub') }}" class="nav-item nav-link mt-2 {{ request()->routeIs('front-office.*') || (request()->routeIs('module.show') && request()->route('module') === 'front-office') ? 'active' : '' }}"><i class="fa fa-concierge-bell me-2"></i>Front Office</a>
                        @else
                            <a href="{{ route('front-office.rooms') }}" class="nav-item nav-link mt-2 {{ request()->routeIs('front-office.rooms') ? 'active' : '' }}"><i class="fa fa-bed me-2"></i>Rooms</a>
                            <a href="{{ route('module.show', 'front-office') }}" class="nav-item nav-link {{ request()->routeIs('module.show') && request()->route('module') === 'front-office' ? 'active' : '' }}"><i class="fa fa-calendar-alt me-2"></i>Booking calendar</a>
                            <a href="{{ route('front-office.add-reservation') }}" class="nav-item nav-link {{ request()->routeIs('front-office.add-reservation') ? 'active' : '' }}"><i class="fa fa-calendar-plus me-2"></i>New reservation</a>
                            <a href="{{ route('front-office.reservations') }}" class="nav-item nav-link {{ request()->routeIs('front-office.reservations') ? 'active' : '' }}"><i class="fa fa-list me-2"></i>All reservations</a>
                            <a href="{{ route('front-office.quick-group-booking') }}" class="nav-item nav-link {{ request()->routeIs('front-office.quick-group-booking') ? 'active' : '' }}"><i class="fa fa-users me-2"></i>Group reservation</a>
                            <a href="{{ route('front-office.self-registered') }}" class="nav-item nav-link {{ request()->routeIs('front-office.self-registered') ? 'active' : '' }}"><i class="fa fa-clipboard-list me-2"></i>Pre-arrival</a>
                            <a href="{{ route('front-office.guests-report') }}" class="nav-item nav-link {{ request()->routeIs('front-office.guests-report') ? 'active' : '' }}"><i class="fa fa-list-alt me-2"></i>Guests report</a>
                            <a href="{{ route('front-office.daily-accommodation-report') }}" class="nav-item nav-link {{ request()->routeIs('front-office.daily-accommodation-report') ? 'active' : '' }}"><i class="fa fa-file-invoice-dollar me-2"></i>Rooms daily report</a>
                            <a href="{{ route('front-office.reports') }}" class="nav-item nav-link {{ request()->routeIs('front-office.reports') ? 'active' : '' }}"><i class="fa fa-chart-bar me-2"></i>Other sales report</a>
                            <a href="{{ route('front-office.communications') }}" class="nav-item nav-link {{ request()->routeIs('front-office.communications') ? 'active' : '' }}"><i class="fa fa-envelope-open-text me-2"></i>Communication</a>
                            @if($canProformaNav)
                                <a href="{{ route('front-office.proforma-invoices') }}" class="nav-item nav-link {{ request()->routeIs(['front-office.proforma-invoices', 'front-office.proforma-invoices.create', 'front-office.proforma-invoices.edit']) ? 'active' : '' }}"><i class="fa fa-file-signature me-2"></i>Proforma invoices</a>
                            @endif
                            @if($canWellnessNav)
                                <a href="{{ route('front-office.wellness') }}" class="nav-item nav-link {{ request()->routeIs('front-office.wellness') ? 'active' : '' }}"><i class="fa fa-spa me-2"></i>Wellness</a>
                            @endif
                            @if($canViewActivityLogNav)
                                <a href="{{ route('activity-log') }}" class="nav-item nav-link {{ request()->routeIs('activity-log') ? 'active' : '' }}"><i class="fa fa-history me-2"></i>Activity log</a>
                            @endif
                            <a href="{{ route('profile') }}" class="nav-item nav-link {{ request()->routeIs('profile') ? 'active' : '' }}"><i class="fa fa-user me-2"></i>My account</a>
                        @endif
                    @endif

                    {{-- Stock: non-manager roles only (managers use Stock hub) --}}
                    @if(! ($isEffectiveAccountant || $isEffectiveManagerLike) && ! $showBackend && $hasStore)
                        <div class="nav-item small text-muted px-3 py-2 mt-2">Stock</div>
                        @if($user && $user->canManageStockItems())
                            <a href="{{ route('stock.management') }}" class="nav-item nav-link {{ request()->routeIs('stock.management') ? 'active' : '' }}"><i class="fa fa-boxes me-2"></i>Stock management</a>
                        @endif
                        @if($user && $user->canManageStockLocations())
                            <a href="{{ route('stock.locations') }}" class="nav-item nav-link {{ request()->routeIs('stock.locations') ? 'active' : '' }}"><i class="fa fa-map-marker-alt me-2"></i>Stock locations</a>
                        @endif
                        <a href="{{ route('goods.receipts') }}" class="nav-item nav-link {{ request()->routeIs('goods.receipts') ? 'active' : '' }}"><i class="fa fa-truck-loading me-2"></i>Stock-in</a>
                        <a href="{{ route('stock.out') }}" class="nav-item nav-link {{ request()->routeIs('stock.out') ? 'active' : '' }}"><i class="fa fa-truck me-2"></i>Stock-out</a>
                        <a href="{{ route('stock.requisitions') }}" class="nav-item nav-link {{ request()->routeIs('stock.requisitions') ? 'active' : '' }}"><i class="fa fa-clipboard-list me-2"></i>Stock requisitions</a>
                        <a href="{{ route('stock.requests') }}" class="nav-item nav-link {{ request()->routeIs('stock.requests') ? 'active' : '' }}"><i class="fa fa-paper-plane me-2"></i>Stock requests</a>
                        <a href="{{ route('stock.movements') }}" class="nav-item nav-link {{ request()->routeIs('stock.movements') ? 'active' : '' }}"><i class="fa fa-exchange-alt me-2"></i>Stock movements</a>

                        @php
                            $canViewStockReportsSidebar = $user && $user->canViewStockReports();
                        @endphp
                        @if($canViewStockReportsSidebar)
                            <a href="{{ route('stock.reports') }}#stock-summary-inventory-category" class="nav-item nav-link {{ request()->routeIs('stock.reports') ? 'active' : '' }}"><i class="fa fa-chart-bar me-2"></i>Stock reports</a>
                            <a href="{{ route('stock.opening-closing-report') }}" class="nav-item nav-link {{ request()->routeIs('stock.opening-closing-report') ? 'active' : '' }}"><i class="fa fa-box-open me-2"></i>Opening / closing report</a>
                            <a href="{{ route('stock.location-activity-report') }}" class="nav-item nav-link {{ request()->routeIs('stock.location-activity-report') ? 'active' : '' }}"><i class="fa fa-warehouse me-2"></i>Stock by location</a>
                            <a href="{{ route('general.monthly-sales-summary') }}" class="nav-item nav-link {{ request()->routeIs('general.monthly-sales-summary') ? 'active' : '' }}"><i class="fa fa-file-invoice me-2"></i>General report</a>
                        @endif
                    @endif

                    {{-- POS: non-manager roles (managers use POS hub) --}}
                    @if(! ($isEffectiveAccountant || $isEffectiveManagerLike) && ! $showBackend && $hasRestaurant)
                        <div class="nav-item small text-muted px-3 py-2 mt-2">POS</div>
                        <a href="{{ route('pos.products') }}" class="nav-item nav-link {{ request()->routeIs('pos.products') ? 'active' : '' }}"><i class="fa fa-cash-register me-2"></i>POS</a>
                        <a href="{{ route('pos.orders') }}" class="nav-item nav-link {{ request()->routeIs('pos.orders') ? 'active' : '' }}"><i class="fa fa-shopping-cart me-2"></i>Orders</a>
                        <a href="{{ route('pos.order-history') }}" class="nav-item nav-link {{ request()->routeIs('pos.order-history') ? 'active' : '' }}"><i class="fa fa-file-invoice me-2"></i>Invoices</a>
                        <a href="{{ route('pos.void-requests') }}" class="nav-item nav-link {{ request()->routeIs('pos.void-requests') ? 'active' : '' }}"><i class="fa fa-ban me-2"></i>Void requests</a>
                        @if(Auth::user()->hasPermission('pos_approve_receipt_modification') || Auth::user()->canNavigateModules())
                            <a href="{{ route('pos.receipt-modification-requests') }}" class="nav-item nav-link {{ request()->routeIs('pos.receipt-modification-requests') ? 'active' : '' }}"><i class="fa fa-edit me-2"></i>Receipt modification</a>
                        @endif
                        <a href="{{ route('pos.my-sales') }}" class="nav-item nav-link {{ request()->routeIs('pos.my-sales') ? 'active' : '' }}"><i class="fa fa-chart-line me-2"></i>My sales</a>
                        @if($canViewPosMySalesSidebar)
                            <a href="{{ route('pos.my-sales') }}" class="nav-item nav-link {{ request()->routeIs('pos.my-sales') ? 'active' : '' }}"><i class="fa fa-chart-line me-2"></i>My sales</a>
                        @endif
                        @if($canViewPosReportsSidebar)
                            <a href="{{ route('pos.reports') }}" class="nav-item nav-link {{ request()->routeIs('pos.reports') ? 'active' : '' }}"><i class="fa fa-file-invoice me-2"></i>My reports</a>
                        @endif
                    @endif

                    @if($isEffectiveSuperAdmin)
                        <div class="nav-item small text-muted px-3 py-2 mt-2">System</div>
                        <a href="{{ route('system.configuration') }}" class="nav-item nav-link {{ request()->routeIs('system.configuration') ? 'active' : '' }}"><i class="fa fa-cog me-2"></i>System configuration</a>
                        <a href="{{ route('reset-actions') }}" class="nav-item nav-link {{ request()->routeIs('reset-actions') ? 'active' : '' }}"><i class="fa fa-trash-alt me-2"></i>Reset Actions</a>
                    @endif

                    @if(! ($isEffectiveAccountant || $isEffectiveManagerLike) && ! $hasFrontOffice)
                        @if($canViewActivityLogNav)
                            <a href="{{ route('activity-log') }}" class="nav-item nav-link {{ request()->routeIs('activity-log') ? 'active' : '' }}"><i class="fa fa-history me-2"></i>Activity log</a>
                        @endif
                        <a href="{{ route('profile') }}" class="nav-item nav-link {{ request()->routeIs('profile') ? 'active' : '' }}"><i class="fa fa-user me-2"></i>My account</a>
                    @endif

                    @if($user && \App\Services\OperationalShiftService::userCanAccessShiftManagementPage($user))
                        <a href="{{ route('shift.management') }}" class="nav-item nav-link {{ request()->routeIs('shift.management') ? 'active' : '' }}">
                            <i class="fa fa-clock me-2"></i>Shift management
                        </a>
                    @endif
                </div>
            </nav>
        </div>
        <!-- Sidebar End -->

        <!-- Content Start -->
        <div class="content">
            <!-- Navbar Start -->
            <nav class="navbar navbar-expand bg-light navbar-light sticky-top px-4 py-0">
                <a href="{{ route('dashboard') }}" class="navbar-brand d-flex d-lg-none me-4">
                    <h2 class="text-primary mb-0"><i class="fa fa-hashtag"></i></h2>
                </a>
                <a href="#" class="sidebar-toggler flex-shrink-0">
                    <i class="fa fa-bars"></i>
                </a>
                <form class="d-none d-md-flex ms-4">
                    <input class="form-control border-0" type="search" placeholder="Search">
                </form>
                @if($hotel)
                    <div class="nav-item px-3 py-2 border-start border-end border-secondary border-opacity-25">
                        <span class="text-muted small">Hotel</span>
                        <span class="d-none d-lg-inline fw-semibold text-dark ms-1" title="Hotel code: {{ $hotel->hotel_code ?? $hotel->id }}">{{ $hotel->name }} <span class="text-primary">#{{ $hotel->hotel_code ?? $hotel->id }}</span></span>
                        <span class="d-lg-none fw-semibold text-dark ms-1">#{{ $hotel->hotel_code ?? $hotel->id }}</span>
                    </div>
                @endif
                <div class="navbar-nav align-items-center ms-auto">
                    @auth
                        @livewire('notification-bell')
                    @endauth
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img 
                                class="rounded-circle me-lg-2" 
                                src="{{ $user->profile_image ? \Illuminate\Support\Facades\Storage::url($user->profile_image) : $defaultUserAvatarUrl }}" 
                                alt="{{ $user->name }}" 
                                style="width: 40px; height: 40px; object-fit: cover;"
                            >
                            <span class="d-none d-lg-inline-flex">{{ $user->name }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded m-0 py-0" style="min-width: 12rem;">
                            @if($allRolesForSwitch->isNotEmpty())
                                <div class="px-3 pt-3 pb-1">
                                    <span class="text-uppercase small text-muted fw-semibold">View as role</span>
                                </div>
                                <form method="POST" action="{{ route('switch-role') }}">
                                    @csrf
                                    <input type="hidden" name="role_id" value="">
                                    <button type="submit" class="dropdown-item py-2 d-flex align-items-center {{ $actingAsRoleId === null || $actingAsRoleId === '' ? 'bg-primary bg-opacity-10 text-primary' : '' }}">
                                        @if($actingAsRoleId === null || $actingAsRoleId === '')
                                            <i class="fa fa-check me-2 text-primary"></i>
                                        @else
                                            <span class="me-2" style="width: 1rem;"></span>
                                        @endif
                                        <span>Super Admin (system)</span>
                                    </button>
                                </form>
                                @foreach($allRolesForSwitch as $r)
                                    @if($r->slug !== 'super-admin')
                                        <form method="POST" action="{{ route('switch-role') }}">
                                            @csrf
                                            <input type="hidden" name="role_id" value="{{ $r->id }}">
                                            <button type="submit" class="dropdown-item py-2 d-flex align-items-center w-100 {{ (string)$actingAsRoleId === (string)$r->id ? 'bg-primary bg-opacity-10 text-primary' : '' }}">
                                                @if((string)$actingAsRoleId === (string)$r->id)
                                                    <i class="fa fa-check me-2 text-primary"></i>
                                                @else
                                                    <span class="me-2" style="width: 1rem;"></span>
                                                @endif
                                                <span>{{ $r->name }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                                <div class="dropdown-divider my-0"></div>
                            @endif
                            @if($canViewActivityLogNav)
                                <a href="{{ route('activity-log') }}" class="dropdown-item py-2">
                                    <i class="fa fa-history me-2 text-muted"></i>Activity log
                                </a>
                            @endif
                            <a href="{{ route('profile') }}" class="dropdown-item py-2">
                                <i class="fa fa-user me-2 text-muted"></i>My Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item py-2 w-100 text-start border-0 bg-transparent">
                                    <i class="fa fa-sign-out-alt me-2 text-muted"></i>Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- Navbar End -->

            <!-- Main Content -->
            <div class="container-fluid pt-4 px-4">
                @php
                    $isHotelSettingsPage = request()->routeIs('pos.tables')
                        || request()->routeIs('room-types.index')
                        || request()->routeIs('additional-charges.index')
                        || ($isEffectiveSuperAdmin && request()->routeIs('subscription'))
                        || request()->routeIs('restaurant.preparation-stations')
                        || request()->routeIs('restaurant.posting-stations')
                        || request()->routeIs('stock.pending-deductions')
                        || request()->routeIs('stock.management')
                        || request()->routeIs('menu.items')
                        || request()->routeIs('menu.item-types')
                        || request()->routeIs('menu.categories')
                        || request()->routeIs('users.index');
                @endphp
                @if($isHotelSettingsPage)
                    <div class="mb-3">
                        <ul class="nav nav-tabs">
                            @if($isEffectiveSuperAdmin)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('subscription') ? 'active' : '' }}" href="{{ route('subscription') }}">Subscription</a>
                                </li>
                            @endif
                            @if($hasRestaurant)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('pos.tables') ? 'active' : '' }}" href="{{ route('pos.tables') }}">Tables management</a>
                                </li>
                                <li class="nav-item">
                                <a class="nav-link {{ (request()->routeIs('restaurant.preparation-stations') || request()->routeIs('restaurant.posting-stations')) ? 'active' : '' }}" href="{{ route('restaurant.preparation-stations') }}">Order stations</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('menu.items') || request()->routeIs('menu.item-types') || request()->routeIs('menu.categories') ? 'active' : '' }}" href="{{ route('menu.items') }}">Menu management</a>
                                </li>
                            @endif

                            @if($hasFrontOffice)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('room-types.index') ? 'active' : '' }}" href="{{ route('room-types.index') }}">Rooms management</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('additional-charges.index') ? 'active' : '' }}" href="{{ route('additional-charges.index') }}">Additional charges</a>
                                </li>
                            @endif

                            @if($hasStore)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock.pending-deductions') ? 'active' : '' }}" href="{{ route('stock.pending-deductions') }}">Deductions</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock.management') ? 'active' : '' }}" href="{{ route('stock.management') }}">Stock management</a>
                                </li>
                            @endif

                            @if($canManageHotelUsers)
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}" href="{{ route('users.index') }}">Users management</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif
                {{ $slot }}
            </div>

            <!-- Footer Start -->
            <div class="container-fluid pt-4 px-4">
                <div class="bg-light rounded-top p-4">
                    <div class="row">
                        <div class="col-12 col-sm-6 text-center text-sm-start">
                            &copy; <a href="#">{{ \App\Models\Hotel::getHotel()->name }}</a>, All Right Reserved. 
                        </div>
                        <div class="col-12 col-sm-6 text-center text-sm-end text-muted small">
                            Delivered by Ireme Technologies
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer End -->
        </div>
        <!-- Content End -->

        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('admintemplates/lib/chart/chart.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/moment.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/moment-timezone.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js') }}"></script>

    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

    <!-- Template Javascript -->
    <script src="{{ asset('admintemplates/js/main.js') }}"></script>

    @livewireScripts

    <script>
        var summernoteToolbar = [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ];

        function initSummernote() {
            $('.summernote').each(function() {
                var $ta = $(this);
                if ($ta.data('summernote')) return;
                var field = $ta.data('field');
                $ta.summernote({
                    height: 200,
                    toolbar: summernoteToolbar,
                    callbacks: {
                        onChange: function(contents) {
                            $ta.val(contents);
                            if (field && typeof Livewire !== 'undefined') {
                                var $root = $ta.closest('[wire\\:id]');
                                if ($root.length) {
                                    var id = $root.attr('wire:id');
                                    var comp = Livewire.find(id);
                                    if (comp && comp.set) comp.set(field, contents);
                                }
                            }
                        }
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', initSummernote);

        Livewire.hook('morph.updated', () => {
            $('.summernote').each(function() {
                if ($(this).data('summernote')) $(this).summernote('destroy');
            });
            setTimeout(initSummernote, 50);
        });
    </script>
</body>
</html>
