@php
    $isGroup = str_starts_with($prefix ?? '', 'group_guests');
@endphp
@php
    $useId = (bool) data_get($this, $useIdDetails);
@endphp
<div class="guest-fields">
    <div class="mb-3">
        <label class="form-check-label fw-semibold">Use details from ID or passport?</label>
        <div class="d-flex flex-column flex-sm-row gap-2 mt-1">
            <label class="form-check">
                <input type="radio" class="form-check-input" wire:model="{{ $useIdDetails }}" value="0">
                <span class="form-check-label">No, I will type</span>
            </label>
            <label class="form-check">
                <input type="radio" class="form-check-input" wire:model="{{ $useIdDetails }}" value="1">
                <span class="form-check-label">Yes — I will upload or scan and enter name & ID</span>
            </label>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Full name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" wire:model="{{ $guestName }}" placeholder="As on travel document">
        </div>
        <div class="col-md-6">
            <label class="form-label">ID or passport number</label>
            <input type="text" class="form-control" wire:model="{{ $guestIdNumber }}" placeholder="Verified at reception">
        </div>
    </div>

    @if($useId)
        <div class="mb-3">
            <label class="form-label">Upload or scan ID / passport <span class="text-muted">(optional)</span></label>
            <input type="file" class="form-control" wire:model="{{ $idDocument }}" accept="image/*" capture="environment">
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Country/Region</label>
            <select class="form-select" wire:model="{{ $guestCountry }}">
                @foreach(\App\Livewire\FrontOffice\AddReservation::COUNTRIES as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email address</label>
            <input type="email" class="form-control" wire:model="{{ $guestEmail }}" placeholder="For stay-related communication">
        </div>
        <div class="col-md-6">
            <label class="form-label">Phone number</label>
            <input type="text" class="form-control" wire:model="{{ $guestPhone }}" placeholder="Optional">
        </div>
        <div class="col-md-6">
            <label class="form-label">Purpose of stay</label>
            <input type="text" class="form-control" wire:model="{{ $guestStayPurpose }}" placeholder="e.g. Business, Leisure">
        </div>
        <div class="col-md-6">
            <label class="form-label">Profession/Occupation</label>
            <input type="text" class="form-control" wire:model="{{ $guestProfession }}" placeholder="Optional">
        </div>
        <div class="col-md-6">
            <label class="form-label">Organization/Company</label>
            <input type="text" class="form-control" wire:model="{{ $organization }}" placeholder="If applicable">
        </div>
        <div class="col-12">
            <label class="form-label">Any private requests</label>
            <textarea class="form-control" wire:model="{{ $privateNotes }}" rows="2" placeholder="e.g. health, special care, dietary, room preference — only our team will see this"></textarea>
        </div>
    </div>
</div>
