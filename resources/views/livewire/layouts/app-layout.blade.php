@php
    $user = Auth::user();
    $hotel = \App\Models\Hotel::getHotel();
    $hotelLogoUrl = $hotel && $hotel->logo ? \Illuminate\Support\Facades\Storage::url($hotel->logo) : null;
    $sidebarBrandLogoUrl = $hotelLogoUrl;
    $defaultUserAvatarUrl = $hotelLogoUrl ?: asset('admintemplates/img/user.jpg');
    $modules = $modules ?? ($user ? $user->getAccessibleModules() : collect());
    $selectedModule = $selectedModule ?? session('selected_module', '');
    if ($modules->count() === 1) {
        if (! $selectedModule) {
            $selectedModule = $modules->first()->slug;
            session(['selected_module' => $selectedModule]);
        }
    }
    $availableDepartments = $availableDepartments ?? collect();
    $availableUsers = $availableUsers ?? collect();
    $effectiveRole = $user ? $user->getEffectiveRole() : null;
    $isEffectiveSuperAdmin = $effectiveRole && $effectiveRole->slug === 'super-admin';
    $isEffectiveManager = $effectiveRole && $effectiveRole->slug === 'manager';
    $isEffectiveDirector = $effectiveRole && $effectiveRole->slug === 'director';
    $isEffectiveGeneralManager = $effectiveRole && $effectiveRole->slug === 'general-manager';
    $canNavigateModules = $user && $user->canNavigateModules();
    $canManageHotelUsers = $user && $user->hasPermission('hotel_manage_users');
    $canConfigureHotel = $user && $user->hasPermission('hotel_configure_details');
    $canViewActivityLogNav = (bool) $user;
    $canProformaNav = $user && $user->hasPermission('fo_proforma_manage');
    $canWellnessNav = $user && $user->hasPermission('fo_wellness_manage');

    $hasRestaurant = $modules->contains('slug', 'restaurant');
    $hasStore = $modules->contains('slug', 'store');
    $hasFrontOffice = $modules->contains('slug', 'front-office');
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('admintemplates/lib/owlcarousel/assets/owl.carousel.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admintemplates/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css') }}" rel="stylesheet" />
    <link href="{{ asset('admintemplates/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('admintemplates/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app-top-nav.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

    @livewireStyles
    @stack('styles')
</head>
<body>
    <div class="app-shell position-relative bg-white d-flex flex-column min-vh-100 p-0 w-100">
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>

        @include('livewire.layouts.partials.top-nav-primary')
        @include('livewire.layouts.partials.top-nav-actions')

        <main class="content flex-grow-1 w-100">
            <div class="app-main-content">
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

            <footer class="app-main-content pt-0">
                <div class="bg-light rounded-top p-4">
                    <div class="row">
                        <div class="col-12 col-sm-6 text-center text-sm-start">
                            &copy; <a href="#">{{ $hotel ? $hotel->name : config('app.name') }}</a>, All Right Reserved.
                        </div>
                        <div class="col-12 col-sm-6 text-center text-sm-end text-muted small">
                            Delivered by Ireme Technologies
                        </div>
                    </div>
                </div>
            </footer>
        </main>

        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('admintemplates/lib/chart/chart.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/easing/easing.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/waypoints/waypoints.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/owlcarousel/owl.carousel.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/moment.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/moment-timezone.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
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
