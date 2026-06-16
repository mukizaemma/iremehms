@php
    $user = Auth::user();
    $iremeLogoUrl = null;
    try {
        $iremeLogoUrl = \App\Models\PlatformSetting::getIremeLogoUrl();
    } catch (\Throwable $e) {}
    $profileImageUrl = $iremeLogoUrl ?: ($user && $user->profile_image ? \Illuminate\Support\Facades\Storage::url($user->profile_image) : asset('admintemplates/img/user.jpg'));
    $canOnboard = $user && $user->hasPermission('ireme_onboard_hotels');
    $canViewHotels = $user && ($user->hasPermission('ireme_view_hotels') || $canOnboard);
    $canViewSubs = $user && ($user->hasPermission('ireme_view_subscriptions') || $user->hasPermission('ireme_manage_subscriptions'));
    $canInvoices = $user && ($user->hasPermission('ireme_invoices_generate') || $user->hasPermission('ireme_invoices_send') || $user->hasPermission('ireme_confirm_payments'));
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
    <link href="{{ asset('css/app-top-nav.css') }}" rel="stylesheet">
    @livewireStyles
</head>
<body>
    <div class="app-shell position-relative bg-white d-flex flex-column min-vh-100 p-0 w-100">
        <header class="app-topnav-primary sticky-top shadow-sm">
            <nav class="navbar navbar-expand-lg navbar-dark py-0 px-2 px-md-3">
                <a href="{{ route('ireme.dashboard') }}" class="navbar-brand d-flex align-items-center me-3 py-2">
                    @if($iremeLogoUrl)
                        <img src="{{ $iremeLogoUrl }}" alt="Ireme" style="max-height: 36px;">
                    @else
                        <span class="fw-bold text-white">Ireme</span>
                    @endif
                </a>
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#iremeTopNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="iremeTopNav">
                    <ul class="navbar-nav me-auto">
                        @if($user && $user->hasPermission('ireme_view_dashboard'))
                            <li class="nav-item"><a href="{{ route('ireme.dashboard') }}" class="nav-link {{ request()->routeIs('ireme.dashboard') ? 'active' : '' }}">Dashboard</a></li>
                        @endif
                        @if($user && $user->isSuperAdmin())
                            <li class="nav-item"><a href="{{ route('ireme.branding') }}" class="nav-link {{ request()->routeIs('ireme.branding') ? 'active' : '' }}">Branding</a></li>
                            <li class="nav-item"><a href="{{ route('ireme.account') }}" class="nav-link {{ request()->routeIs('ireme.account') ? 'active' : '' }}">Account</a></li>
                        @endif
                        @if($canViewHotels)
                            <li class="nav-item"><a href="{{ route('ireme.hotels.index') }}" class="nav-link {{ request()->routeIs('ireme.hotels.*') ? 'active' : '' }}">Hotels</a></li>
                        @endif
                        @if($canViewSubs)
                            <li class="nav-item"><a href="{{ route('ireme.subscriptions.index') }}" class="nav-link {{ request()->routeIs('ireme.subscriptions.*') ? 'active' : '' }}">Subscriptions</a></li>
                        @endif
                        @if($canInvoices)
                            <li class="nav-item"><a href="{{ route('ireme.invoices.index') }}" class="nav-link {{ request()->routeIs('ireme.invoices.*') ? 'active' : '' }}">Invoices</a></li>
                        @endif
                        @if($user && $user->isIremeUser())
                            <li class="nav-item"><a href="{{ route('ireme.requests.index') }}" class="nav-link {{ request()->routeIs('ireme.requests.*') ? 'active' : '' }}">Requests</a></li>
                        @endif
                    </ul>
                    <div class="d-flex align-items-center gap-2 py-2">
                        @if($user)
                            <img class="rounded-circle" src="{{ $profileImageUrl }}" alt="" style="width: 32px; height: 32px; object-fit: cover;">
                            <span class="text-white small d-none d-md-inline">{{ $user->name }}</span>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-light">Logout</button>
                        </form>
                    </div>
                </div>
            </nav>
        </header>

        <main class="content flex-grow-1 w-100">
            <div class="app-main-content">
                @if (session()->has('message'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('message') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>
    <script src="{{ asset('admintemplates/lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @livewireScripts
</body>
</html>
