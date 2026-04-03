<?php

namespace App\Notifications;

use App\Models\OrderItemVoidRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VoidRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public OrderItemVoidRequest $voidRequest
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $item = $this->voidRequest->orderItem;
        $order = $item?->order;
        $orderId = $order?->id;
        $requestedBy = $this->voidRequest->requestedBy;

        return [
            'type' => 'void_request',
            'title' => 'Void request needs your approval',
            'message' => ($requestedBy ? $requestedBy->name : 'Someone') . ' requested to void "' . ($item?->menuItem?->name ?? 'item') . '" on Order #' . $orderId,
            'action_url' => $orderId ? route('pos.orders', ['order' => $orderId]) : route('pos.orders'),
            'void_request_id' => $this->voidRequest->id,
            'order_id' => $orderId,
        ];
    }
}
