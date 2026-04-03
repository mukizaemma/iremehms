<?php

namespace App\Livewire\Pos;

use App\Models\Hotel;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manager / cashier view: aging POS orders from previous days (backlog),
 * with a simple daily snapshot so older open/confirmed/unpaid bills are
 * visible and can be followed up or regularized.
 */
class PosAgingOrders extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** Reference hotel business date for the snapshot (Y-m-d). */
    public string $date;

    /**
     * Filter for which orders to show:
     * - unpaid: OPEN/CONFIRMED with unpaid or no invoice (default)
     * - all: all orders for the selected date
     */
    public string $statusFilter = 'unpaid';

    /**
     * When true, show only orders from days before the selected date
     * (backlog). When false, show orders exactly on the selected date.
     */
    public bool $onlyPreviousDays = true;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_confirm_payment')) {
            abort(403, 'You do not have access to review aging POS orders.');
        }

        $this->date = Hotel::getTodayForHotel() ?? now()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyPreviousDays(): void
    {
        $this->resetPage();
    }

    /**
     * Aging / backlog orders according to current filters.
     */
    public function getOrdersProperty()
    {
        $hotel = Hotel::getHotel();
        $query = Order::with(['table', 'waiter', 'invoice.payments'])
            ->orderByDesc('created_at');

        if ($hotel) {
            $query->whereHas('waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            });
        }

        if ($this->onlyPreviousDays) {
            $query->whereDate('created_at', '<', $this->date);
        } else {
            $query->whereDate('created_at', $this->date);
        }

        if ($this->statusFilter === 'unpaid') {
            // Focus on operational backlog: open/confirmed orders whose invoices are not fully paid.
            $query->whereIn('order_status', ['OPEN', 'CONFIRMED'])
                ->where(function ($q) {
                    $q->whereHas('invoice', function ($iq) {
                        $iq->where('invoice_status', '!=', 'PAID');
                    })->orWhereDoesntHave('invoice');
                });
        }

        return $query->paginate(25);
    }

    /**
     * Simple daily snapshot: counts and totals for the selected date.
     */
    public function getSnapshotProperty(): array
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            return [
                'total_orders' => 0,
                'unpaid_orders' => 0,
                'unpaid_amount' => 0.0,
                'paid_orders' => 0,
                'paid_amount' => 0.0,
            ];
        }

        $orders = Order::with('invoice')
            ->whereHas('waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            })
            ->whereDate('created_at', $this->date)
            ->get();

        $totalOrders = $orders->count();
        $unpaidOrders = 0;
        $unpaidAmount = 0.0;
        $paidOrders = 0;
        $paidAmount = 0.0;

        foreach ($orders as $order) {
            $inv = $order->invoice;
            $total = $inv ? (float) $inv->total_amount : (float) $order->total;

            if ($inv && $inv->invoice_status === 'PAID') {
                $paidOrders++;
                $paidAmount += $total;
            } else {
                $unpaidOrders++;
                $unpaidAmount += $total;
            }
        }

        return [
            'total_orders' => $totalOrders,
            'unpaid_orders' => $unpaidOrders,
            'unpaid_amount' => $unpaidAmount,
            'paid_orders' => $paidOrders,
            'paid_amount' => $paidAmount,
        ];
    }

    public function render()
    {
        return view('livewire.pos.pos-aging-orders', [
            'orders' => $this->orders,
            'snapshot' => $this->snapshot,
        ])->layout('livewire.layouts.app-layout');
    }
}

