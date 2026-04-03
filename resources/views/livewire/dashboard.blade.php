<div>
    @if($user->hotel_id && $modules->isEmpty())
        <div class="alert alert-warning mb-4">
            <h6 class="alert-heading">Permission required</h6>
            <p class="mb-0">You need permission to access some features of this hotel. Please contact your hotel admin or manager to assign you to modules.</p>
        </div>
    @endif

    @if($user->hotel_id && $modules->isNotEmpty())
        <div class="mb-4">
            <h5 class="mb-1">Dashboard</h5>
            <p class="text-muted small mb-0">Summary and reports for the modules you have access to.</p>
        </div>

        @php
            $effectiveSlug = $user->getEffectiveRole()?->slug;
        @endphp

        @if($effectiveSlug === 'accountant')
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <strong>General report summary</strong>
                </div>
                <div class="card-body">
                    @livewire('front-office.general-report-summary-dashboard', ['days' => 7], key('general-report-summary-dashboard-accountant'))
                </div>
            </div>
        @endif

        {{-- POS / Restaurant module summary --}}
        @if($hasRestaurant)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span><i class="fa fa-cash-register me-2 text-primary"></i><strong>POS &amp; Restaurant</strong></span>
                    @if($canViewPosReports)
                        <a href="{{ route('pos.reports') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-chart-bar me-1"></i>View report</a>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-0">Sales, orders and menu data for the restaurant module. Use the sidebar to open POS, Orders, Invoices, or My sales.</p>
                </div>
            </div>
        @endif

        {{-- Front office module summary (rooms, occupancy) --}}
        @if($hasFrontOffice)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span><i class="fa fa-bed me-2 text-primary"></i><strong>Front office</strong></span>
                    @if($canViewFrontOfficeReports)
                        <a href="{{ route('front-office.reports') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-chart-bar me-1"></i>View report</a>
                    @endif
                </div>
                <div class="card-body">
                    @livewire('front-office.front-office-dashboard')
                </div>
            </div>
        @endif

        {{-- Stock module summary --}}
        @if($hasStore)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span><i class="fa fa-boxes me-2 text-primary"></i><strong>Stock</strong></span>
                    @if($canViewStockReports)
                        <a href="{{ route('stock.reports') }}" class="btn btn-outline-primary btn-sm"><i class="fa fa-chart-bar me-1"></i>View report</a>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-0">Stock levels, movements and requisitions. Use the sidebar to open Stock management, Stock-in, Stock-out, or Stock reports.</p>
                </div>
            </div>
        @endif

        {{-- Quick links to modules --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-light"><strong>Your modules</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Use the sidebar to open pages for each module.</p>
                <div class="row g-2">
                    @foreach($modules as $module)
                        @php $route = \App\Livewire\Dashboard::getModuleDefaultRoute($module->slug); @endphp
                        <div class="col-md-6 col-lg-4">
                            <div class="border rounded p-2 d-flex align-items-center">
                                <i class="fa fa-{{ $module->icon ?? 'folder' }} fa-lg text-primary me-2"></i>
                                <span>{{ $module->name }}</span>
                                @if($route && \Illuminate\Support\Facades\Route::has($route))
                                    <a href="{{ route($route) }}" class="btn btn-outline-primary btn-sm ms-auto">Open</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
