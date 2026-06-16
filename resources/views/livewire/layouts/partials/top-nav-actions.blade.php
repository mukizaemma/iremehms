@php
    use App\Support\AppTopNavigation;

    $actionNavItems = AppTopNavigation::actionItems(
        $user,
        request(),
        $hasFrontOffice,
        $hasRestaurant,
        $hasStore,
        $showBackend,
        $isEffectiveAccountant,
        $isEffectiveManagerLike,
        $isEffectiveSuperAdmin,
        $canProformaNav,
        $canWellnessNav,
    );
@endphp
@if(count($actionNavItems) > 0)
    <nav class="app-topnav-actions" aria-label="Shortcuts">
        <div class="app-topnav-actions-inner">
            @foreach($actionNavItems as $item)
                <a href="{{ $item['href'] }}" class="app-action-tile {{ $item['active'] ? 'active' : '' }}" title="{{ $item['label'] }}">
                    <span class="app-action-tile-icon tone-{{ $item['tone'] }}">
                        <i class="fa {{ $item['icon'] }}"></i>
                    </span>
                    <span class="app-action-tile-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
@endif
