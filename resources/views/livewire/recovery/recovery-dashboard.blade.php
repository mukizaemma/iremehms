<div class="bg-light rounded p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-1">Recovery & Credit Control</h5>
            <p class="text-muted mb-0">Unpaid invoices, room charges, and credits — accountability and follow-up.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i>Dashboard
        </a>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'unpaid' ? 'active' : '' }}" wire:click="$set('tab', 'unpaid')">
                Unpaid
                <span class="badge bg-warning ms-1">{{ count($unpaidInvoices) }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'credits' ? 'active' : '' }}" wire:click="$set('tab', 'credits')">
                Credits
                <span class="badge bg-info ms-1">{{ count($creditInvoices) }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'room_charges' ? 'active' : '' }}" wire:click="$set('tab', 'room_charges')">
                Room charges (unpaid)
                <span class="badge bg-secondary ms-1">{{ count($roomChargeInvoices) }}</span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $tab === 'hotel_covered' ? 'active' : '' }}" wire:click="$set('tab', 'hotel_covered')">
                Hotel covered
                <span class="badge bg-dark ms-1">{{ count($hotelCoveredInvoices ?? []) }}</span>
            </button>
        </li>
    </ul>

    <div class="mb-3">
        <input type="text" class="form-control form-control-sm" style="max-width: 300px;" wire:model.live.debounce.300ms="search" placeholder="Search invoice, waiter, guest...">
    </div>

    @if($tab === 'unpaid')
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Posted by</th>
                                <th>Waiter / Order</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($unpaidInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ number_format($inv->total_amount, 0) }}</td>
                                    <td>{{ $inv->postedBy?->name ?? '—' }}</td>
                                    <td>{{ $inv->order?->waiter?->name ?? '—' }} / Order #{{ $inv->order_id ?? '—' }}</td>
                                    <td>{{ $inv->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-4">No unpaid invoices.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'credits')
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Posted by</th>
                                <th>Waiter / Order</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($creditInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ number_format($inv->total_amount, 0) }}</td>
                                    <td>{{ $inv->postedBy?->name ?? '—' }}</td>
                                    <td>{{ $inv->order?->waiter?->name ?? '—' }} / Order #{{ $inv->order_id ?? '—' }}</td>
                                    <td>{{ $inv->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-4">No credit invoices.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'room_charges')
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Guest / Reservation</th>
                                <th>Room</th>
                                <th>Checkout</th>
                                <th>Assigned by</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roomChargeInvoices as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ number_format($inv->total_amount, 0) }}</td>
                                    <td><span class="badge bg-{{ $inv->invoice_status === 'UNPAID' ? 'warning' : 'info' }}">{{ $inv->invoice_status }}</span></td>
                                    <td>{{ $inv->reservation?->guest_name ?? '—' }} / {{ $inv->reservation?->reservation_number ?? '—' }}</td>
                                    <td>{{ $inv->room?->room_number ?? '—' }}</td>
                                    <td>{{ $inv->reservation ? ($inv->reservation->check_out_date?->format('d M Y') . ($inv->reservation->check_out_time ? ' ' . \Carbon\Carbon::parse($inv->reservation->check_out_time)->format('H:i') : '')) : '—' }}</td>
                                    <td>{{ $inv->postedBy?->name ?? '—' }}</td>
                                    <td>{{ $inv->assigned_at?->format('d/m/Y H:i') ?? ($inv->created_at?->format('d/m/Y H:i') ?? '—') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted text-center py-4">No unpaid room charges.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($tab === 'hotel_covered')
        <div class="card">
            <div class="card-body p-0">
                <p class="small text-muted px-3 pt-2 mb-0">Invoices marked as covered by the hotel (names and reason). Assigned by shows who set this for verification.</p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Names</th>
                                <th>Reason</th>
                                <th>Assigned by</th>
                                <th>Assigned at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($hotelCoveredInvoices ?? [] as $inv)
                                <tr>
                                    <td>{{ $inv->invoice_number }}</td>
                                    <td>{{ number_format($inv->total_amount, 0) }}</td>
                                    <td><span class="badge bg-{{ $inv->invoice_status === 'PAID' ? 'success' : ($inv->invoice_status === 'CREDIT' ? 'info' : 'warning') }}">{{ $inv->invoice_status }}</span></td>
                                    <td>{{ $inv->hotel_covered_names ?? '—' }}</td>
                                    <td>{{ $inv->hotel_covered_reason ?? '—' }}</td>
                                    <td>{{ $inv->postedBy?->name ?? '—' }}</td>
                                    <td>{{ $inv->assigned_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center py-4">No hotel-covered invoices.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
