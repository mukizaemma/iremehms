<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PreparationStation;
use App\Notifications\OrderReadyNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PosStationDisplay extends Component
{
    public string $station = '';
    public string $stationName = '';
    public array $orders = [];
    /** For new-order notification */
    public int $orderCount = 0;
    public int $previousOrderCount = -1;

    /** Can this user mark items ready and view station report? (station staff / manager) */
    public bool $canMarkReady = false;

    public function mount(string $station)
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('pos_view_station_orders') && ! $user->hasPermission('pos_send_to_station') && ! $user->hasPermission('pos_take_orders'))) {
            abort(403, 'You do not have access to view station orders.');
        }

        $activeStations = PreparationStation::getActiveForPos();
        if (! array_key_exists($station, $activeStations)) {
            abort(404, 'Station not available or inactive.');
        }

        $this->station = $station;
        $this->stationName = $activeStations[$station];
        // Only specifically authorized station staff / managers may mark items ready.
        // Being able to view station orders is not enough (e.g. waiters).
        $this->canMarkReady = $user->hasPermission('pos_mark_station_ready');
        $this->loadOrders();
    }

    public function loadOrders()
    {
        $station = $this->station;
        $user = Auth::user();
        $onlyMyOrders = $user && ! $this->canMarkReady && $user->hasPermission('pos_take_orders');

        $query = Order::query()
            ->with([
                'table',
                'waiter',
                'orderItems' => function ($q) use ($station) {
                    $q->with('menuItem', 'voidedBy')
                        ->where(function ($q2) use ($station) {
                            $q2->where('posted_to_station', $station)
                                ->orWhere(function ($q3) use ($station) {
                                    $q3->where(function ($q4) {
                                        $q4->whereNull('posted_to_station')->orWhere('posted_to_station', '');
                                    })->whereHas('menuItem', fn ($q5) => $q5->where('preparation_station', $station));
                                });
                        });
                },
            ])
            ->whereIn('order_status', ['OPEN', 'CONFIRMED'])
            ->whereHas('orderItems', function ($q) use ($station) {
                $q->where(function ($q2) use ($station) {
                    $q2->where('posted_to_station', $station)
                        ->orWhere(function ($q3) use ($station) {
                            $q3->where(function ($q4) {
                                $q4->whereNull('posted_to_station')->orWhere('posted_to_station', '');
                            })->whereHas('menuItem', fn ($q5) => $q5->where('preparation_station', $station));
                        });
                });
            });

        if ($onlyMyOrders) {
            $query->where('waiter_id', $user->id);
        }

        $orders = $query->orderByDesc('created_at')->limit(100)->get();

        $this->orders = $orders->map(function (Order $order) use ($station) {
            $items = $order->orderItems
                ->filter(function ($item) use ($station) {
                    $eff = $item->posted_to_station ?: $item->menuItem?->preparation_station;
                    return $eff === $station;
                })
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->menuItem->name ?? 'N/A',
                        'quantity' => (int) $item->quantity,
                        'unit_price' => $item->unit_price,
                        'notes' => $item->notes ?? '',
                        'selected_options' => $item->selected_options ?? [],
                        'ingredient_overrides' => $item->ingredient_overrides ?? [],
                        'preparation_status' => $item->preparation_status ?? 'pending',
                        'voided_at' => $item->voided_at?->toIso8601String(),
                        'voided_by_name' => $item->voidedBy?->name,
                    ];
                })
                ->values()
                ->toArray();
            $allReady = $order->orderItems
                ->filter(fn ($i) => ($i->posted_to_station ?: $i->menuItem?->preparation_station) === $station && ! $i->voided_at)
                ->every(fn ($i) => ($i->preparation_status ?? 'pending') === OrderItem::PREPARATION_READY);
            return [
                'id' => $order->id,
                'table_number' => $order->table ? $order->table->table_number : '—',
                'order_status' => $order->order_status,
                'created_at' => $order->created_at->format('H:i'),
                'created_at_full' => $order->created_at->toIso8601String(),
                'waiter_name' => $order->waiter ? $order->waiter->name : '—',
                'items' => $items,
                'all_ready' => $allReady,
            ];
        })->toArray();

        $newCount = count($this->orders);
        if ($newCount > $this->previousOrderCount && $this->previousOrderCount >= 0) {
            $this->dispatch('play-new-order-sound');
        }
        $this->previousOrderCount = $newCount;
        $this->orderCount = $newCount;
    }

    /** Mark a single item as ready (per-item control). Only users with station access. */
    public function markItemReady(int $orderItemId): void
    {
        if (! $this->canMarkReady) {
            abort(403, 'You do not have permission to mark items ready at this station.');
        }
        $item = OrderItem::with('menuItem')->find($orderItemId);
        if (! $item) {
            return;
        }
        $eff = $item->posted_to_station ?: $item->menuItem?->preparation_station;
        if ($eff !== $this->station) {
            return;
        }
        $item->update([
            'preparation_status' => OrderItem::PREPARATION_READY,
            'preparation_ready_at' => now(),
        ]);
        $order = Order::with('waiter')->find($item->order_id);
        if ($order && $order->waiter_id && $order->waiter) {
            $order->waiter->notify(new OrderReadyNotification($order, $this->stationName, false));
        }
        $this->loadOrders();
    }

    /** Mark all items for this station in the given order as ready / provided. Only users with station access. */
    public function markOrderReady(int $orderId): void
    {
        if (! $this->canMarkReady) {
            abort(403, 'You do not have permission to mark items ready at this station.');
        }
        $station = $this->station;
        OrderItem::query()
            ->where('order_id', $orderId)
            ->where(function ($q) use ($station) {
                $q->where('posted_to_station', $station)
                    ->orWhereHas('menuItem', fn ($q2) => $q2->where('preparation_station', $station));
            })
            ->update([
                'preparation_status' => OrderItem::PREPARATION_READY,
                'preparation_ready_at' => now(),
            ]);
        $order = Order::with('waiter')->find($orderId);
        if ($order && $order->waiter_id && $order->waiter) {
            $order->waiter->notify(new OrderReadyNotification($order, $this->stationName, true));
        }
        $this->loadOrders();
    }

    public function render()
    {
        return view('livewire.pos.pos-station-display', [
            'activeStations' => PreparationStation::getActiveForPos(),
        ])->layout('livewire.layouts.app-layout');
    }
}
