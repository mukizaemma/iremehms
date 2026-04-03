<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->load(['orderItems.menuItem', 'table', 'waiter', 'invoice.payments.receiver']);
    }

    public function envelope(): Envelope
    {
        $invoiceNumber = $this->order->invoice->invoice_number ?? $this->order->id;
        return new Envelope(
            subject: 'Your receipt - Invoice ' . $invoiceNumber,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
        );
    }
}
