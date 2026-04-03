<div class="container-fluid py-4" wire:poll.15s>
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs px-3 border-0 bg-light rounded-top mb-0" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button"
                            class="nav-link rounded-0 {{ $tab === 'guests' ? 'active fw-semibold' : '' }}"
                            wire:click="$set('tab', 'guests')"
                            role="tab">
                        <i class="fa fa-envelope me-1"></i> Guest email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button"
                            class="nav-link rounded-0 {{ $tab === 'staff' ? 'active fw-semibold' : '' }}"
                            wire:click="$set('tab', 'staff')"
                            role="tab">
                        <i class="fa fa-user-friends me-1"></i> Staff messages
                        @if($this->unreadStaffMessages > 0)
                            <span class="badge bg-primary ms-1">{{ $this->unreadStaffMessages > 99 ? '99+' : $this->unreadStaffMessages }}</span>
                        @endif
                    </button>
                </li>
            </ul>
            <div class="border border-top-0 bg-white rounded-bottom shadow-sm">
                @if($tab === 'guests')
                    @livewire('front-office.guest-communications', ['embedded' => true], key('fo-comms-guest'))
                @else
                    @livewire('front-office.staff-communications', [], key('fo-comms-staff'))
                @endif
            </div>
        </div>
    </div>
</div>
