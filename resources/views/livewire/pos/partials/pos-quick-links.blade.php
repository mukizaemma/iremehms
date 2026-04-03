{{--
    POS quick links bar (same on Orders, POS products, Invoices, etc.)

    @param string|null $active One of: products, orders, invoices, void-requests, my-sales, reports
    @param int|null $pendingVoidRequestsCount Optional override; otherwise computed for current user/hotel.
--}}
@php
    $activeKey = $active ?? null;
    $voidCount = isset($pendingVoidRequestsCount)
        ? (int) $pendingVoidRequestsCount
        : \App\Helpers\PosNavHelper::pendingVoidRequestsCount();
    $canViewPosReportsNav = \App\Helpers\PosNavHelper::canViewPosReportsNav();
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-1 border-top border-light-subtle">
    <span class="small text-muted me-1 d-none d-sm-inline">Quick links:</span>

    @if($activeKey === 'products')
        <span class="btn btn-sm btn-primary disabled px-2" tabindex="-1" aria-current="page">
            <i class="fa fa-cash-register me-1"></i>POS
        </span>
    @else
        <a href="{{ route('pos.products') }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
            <i class="fa fa-cash-register me-1"></i>POS
        </a>
    @endif

    @if($activeKey === 'orders')
        <span class="btn btn-sm btn-primary disabled px-2" tabindex="-1" aria-current="page">
            <i class="fa fa-shopping-cart me-1"></i>Orders
        </span>
    @else
        <a href="{{ route('pos.orders') }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
            <i class="fa fa-shopping-cart me-1"></i>Orders
        </a>
    @endif

    @if($activeKey === 'invoices')
        <span class="btn btn-sm btn-primary disabled px-2" tabindex="-1" aria-current="page">
            <i class="fa fa-file-invoice me-1"></i>Invoices
        </span>
    @else
        <a href="{{ route('pos.order-history') }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
            <i class="fa fa-file-invoice me-1"></i>Invoices
        </a>
    @endif

    @if($activeKey === 'void-requests')
        <span class="btn btn-sm btn-primary disabled px-2 position-relative" tabindex="-1" aria-current="page">
            <i class="fa fa-ban me-1"></i>Void requests
            @if($voidCount > 0)
                <span class="badge bg-danger ms-1">{{ $voidCount }}</span>
            @endif
        </span>
    @else
        <a href="{{ route('pos.void-requests') }}" class="btn btn-outline-secondary btn-sm position-relative" wire:navigate>
            <i class="fa fa-ban me-1"></i>Void requests
            @if($voidCount > 0)
                <span class="badge bg-danger ms-1">{{ $voidCount }}</span>
            @endif
        </a>
    @endif

    @if($activeKey === 'my-sales')
        <span class="btn btn-sm btn-primary disabled px-2" tabindex="-1" aria-current="page">
            <i class="fa fa-chart-line me-1"></i>My sales
        </span>
    @else
        <a href="{{ route('pos.my-sales') }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
            <i class="fa fa-chart-line me-1"></i>My sales
        </a>
    @endif

    @if($canViewPosReportsNav)
        @if($activeKey === 'reports')
            <span class="btn btn-sm btn-primary disabled px-2" tabindex="-1" aria-current="page">
                <i class="fa fa-chart-pie me-1"></i>POS reports
            </span>
        @else
            <a href="{{ route('pos.reports') }}" class="btn btn-outline-secondary btn-sm" wire:navigate>
                <i class="fa fa-chart-pie me-1"></i>POS reports
            </a>
        @endif
    @endif
</div>
