<?php

namespace App\Mail;

use App\Models\ProformaInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProformaInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ProformaInvoice $proformaInvoice
    ) {
        $this->proformaInvoice->load(['hotel', 'lines']);
    }

    public function envelope(): Envelope
    {
        $hotel = $this->proformaInvoice->hotel?->name ?? config('app.name');

        return new Envelope(
            subject: 'Proforma '.$this->proformaInvoice->proforma_number.' — '.$hotel,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.proforma-invoice',
        );
    }
}
