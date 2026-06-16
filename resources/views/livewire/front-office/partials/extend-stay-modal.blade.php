@if($showExtendModal && $extendReservationId)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);" wire:keydown.escape="closeExtendModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend stay — {{ $extendReservationNumber }}</h5>
                    <button type="button" class="btn-close" wire:click="closeExtendModal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Extend the check-out date if the guest will stay longer. Update room charges separately if the rate changes.</p>
                    <div class="mb-3">
                        <label class="form-label small mb-0">Current check-out</label>
                        <input type="date" class="form-control form-control-sm" value="{{ $extendCurrentCheckOut }}" disabled>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-0">New check-out <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            class="form-control form-control-sm @error('extendNewCheckOutDate') is-invalid @enderror"
                            wire:model="extendNewCheckOutDate"
                            min="{{ \Carbon\Carbon::parse($extendCurrentCheckOut)->addDay()->format('Y-m-d') }}"
                        >
                        @error('extendNewCheckOutDate') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeExtendModal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmExtendStay" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="confirmExtendStay">Save new check-out</span>
                        <span wire:loading wire:target="confirmExtendStay">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
