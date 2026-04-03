<?php

namespace App\Notifications;

use App\Models\ProformaInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProformaApprovalRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ProformaInvoice $proformaInvoice
    ) {
        $this->proformaInvoice->loadMissing('hotel');
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $number = $this->proformaInvoice->proforma_number;
        $client = $this->proformaInvoice->client_organization ?: $this->proformaInvoice->client_name;

        return [
            'type' => 'proforma_approval_request',
            'title' => 'Proforma pending approval',
            'message' => 'Proforma '.$number.' for '.$client.' is waiting for manager verification.',
            'action_url' => route('front-office.proforma-invoices.edit', $this->proformaInvoice),
            'proforma_invoice_id' => $this->proformaInvoice->id,
            'proforma_number' => $number,
        ];
    }
}
