<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $stationName,
        public bool $fullOrder = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = $this->fullOrder
            ? 'Order #' . $this->order->id . ' is ready at ' . $this->stationName
            : 'Items for Order #' . $this->order->id . ' are ready at ' . $this->stationName;

        return [
            'type' => 'order_ready',
            'title' => 'Order ready',
            'message' => $message,
            'action_url' => route('pos.orders', ['order' => $this->order->id]),
            'order_id' => $this->order->id,
            'station_name' => $this->stationName,
        ];
    }
}
