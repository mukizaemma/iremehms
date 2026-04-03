<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="bg-light rounded p-4">
                <div class="mb-4">
                    <a href="{{ route('front-office.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa fa-arrow-left me-1"></i>Back</a>
                    <h5 class="mb-2">Quick group booking</h5>
                    @include('livewire.front-office.partials.front-office-quick-nav')
                </div>

                @if($success && $reservation_number)
                    <div class="card border-success mb-4">
                        <div class="card-body">
                            <h6 class="text-success mb-2"><i class="fa fa-check-circle me-1"></i>Booking created</h6>
                            <p class="mb-2">Share this <strong>reservation reference</strong> with the group so they can complete self-registration:</p>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <code class="fs-5 px-3 py-2 bg-light rounded">{{ $reservation_number }}</code>
                                <button type="button" class="btn btn-outline-primary btn-sm" wire:click="$dispatch('copy-to-clipboard', { text: '{{ $reservation_number }}' })" onclick="navigator.clipboard.writeText('{{ $reservation_number }}'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);"><i class="fa fa-copy me-1"></i>Copy</button>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Pre-arrival registration URL: @php $regHotel = \App\Models\Hotel::getHotel(); $welcomeUrl = url('/welcome' . ($regHotel ? '?hotel=' . $regHotel->id : '')); @endphp</p>
                            <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                <a href="{{ $welcomeUrl }}" target="_blank" class="text-break">{{ $welcomeUrl }}</a>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="navigator.clipboard.writeText('{{ $welcomeUrl }}'); this.textContent='Copied!'; setTimeout(() => this.innerHTML='<i class=\'fa fa-copy me-1\'></i>Copy', 2000);"><i class="fa fa-copy me-1"></i>Copy</button>
                            </div>
                            <hr>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="startNew"><i class="fa fa-plus me-1"></i>Create another group booking</button>
                        </div>
                    </div>
                @else
                    <div class="card mb-4">
                        <div class="card-body">
                            <form wire:submit="save">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Group / Company name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="group_name" placeholder="e.g. Acme Workshop">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-in date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" wire:model="check_in_date" min="{{ now()->toDateString() }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Check-out date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" wire:model="check_out_date">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Expected number of guests</label>
                                        <input type="number" class="form-control" wire:model="expected_guest_count" min="1" placeholder="e.g. 10">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Additional notes</label>
                                        <textarea class="form-control" wire:model="notes" rows="2" placeholder="Any special requests or details"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                            <span wire:loading.remove>Confirm & get reference</span>
                                            <span wire:loading>Saving…</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
