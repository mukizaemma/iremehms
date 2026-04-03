@props([
    'hotel' => null,
    'subtitle' => null,
])
@php
    use Illuminate\Support\Facades\Storage;
    $logoUrl = ($hotel && ! empty($hotel->logo)) ? url(Storage::url($hotel->logo)) : null;
    $hotelName = $hotel?->name ?? config('app.name');
@endphp
<div {{ $attributes->merge(['class' => 'hotel-document-header']) }}>
    {{-- Stacked: logo → name → subtitle → slot (contacts, etc.) — all left-aligned --}}
    <div style="display:flex;flex-direction:column;align-items:flex-start;max-width:100%;">
        @if($logoUrl)
            <img
                src="{{ $logoUrl }}"
                alt=""
                style="max-height:64px;max-width:240px;width:auto;height:auto;object-fit:contain;display:block;margin-bottom:10px;"
            >
        @endif
        <div style="font-weight:700;font-size:1.15rem;line-height:1.25;color:#111;margin:0;padding:0;">
            {{ $hotelName }}
        </div>
        @if(filled($subtitle))
            <div style="color:#64748b;font-size:12px;margin-top:6px;margin-bottom:0;line-height:1.4;">
                {{ $subtitle }}
            </div>
        @endif
        {!! $slot !!}
    </div>
</div>
