<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $reason = 'assigned'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $title = $this->reason === 'assigned'
            ? 'Order assigned to you'
            : 'New order';
        $message = 'Order #' . $this->order->id;
        if ($this->order->table) {
            $message .= ' — Table ' . $this->order->table->table_number;
        } else {
            $message .= ' — Takeaway';
        }

        return [
            'type' => 'order_placed',
            'title' => $title,
            'message' => $message,
            'action_url' => route('pos.orders', ['order' => $this->order->id]),
            'order_id' => $this->order->id,
        ];
    }
}
