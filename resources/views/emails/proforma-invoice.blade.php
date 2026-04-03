<x-mail::message>
# Proforma {{ $proformaInvoice->proforma_number }}

**{{ $proformaInvoice->hotel?->name ?? config('app.name') }}**

@if($proformaInvoice->client_organization)
**Organization:** {{ $proformaInvoice->client_organization }}  
@endif
**Contact:** {{ $proformaInvoice->client_name }}

@if($proformaInvoice->event_title)
**Event:** {{ $proformaInvoice->event_title }}
@endif

@if($proformaInvoice->service_start_date && $proformaInvoice->service_end_date)
**Dates:** {{ $proformaInvoice->service_start_date->format('M j, Y') }} – {{ $proformaInvoice->service_end_date->format('M j, Y') }}
@endif

---

### Line items

@foreach($proformaInvoice->lines as $line)
- {{ $line->description }} — {{ number_format((float) $line->quantity, 2) }} × {{ number_format((float) $line->unit_price, 2) }} {{ $proformaInvoice->currency }} = **{{ number_format((float) $line->line_total, 2) }}**
@endforeach

**Subtotal:** {{ number_format((float) $proformaInvoice->subtotal, 2) }} {{ $proformaInvoice->currency }}

@if((float) $proformaInvoice->discount_amount > 0)
**Discount:** −{{ number_format((float) $proformaInvoice->discount_amount, 2) }}
@endif
@if((float) $proformaInvoice->tax_amount > 0)
**Tax:** {{ number_format((float) $proformaInvoice->tax_amount, 2) }}
@endif

## Total: {{ number_format((float) $proformaInvoice->grand_total, 2) }} {{ $proformaInvoice->currency }}

@if($proformaInvoice->payment_terms)
---

**Payment terms**

{{ $proformaInvoice->payment_terms }}
@endif

@if($proformaInvoice->notes)
---

**Notes**

{{ $proformaInvoice->notes }}
@endif

Thanks,<br>
{{ $proformaInvoice->hotel?->name ?? config('app.name') }}
</x-mail::message>
