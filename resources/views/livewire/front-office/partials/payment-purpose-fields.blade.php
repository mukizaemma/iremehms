@props([
    'purposeModel' => 'paymentPurpose',
    'revenueDateModel' => 'paymentRevenueAttributionDate',
    'hint' => '',
])

@php use App\Enums\PaymentPurpose; @endphp

<div class="mb-2">
    <label class="form-label small mb-1">Payment purpose <span class="text-danger">*</span></label>
    <select class="form-select form-select-sm" wire:model.live="{{ $purposeModel }}">
        @foreach(PaymentPurpose::selectableCases() as $purpose)
            <option value="{{ $purpose->value }}">{{ $purpose->label() }}</option>
        @endforeach
    </select>
</div>

@php $purposeVal = data_get($this, $purposeModel, 'current_stay'); @endphp
@if($purposeVal === 'debt_settlement')
    <div class="mb-2">
        <label class="form-label small mb-1">Sales / revenue date <span class="text-danger">*</span></label>
        <input type="date" class="form-control form-control-sm" wire:model="{{ $revenueDateModel }}">
        @error($revenueDateModel) <span class="text-danger small">{{ $message }}</span> @enderror
    </div>
@endif

@if($hint !== '')
    <p class="small text-muted mb-0">{{ $hint }}</p>
@endif
