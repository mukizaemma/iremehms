<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <link href="{{ asset('admintemplates/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-size: 14px; }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .invoice-footer { border-top: 1px solid #ddd; margin-top: 2rem; padding-top: 1rem; font-size: 12px; color: #666; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-white p-4">
    <div class="container">
        <div class="d-flex justify-content-end no-print mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
            @if(request()->routeIs('ireme.*'))
                <a href="{{ route('ireme.subscriptions.show', $invoice->hotel) }}" class="btn btn-outline-secondary btn-sm ms-1">Back</a>
            @else
                <a href="{{ route('subscription') }}" class="btn btn-outline-secondary btn-sm ms-1">Back</a>
            @endif
        </div>

        <div class="invoice-header row">
            <div class="col-6">
                <strong class="fs-5">{{ \App\Models\PlatformSetting::getIremeCompanyName() }}</strong>
                @if(\App\Models\PlatformSetting::getIremePhone())
                    <br>Phone: {{ \App\Models\PlatformSetting::getIremePhone() }}
                @endif
                @if(\App\Models\PlatformSetting::getIremeEmail())
                    <br>Email: {{ \App\Models\PlatformSetting::getIremeEmail() }}
                @endif
                @if(\App\Models\PlatformSetting::getIremeTin())
                    <br>TIN: {{ \App\Models\PlatformSetting::getIremeTin() }}
                @endif
            </div>
            <div class="col-6 text-end">
                <strong>INVOICE</strong>
                <br>#{{ $invoice->invoice_number }}
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <strong>Bill to</strong><br>
                @if(!empty($invoice->hotel->logo))
                    <div class="mb-2 mt-1">
                        <img src="{{ url(\Illuminate\Support\Facades\Storage::url($invoice->hotel->logo)) }}" alt="" style="max-height:48px;max-width:180px;width:auto;object-fit:contain;">
                    </div>
                @endif
                {{ $invoice->hotel->name }}
                @if($invoice->hotel->address)
                    <br>{{ $invoice->hotel->address }}
                @endif
                @if($invoice->hotel->email)
                    <br>{{ $invoice->hotel->email }}
                @endif
                @if($invoice->hotel->contact)
                    <br>{{ $invoice->hotel->contact }}
                @endif
            </div>
            <div class="col-6 text-end">
                <table class="table table-sm table-borderless mb-0 ms-auto" style="max-width: 240px;">
                    <tr>
                        <td class="text-muted">Invoice date</td>
                        <td>{{ ($invoice->invoice_date ?? $invoice->created_at)?->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Due date</td>
                        <td>{{ $invoice->due_date->format('d M Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th class="text-end" style="width: 120px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ \App\Models\PlatformSetting::getIremeInvoiceDescription() }}
                        @if($invoice->period_start && $invoice->period_end)
                            <br><small class="text-muted">Period: {{ $invoice->period_start->format('d M Y') }} – {{ $invoice->period_end->format('d M Y') }}</small>
                        @endif
                    </td>
                    <td class="text-end">{{ number_format((float) $invoice->amount, 2) }} {{ $invoice->hotel->currency ?? 'RWF' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4">
            <strong>Payment methods</strong>
            <ul class="mb-0">
                @if(\App\Models\PlatformSetting::getIremeBankAccount())
                    <li>Bank account: {{ \App\Models\PlatformSetting::getIremeBankAccount() }}</li>
                @endif
                @if(\App\Models\PlatformSetting::getIremeMomoCode())
                    <li>Momo Pay: {{ \App\Models\PlatformSetting::getIremeMomoCode() }}</li>
                @endif
                @if(!\App\Models\PlatformSetting::getIremeBankAccount() && !\App\Models\PlatformSetting::getIremeMomoCode())
                    <li class="text-muted">Contact Ireme for payment details.</li>
                @endif
            </ul>
        </div>

        <div class="invoice-footer">
            <p class="mb-0">{{ \App\Models\PlatformSetting::getIremeInvoiceThankYou() }}</p>
        </div>
    </div>
    <script src="{{ asset('admintemplates/lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('admintemplates/lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
