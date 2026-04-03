<div>
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-sm" id="receipt-print">
                    <div class="card-body p-4">
                        @php
                            $hotel = \App\Models\Hotel::getHotel();
                            $receiptSubtitle = ($split_part && $split_parts && $split_amount !== null)
                                ? 'Split receipt — Part '.$split_part.' of '.$split_parts
                                : 'Receipt';
                        @endphp
                        <x-hotel-document-header :hotel="$hotel" :subtitle="$receiptSubtitle" class="mb-2" />
                        @if($hotel && ($hotel->email || $hotel->contact))
                            <p class="mb-0 small mt-2" style="line-height:1.55;color:#374151;">
                                @if($hotel->email)<strong style="font-weight:700;">{{ __('Email') }}:</strong> {{ $hotel->email }}<br>@endif
                                @if($hotel->contact)<strong style="font-weight:700;">{{ __('Phone') }}:</strong> {{ $hotel->contact }}@endif
                            </p>
                        @endif
                        <hr>
                        <p class="mb-0 small">
                            <strong>Invoice:</strong> {{ $order->invoice->invoice_number ?? '—' }}<br>
                            <strong>Table:</strong>
                            {{ $order->table->table_number ?? 'Takeaway' }}<br>
                            <strong>Date:</strong> {{ \App\Helpers\HotelTimeHelper::format($order->created_at, 'Y-m-d H:i') }}<br>
                            <strong>Waiter:</strong> {{ $order->waiter->name ?? 'N/A' }}<br>
                            @if($hotel->receipt_momo_value && $hotel->receipt_momo_label)
                                <strong class="receipt-momo-badge">{{ $hotel->receipt_momo_label }}:</strong> {{ $hotel->receipt_momo_value }}
                            @endif
                        </p>
                        <hr>
                        @if($split_part && $split_parts && $split_amount !== null)
                            <p class="small text-muted mb-2">This is your share of a split bill (Part {{ $split_part }} of {{ $split_parts }}).</p>
                            <p class="mb-0"><strong>Invoice:</strong> {{ $order->invoice->invoice_number ?? '—' }} · Full total: {{ \App\Helpers\CurrencyHelper::format($order->invoice->total_amount ?? 0) }}</p>
                        @else
                            <table class="table table-sm table-borderless mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->orderItems as $oi)
                                        <tr>
                                            <td>{{ $oi->menuItem->name ?? 'N/A' }}</td>
                                            <td class="text-end">{{ $oi->quantity }}</td>
                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($oi->unit_price) }}</td>
                                            <td class="text-end">{{ \App\Helpers\CurrencyHelper::format($oi->line_total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        <hr>
                        @php
                            $totalAmount = (float) ($order->invoice->total_amount ?? 0);
                            $displayAmount = ($split_part && $split_parts && $split_amount !== null) ? (float) $split_amount : $totalAmount;
                            $vatBreakdown = \App\Helpers\VatHelper::fromInclusive($displayAmount);
                        @endphp
                        @if($split_part && $split_parts && $split_amount !== null)
                            <div class="text-end small">
                                <p class="mb-0"><strong>Your share (Part {{ $split_part }} of {{ $split_parts }}): {{ \App\Helpers\CurrencyHelper::format($displayAmount) }}</strong></p>
                            </div>
                        @elseif($hotel->showsVatOnReceipts())
                            <div class="text-end small">
                                <p class="mb-1">Amount: {{ \App\Helpers\CurrencyHelper::format($vatBreakdown['net']) }}</p>
                                <p class="mb-1">VAT ({{ (int)\App\Helpers\VatHelper::getVatRate() }}%): {{ \App\Helpers\CurrencyHelper::format($vatBreakdown['vat']) }}</p>
                                @if($order->order_status === 'PAID' && $order->invoice && $order->invoice->isPaid())
                                    <p class="mb-0"><strong>Total Amount paid: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong></p>
                                @else
                                    <p class="mb-0"><strong>Total Amount to Pay: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong></p>
                                @endif
                            </div>
                        @else
                            <div class="text-end small">
                                @if($order->order_status === 'PAID' && $order->invoice && $order->invoice->isPaid())
                                    <p class="mb-0"><strong>Total Amount paid: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong></p>
                                @else
                                    <p class="mb-0"><strong>Total Amount to Pay: {{ \App\Helpers\CurrencyHelper::format($totalAmount) }}</strong></p>
                                @endif
                            </div>
                        @endif
                        @if(!($split_part && $split_parts && $split_amount !== null))
                            @if($order->order_status === 'PAID' && $order->invoice && $order->invoice->isPaid())
                                <p class="text-end text-success small mt-2 mb-0"><strong>PAID</strong></p>
                            @else
                                <p class="text-end text-warning small mt-2 mb-0"><strong>UNPAID</strong></p>
                            @endif
                        @endif
                        @if(!empty($hotel->receipt_thank_you_text))
                            <p class="text-center small mt-2 mb-1"><strong>{{ $hotel->receipt_thank_you_text }}</strong></p>
                        @endif
                        <p class="text-center small text-muted mt-1 mb-0">
                            Powered By <a href="https://iremetech.com" target="_blank" class="text-decoration-none">IremeTech</a>
                        </p>
                    </div>
                </div>
                <div class="mt-3 text-center no-print">
                    <p class="small text-muted mb-2">Print to a POS printer or choose <strong>Save as PDF</strong> in the print dialog if no printer is connected.</p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <button type="button" class="btn btn-outline-primary" onclick="window.print();">
                            <i class="fa fa-print me-2"></i>Print / Save as PDF
                        </button>
                        <a href="{{ $whatsapp_share_url }}" target="_blank" rel="noopener" class="btn btn-outline-success">
                            <i class="fa fa-whatsapp me-2"></i>Share on WhatsApp
                        </a>
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEmailForm', true)">
                            <i class="fa fa-envelope me-2"></i>Email receipt
                        </button>
                        <a href="{{ url('/pos/orders') }}" class="btn btn-outline-secondary">Back to Orders</a>
                    </div>
                </div>

                @if($showEmailForm)
                    <div class="mt-4 card no-print">
                        <div class="card-body">
                            <h6 class="card-title">Email receipt</h6>
                            @if (session()->has('receipt_email_sent'))
                                <div class="alert alert-success small">{{ session('receipt_email_sent') }}</div>
                            @endif
                            @if (session()->has('receipt_email_error'))
                                <div class="alert alert-danger small">{{ session('receipt_email_error') }}</div>
                            @endif
                            <form wire:submit.prevent="sendReceiptByEmail">
                                <div class="mb-2">
                                    <label class="form-label small">Client email</label>
                                    <input type="email" class="form-control form-control-sm" wire:model.defer="email_to" placeholder="client@example.com" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Send receipt</button>
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showEmailForm', false)">Cancel</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <style>
    .receipt-momo-badge { background: #b6e0fe; color: #0a58ca; padding: 0.2em 0.5em; border-radius: 4px; font-weight: 600; }
    @media print {
        .sidebar, .navbar, .btn, .nav-pills, .breadcrumb, .no-print, a[href]:not([href^="#"]).btn { display: none !important; }
        #receipt-print { box-shadow: none !important; border: 1px solid #ddd !important; }
        .receipt-momo-badge { background: #cfe2ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    </style>
</div>
