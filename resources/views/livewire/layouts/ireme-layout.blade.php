@php
    $user = Auth::user();
    $iremeLogoUrl = null;
    try {
        $iremeLogoUrl = \App\Models\PlatformSetting::getIremeLogoUrl();
    } catch (\Throwable $e) {}
    $sidebarProfileImageUrl = $iremeLogoUrl ?: ($user && $user->profile_image ? \Illuminate\Support\Facades\Storage::url($user->profile_image) : asset('admintemplates/img/user.jpg'));
    $canOnboard = $user && $user->hasPermission('ireme_onboard_hotels');
    $canManageUsers = $user && $user->hasPermission('ireme_manage_hotel_users');
    $canAssignModules = $user && $user->hasPermission('ireme_assign_modules');
    $canManageSubs = $user && $user->hasPermission('ireme_manage_subscriptions');
    $canViewHotels = $user && $user->hasPermission('ireme_view_hotels');
    $canViewSubs = $user && $user->hasPermission('ireme_view_subscriptions');
    $canViewPayments = $user && $user->hasPermission('ireme_view_payments');
    $canInvoices = $user && ($user->hasPermission('ireme_invoices_generate') || $user->hasPermission('ireme_invoices_send') || $user->hasPermission('ireme_confirm_payments'));
    $canViewRequests = $user && $user->hasPermission('ireme_view_requests');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ireme – {{ $title ?? 'Dashboard' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('admintemplates/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admintemplates/css/style.css') }}" rel="stylesheet">
    <style>.sidebar { width: 250px; } .content { margin-left: 250px; flex: 1; } @media (max-width: 991.98px) { .content { margin-left: 0; } }</style>
    @livewireStyles
</head>
<body>
    <div class="container-xxl position-relative bg-white d-flex p-0">
        <div class="sidebar pb-3">
            <nav class="navbar bg-light navbar-light">
                <a href="{{ route('ireme.dashboard') }}" class="navbar-brand mx-4 mb-3">
                    <h3 class="text-primary"><i class="fa fa-hashtag me-2"></i>Ireme</h3>
                </a>
                <div class="d-flex align-items-center ms-4 mb-4">
                    @if($user)
                    <img class="rounded-circle" src="{{ $sidebarProfileImageUrl }}" alt="Ireme" style="width: 40px; height: 40px; object-fit: cover;">
                    <div class="ms-3">
                        <h6 class="mb-0">{{ $user->name }}</h6>
                        <span class="text-muted small">{{ $user->role->name ?? '—' }}</span>
                    </div>
                    @endif
                </div>
                <div class="navbar-nav w-100">
                    @if($user && $user->hasPermission('ireme_view_dashboard'))
                        <a href="{{ route('ireme.dashboard') }}" class="nav-link {{ request()->routeIs('ireme.dashboard') ? 'active' : '' }}"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                    @endif
                    @if($user && $user->isSuperAdmin())
                        <a href="{{ route('ireme.branding') }}" class="nav-link {{ request()->routeIs('ireme.branding') ? 'active' : '' }}"><i class="fa fa-image me-2"></i>Branding</a>
                        <a href="{{ route('ireme.account') }}" class="nav-link {{ request()->routeIs('ireme.account') ? 'active' : '' }}"><i class="fa fa-building me-2"></i>Account</a>
                    @endif
                    @if($canViewHotels || $canOnboard)
                        <a href="{{ route('ireme.hotels.index') }}" class="nav-link {{ request()->routeIs('ireme.hotels.*') ? 'active' : '' }}"><i class="fa fa-hotel me-2"></i>Hotels</a>
                    @endif
                    @if($canViewSubs || $canManageSubs)
                        <a href="{{ route('ireme.subscriptions.index') }}" class="nav-link {{ request()->routeIs('ireme.subscriptions.*') ? 'active' : '' }}"><i class="fa fa-credit-card me-2"></i>Subscriptions</a>
                    @endif
                    @if($canInvoices)
                        <a href="{{ route('ireme.invoices.index') }}" class="nav-link {{ request()->routeIs('ireme.invoices.*') ? 'active' : '' }}"><i class="fa fa-file-invoice me-2"></i>Invoices & Payments</a>
                    @endif
                    @if($user && $user->isIremeUser())
                        <a href="{{ route('ireme.requests.index') }}" class="nav-link {{ request()->routeIs('ireme.requests.*') ? 'active' : '' }}"><i class="fa fa-inbox me-2"></i>Requests</a>
                    @endif
                </div>
            </nav>
        </div>
        <div class="content">
            <nav class="navbar navbar-expand bg-light navbar-light sticky-top px-4 py-2">
                <a href="{{ route('ireme.dashboard') }}" class="navbar-brand d-flex align-items-center">
                    <h6 class="mb-0 text-primary">Ireme Admin</h6>
                </a>
                <div class="navbar-nav align-items-center ms-auto">
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
                    </form>
                </div>
            </nav>
            <div class="container-fluid py-4">
                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
    <script src="{{ asset('admintemplates/lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @livewireScripts
</body>
</html>
