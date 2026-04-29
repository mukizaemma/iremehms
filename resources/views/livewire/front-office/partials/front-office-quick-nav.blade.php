@php
    $u = Auth::user();
@endphp
<div class="d-flex flex-wrap gap-2">
    @if($u && \App\Support\FrontOfficeNavAccess::canViewRooms($u))
        <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-gauge-high me-1"></i>Dashboard
        </a>
    @endif
    @if($u && \App\Support\FrontOfficeNavAccess::canViewNewReservation($u))
        <a href="{{ route('front-office.add-reservation') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-plus me-1"></i>New reservation
        </a>
    @endif
    @if($u && \App\Support\FrontOfficeNavAccess::canViewAllReservations($u))
        <a href="{{ route('front-office.reservations') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-list me-1"></i>All reservations
        </a>
    @endif
    @if($u && empty($hideRoomsLink) && \App\Support\FrontOfficeNavAccess::canViewRooms($u))
        <a href="{{ route('front-office.rooms') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-door-open me-1"></i>Rooms
        </a>
    @endif
    @if($u && \App\Support\FrontOfficeNavAccess::canViewCommunication($u))
        <a href="{{ route('front-office.communications') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-comments me-1"></i>Communication
        </a>
    @endif
    @if($u && ($u->hasPermission('fo_proforma_manage') || $u->isSuperAdmin() || $u->isManager()))
        <a href="{{ route('front-office.proforma-invoices') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-file-invoice me-1"></i>Proforma
        </a>
    @endif
    @if($u && ($u->hasPermission('fo_wellness_manage') || $u->isSuperAdmin() || $u->isManager()))
        <a href="{{ route('front-office.wellness') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-spa me-1"></i>Wellness
        </a>
    @endif
</div>
