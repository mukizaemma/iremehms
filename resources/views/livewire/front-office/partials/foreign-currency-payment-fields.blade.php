@php
    $baseCurrency = $baseCurrency ?? \App\Support\ForeignCurrencyPaymentSupport::hotelBaseCurrency();
    $compact = $compact ?? false;
    $foreignToggleModel = $foreignToggleModel ?? 'use_international_currency';
    $foreignCurrencyModel = $foreignCurrencyModel ?? 'foreign_currency';
    $exchangeRateModel = $exchangeRateModel ?? 'exchange_rate';
    $amountForeignModel = $amountForeignModel ?? 'amount_in_foreign';
    $amountLocalModel = $amountLocalModel ?? 'amount_in_local';
    $amountDirectModel = $amountDirectModel ?? 'payment_amount';
@endphp

<div class="form-check {{ $compact ? 'mb-2' : 'mb-2' }}">
    <input class="form-check-input" type="checkbox" wire:model.live="{{ $foreignToggleModel }}" id="{{ $idPrefix ?? 'fx' }}IntlCurrency">
    <label class="form-check-label small" for="{{ $idPrefix ?? 'fx' }}IntlCurrency">Foreign currency</label>
</div>

@if($this->{$foreignToggleModel})
    <div class="row g-2 mb-2">
        <div class="col-6">
            <label class="{{ $compact ? 'ar-label' : 'form-label small' }}">Currency</label>
            <select class="form-select form-select-sm" wire:model="{{ $foreignCurrencyModel }}">
                @foreach(\App\Support\ForeignCurrencyPaymentSupport::OPTIONS as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="{{ $compact ? 'ar-label' : 'form-label small' }}">Rate (1 {{ $this->{$foreignCurrencyModel} }} = ? {{ $baseCurrency }})</label>
            <input type="number" class="form-control form-control-sm" wire:model.live="{{ $exchangeRateModel }}" step="0.0001" min="0">
        </div>
        <div class="col-6">
            <label class="{{ $compact ? 'ar-label' : 'form-label small' }}">Amount ({{ $this->{$foreignCurrencyModel} }})</label>
            <input type="number" class="form-control form-control-sm" wire:model.live="{{ $amountForeignModel }}" step="0.01" min="0" placeholder="0">
        </div>
        <div class="col-6">
            <label class="{{ $compact ? 'ar-label' : 'form-label small' }}">Amount ({{ $baseCurrency }})</label>
            <input type="number" class="form-control form-control-sm" wire:model.live="{{ $amountLocalModel }}" step="0.01" min="0" placeholder="0">
        </div>
    </div>
@else
    @unless($inlineAmount ?? false)
        <div class="{{ $inlineAmount ? 'mb-0' : 'mb-2' }}">
            <label class="{{ $compact ? 'ar-label' : 'form-label small' }}">Amount ({{ $baseCurrency }})</label>
            <input type="number" class="form-control form-control-sm" wire:model.live="{{ $amountDirectModel }}" step="0.01" min="0" placeholder="0">
            @error($amountDirectModel) <span class="text-danger small">{{ $message }}</span> @enderror
        </div>
    @endunless
@endif
