<?php

namespace App\Notifications;

use App\Models\StaffMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class StaffMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public StaffMessage $staffMessage
    ) {
        $this->staffMessage->loadMissing('sender');
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $senderName = $this->staffMessage->sender?->name ?? 'Staff';
        $preview = $this->staffMessage->subject
            ? (string) $this->staffMessage->subject
            : Str::limit(strip_tags($this->staffMessage->body), 120);

        return [
            'type' => 'staff_message',
            'title' => __('Message from :name', ['name' => $senderName]),
            'message' => $preview,
            'action_url' => route('front-office.communications', [
                'tab' => 'staff',
                'with' => $this->staffMessage->sender_id,
            ]),
            'staff_message_id' => $this->staffMessage->id,
        ];
    }
}
