<?php

namespace App\Livewire\Pos;

use App\Mail\ReceiptMail;
use App\Models\Order;
use App\Models\PosSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class PosReceipt extends Component
{
    public $orderId;
    public $order = null;
    public $showEmailForm = false;
    public $email_to = '';

    /** Split receipt: part number (1-based), total parts, amount for this part */
    public ?int $split_part = null;
    public ?int $split_parts = null;
    public ?float $split_amount = null;

    public function mount($order)
    {
        $this->orderId = $order;
        $this->order = Order::with(['orderItems.menuItem', 'table', 'waiter', 'invoice.payments.receiver'])
            ->find($this->orderId);
        if (!$this->order) {
            session()->flash('error', 'Order not found.');
            return $this->redirect(route('pos.orders'), navigate: true);
        }
        $this->split_part = (int) request()->query('split_part', 0) ?: null;
        $this->split_parts = (int) request()->query('split_parts', 0) ?: null;
        $this->split_amount = request()->has('split_amount') ? (float) request()->query('split_amount') : null;
        if ($this->split_part && $this->split_parts && $this->split_amount !== null) {
            $this->split_part = max(1, min($this->split_part, $this->split_parts));
        } else {
            $this->split_part = $this->split_parts = $this->split_amount = null;
        }
    }

    public function getWhatsappShareUrlProperty(): string
    {
        if (!$this->order) {
            return 'https://wa.me/';
        }
        $hotel = \App\Models\Hotel::getHotel();
        $lines = [
            ($hotel->name ?? config('app.name')) . ' - Receipt',
            'Invoice: ' . ($this->order->invoice->invoice_number ?? '—'),
            'Table: ' . ($this->order->table->table_number ?? '—'),
            'Date: ' . $this->order->created_at->format('Y-m-d H:i'),
            'Total: ' . \App\Helpers\CurrencyHelper::format($this->order->invoice->total_amount ?? 0),
            $this->order->invoice && $this->order->invoice->isPaid() ? 'Status: PAID' : 'Status: UNPAID',
        ];
        $text = implode("\n", $lines);
        return 'https://wa.me/?text=' . rawurlencode($text);
    }

    public function sendReceiptByEmail(): void
    {
        $this->validate(['email_to' => 'required|email']);
        try {
            Mail::to($this->email_to)->send(new ReceiptMail($this->order));
            session()->flash('receipt_email_sent', 'Receipt sent to ' . $this->email_to);
            $this->showEmailForm = false;
            $this->email_to = '';
        } catch (\Throwable $e) {
            session()->flash('receipt_email_error', 'Could not send email: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pos.pos-receipt', [
            'whatsapp_share_url' => $this->whatsappShareUrl,
        ])->layout('livewire.layouts.app-layout');
    }
}
