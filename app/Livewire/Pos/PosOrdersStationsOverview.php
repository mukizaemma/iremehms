<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\PreparationStation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Manager overview: tables with orders, waiter, invoices, and whether orders
 * were sent to preparation/posting stations or printed.
 */
class PosOrdersStationsOverview extends Component
{
    public ?int $detailOrderId = null;

    /** Items by station for the order shown in the details modal */
    public array $detailItemsByStation = [];

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_view_station_orders')) {
            abort(403, 'You do not have access to view orders and station overview.');
        }
    }

    public function getStationsProperty(): array
    {
        return PreparationStation::getActiveForPos();
    }

    /** Tables that have at least one OPEN or CONFIRMED order, with those orders loaded */
    public function getTablesWithOrdersProperty(): array
    {
        $tables = RestaurantTable::query()
            ->whereHas('orders', fn ($q) => $q->whereIn('order_status', ['OPEN', 'CONFIRMED']))
            ->with([
                'orders' => fn ($q) => $q->whereIn('order_status', ['OPEN', 'CONFIRMED'])
                    ->with(['waiter', 'invoice', 'orderItems.menuItem']),
            ])
            ->orderBy('table_number')
            ->get();

        return $tables->map(function (RestaurantTable $table) {
            $ordersData = $table->orders->map(function (Order $order) {
                $itemsByStation = [];
                $itemNames = [];
                foreach ($order->orderItems as $item) {
                    $station = $item->posted_to_station ?: $item->menuItem?->preparation_station;
                    if ($station === '' || $station === null) {
                        $station = '—';
                    }
                    if (! isset($itemsByStation[$station])) {
                        $itemsByStation[$station] = ['items' => [], 'sent' => false, 'printed' => false];
                    }
                    $itemsByStation[$station]['items'][] = [
                        'name' => $item->menuItem->name ?? 'N/A',
                        'quantity' => (int) $item->quantity,
                        'voided' => $item->voided_at !== null,
                        'sent_to_station_at' => $item->sent_to_station_at?->toIso8601String(),
                        'printed_at' => $item->printed_at?->toIso8601String(),
                    ];
                    // Collect a short list of item names for display as "order name".
                    if (! $item->voided_at && $item->menuItem && ! in_array($item->menuItem->name, $itemNames, true)) {
                        $itemNames[] = $item->menuItem->name;
                    }
                    if ($item->sent_to_station_at) {
                        $itemsByStation[$station]['sent'] = true;
                    }
                    if ($item->printed_at) {
                        $itemsByStation[$station]['printed'] = true;
                    }
                }
                $summary = '';
                if (count($itemNames) > 0) {
                    $summary = implode(', ', array_slice($itemNames, 0, 3));
                    if (count($itemNames) > 3) {
                        $summary .= '…';
                    }
                }

                return [
                    'id' => $order->id,
                    'order_status' => $order->order_status,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                    'waiter_name' => $order->waiter ? $order->waiter->name : '—',
                    'invoice_status' => $order->invoice ? $order->invoice->invoice_status : null,
                    'order_ticket_printed_at' => $order->order_ticket_printed_at?->toIso8601String(),
                    'items_summary' => $summary,
                    'items_by_station' => $itemsByStation,
                ];
            })->values()->toArray();

            return [
                'table_id' => $table->id,
                'table_number' => $table->table_number,
                'orders' => $ordersData,
            ];
        })->toArray();
    }

    public function showOrderDetails(int $orderId): void
    {
        $order = Order::with(['orderItems.menuItem'])->find($orderId);
        if (! $order) {
            return;
        }
        $itemsByStation = [];
        foreach ($order->orderItems as $item) {
            $station = $item->posted_to_station ?: $item->menuItem?->preparation_station;
            if ($station === '' || $station === null) {
                $station = '—';
            }
            if (! isset($itemsByStation[$station])) {
                $itemsByStation[$station] = ['items' => [], 'sent' => false, 'printed' => false];
            }
            $itemsByStation[$station]['items'][] = [
                'name' => $item->menuItem->name ?? 'N/A',
                'quantity' => (int) $item->quantity,
                'voided' => $item->voided_at !== null,
                'sent_to_station_at' => $item->sent_to_station_at?->toIso8601String(),
                'printed_at' => $item->printed_at?->toIso8601String(),
            ];
            if ($item->sent_to_station_at) {
                $itemsByStation[$station]['sent'] = true;
            }
            if ($item->printed_at) {
                $itemsByStation[$station]['printed'] = true;
            }
        }
        $this->detailOrderId = $orderId;
        $this->detailItemsByStation = $itemsByStation;
    }

    public function closeOrderDetails(): void
    {
        $this->detailOrderId = null;
        $this->detailItemsByStation = [];
    }

    public function render()
    {
        return view('livewire.pos.pos-orders-stations-overview')
            ->layout('livewire.layouts.app-layout');
    }
}
