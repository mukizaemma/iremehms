<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-gauge-high me-1"></i>Dashboard
    </a>
    <a href="{{ route('front-office.add-reservation') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-plus me-1"></i>New reservation
    </a>
    <a href="{{ route('front-office.reservations') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-list me-1"></i>All reservations
    </a>
    @if(empty($hideRoomsLink))
        <a href="{{ route('front-office.rooms') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-door-open me-1"></i>Rooms
        </a>
    @endif
    <a href="{{ route('front-office.communications') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-comments me-1"></i>Communication
    </a>
    @if(Auth::user()->hasPermission('fo_proforma_manage') || Auth::user()->isSuperAdmin() || Auth::user()->isManager())
        <a href="{{ route('front-office.proforma-invoices') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-file-invoice me-1"></i>Proforma
        </a>
    @endif
    @if(Auth::user()->hasPermission('fo_wellness_manage') || Auth::user()->isSuperAdmin() || Auth::user()->isManager())
        <a href="{{ route('front-office.wellness') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-spa me-1"></i>Wellness
        </a>
    @endif
</div>
