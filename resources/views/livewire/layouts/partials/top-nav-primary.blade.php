@php
    use App\Support\AppTopNavigation;
    use App\Support\FrontOfficeTopNavigation;

    $primaryNavItems = AppTopNavigation::primaryItems(
        $user,
        request(),
        $hasFrontOffice,
        $hasRestaurant,
        $hasStore,
        $showBackend,
        $isEffectiveAccountant,
        $isEffectiveManagerLike,
        $isEffectiveSuperAdmin,
        $canViewActivityLogNav,
    );
    $businessDate = AppTopNavigation::businessDateLabel();
    $counts = AppTopNavigation::arrivalDepartureCounts();
    $usesFoBar = FrontOfficeTopNavigation::usesFrontOfficePrimaryBar(
        $user,
        $hasFrontOffice,
        $showBackend,
        $isEffectiveAccountant,
        $isEffectiveManagerLike,
    );
@endphp
<header class="app-topnav-primary sticky-top shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-dark py-0 px-2 px-md-3">
        <a href="{{ route('dashboard') }}" class="navbar-brand d-flex align-items-center me-2 me-lg-3 py-2 flex-shrink-0">
            @if($sidebarBrandLogoUrl ?? null)
                <img src="{{ $sidebarBrandLogoUrl }}" alt="{{ $hotel ? $hotel->name : config('app.name') }}">
            @else
                <span class="fw-bold text-white text-truncate" style="max-width: 180px;">{{ $hotel ? $hotel->name : config('app.name') }}</span>
            @endif
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#appTopNavPrimary" aria-controls="appTopNavPrimary" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="appTopNavPrimary">
            <ul class="navbar-nav me-auto flex-row flex-wrap gap-0">
                @foreach($primaryNavItems as $item)
                    @if(! empty($item['children']))
                        <li class="nav-item dropdown app-topnav-dropdown">
                            <a href="#" class="nav-link dropdown-toggle {{ $item['active'] ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa {{ $item['icon'] }} me-1 d-none d-xl-inline"></i>{{ $item['label'] }}
                            </a>
                            <ul class="dropdown-menu shadow border-0">
                                @foreach($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['href'] }}" class="dropdown-item {{ $child['active'] ? 'active' : '' }}">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ $item['href'] }}" class="nav-link {{ $item['active'] ? 'active' : '' }}">
                                <i class="fa {{ $item['icon'] }} me-1 d-none d-xl-inline"></i>{{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>

            <div class="d-flex align-items-center flex-wrap gap-2 gap-lg-3 ms-lg-auto py-2">
                @if($hasFrontOffice)
                    <div class="app-topnav-meta d-none d-md-flex align-items-center gap-2">
                        <span class="badge rounded-pill badge-arrival" title="Arrivals today">{{ $counts['arrivals'] }} Arrival</span>
                        <span class="badge rounded-pill badge-departure" title="Departures today">{{ $counts['departures'] }} Departure</span>
                    </div>
                @endif
                <span class="app-topnav-meta d-none d-lg-inline"><i class="fa fa-calendar-day me-1"></i>{{ $businessDate }}</span>
                @auth
                    @livewire('notification-bell')
                @endauth
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center py-1" data-bs-toggle="dropdown">
                        <img
                            class="rounded-circle me-2"
                            src="{{ $user->profile_image ? \Illuminate\Support\Facades\Storage::url($user->profile_image) : $defaultUserAvatarUrl }}"
                            alt="{{ $user->name }}"
                            style="width: 36px; height: 36px; object-fit: cover;"
                        >
                        <span class="d-none d-md-inline">Hi {{ strtok($user->name, ' ') }}!</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0">
                        <div class="px-3 py-2 border-bottom">
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $effectiveRole ? $effectiveRole->name : ($user->role->name ?? '') }}</div>
                            @if($hotel)
                                <div class="small text-muted mt-1">{{ $hotel->name }} #{{ $hotel->hotel_code ?? $hotel->id }}</div>
                            @endif
                        </div>
                        @if($allRolesForSwitch->isNotEmpty())
                            <div class="px-3 pt-2 pb-1">
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
                                    Super Admin (system)
                                </button>
                            </form>
                            @foreach($allRolesForSwitch as $r)
                                @if($r->slug !== 'super-admin')
                                    <form method="POST" action="{{ route('switch-role') }}">
                                        @csrf
                                        <input type="hidden" name="role_id" value="{{ $r->id }}">
                                        <button type="submit" class="dropdown-item py-2 d-flex align-items-center w-100 {{ (string) $actingAsRoleId === (string) $r->id ? 'bg-primary bg-opacity-10 text-primary' : '' }}">
                                            @if((string) $actingAsRoleId === (string) $r->id)
                                                <i class="fa fa-check me-2 text-primary"></i>
                                            @else
                                                <span class="me-2" style="width: 1rem;"></span>
                                            @endif
                                            {{ $r->name }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                            <div class="dropdown-divider my-0"></div>
                        @endif
                        @if($canViewActivityLogNav)
                            <a href="{{ route('activity-log') }}" class="dropdown-item py-2"><i class="fa fa-history me-2 text-muted"></i>Activity log</a>
                        @endif
                        <a href="{{ route($showBackend || $isEffectiveAccountant || $isEffectiveManagerLike ? 'account.hub' : 'profile') }}" class="dropdown-item py-2"><i class="fa fa-user me-2 text-muted"></i>My account</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 w-100 text-start border-0 bg-transparent">
                                <i class="fa fa-sign-out-alt me-2 text-muted"></i>Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>
