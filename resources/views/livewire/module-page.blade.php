<div>
    @if($module->slug === 'front-office')
        @livewire('front-office.front-office-admin')
    @else
    <div class="bg-light rounded p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">{{ $module->name }}</h5>
                @if($module->description)
                    <p class="text-muted mb-0">{{ $module->description }}</p>
                @endif
            </div>
        </div>

        <div class="alert alert-info">
            <i class="fa fa-info-circle me-2"></i>
            Module content for <strong>{{ $module->name }}</strong> will be displayed here.
            <br>
            <small>This is a placeholder page. Module-specific functionality will be implemented here.</small>
        </div>

        <!-- Module-specific content can be added here based on module slug -->
        @if($module->slug === 'dashboard')
            <p>Dashboard module content</p>
        @elseif($module->slug === 'restaurant')
            <p>Restaurant module content</p>
        @elseif($module->slug === 'store')
            @livewire('stock-dashboard')
        @elseif($module->slug === 'housekeeping')
            <p>Housekeeping module content</p>
        @elseif($module->slug === 'reports')
            <p>Reports module content</p>
        @elseif($module->slug === 'settings')
            <p>Settings module content</p>
        @elseif($module->slug === 'recovery')
            @livewire('recovery.recovery-dashboard')
        @elseif($module->slug === 'back-office')
            <p>Back Office: stock items, rooms, menu, BoM, stations, cost & margin (use existing menu/rooms/stock pages).</p>
        @endif
    </div>
    @endif
</div>
