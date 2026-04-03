<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class IremeSubscriptionShow extends Component
{
    public Hotel $hotel;
    public $showNotifyModal = false;
    public $notify_subject = '';
    public $notify_message = '';
    public $confirmPaymentInvoiceId = null;

    public function mount(Hotel $hotel): void
    {
        $this->hotel = $hotel;
    }

    public function generateInvoice(): void
    {
        $invoice = SubscriptionInvoiceService::generateForHotel($this->hotel);
        if ($invoice) {
            session()->flash('message', "Invoice {$invoice->invoice_number} generated (due {$invoice->due_date->format('d M Y')}).");
        } else {
            session()->flash('error', 'No invoice generated. Next due date must be within 15 days and no invoice may exist for that date yet. Set amount, start date, and next due date in Update subscription.');
        }
        $this->hotel->refresh();
    }

    public function confirmPayment(int $invoiceId): void
    {
        $invoice = SubscriptionInvoice::where('hotel_id', $this->hotel->id)->find($invoiceId);
        if (!$invoice || $invoice->status === 'paid') {
            session()->flash('error', 'Invoice not found or already paid.');
            return;
        }
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        session()->flash('message', "Invoice {$invoice->invoice_number} marked as paid.");
        $this->confirmPaymentInvoiceId = null;
        $this->hotel->refresh();
    }

    public function openNotifyModal(): void
    {
        $this->notify_subject = '';
        $this->notify_message = '';
        $this->showNotifyModal = true;
        $this->resetValidation();
    }

    public function closeNotifyModal(): void
    {
        $this->showNotifyModal = false;
        $this->resetValidation();
    }

    public function sendNotification(): void
    {
        $this->validate([
            'notify_subject' => 'required|string|min:1|max:255',
            'notify_message' => 'required|string|min:1',
        ], [], ['notify_subject' => 'Subject', 'notify_message' => 'Message']);

        $email = $this->hotel->email;
        if (!$email) {
            session()->flash('error', 'Hotel has no email set. Update hotel details first.');
            return;
        }

        try {
            Mail::raw($this->notify_message, function ($m) {
                $m->to($this->hotel->email)
                    ->subject($this->notify_subject);
            });
            session()->flash('message', 'Notification sent to ' . $this->hotel->email);
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send: ' . $e->getMessage());
        }
        $this->closeNotifyModal();
    }

    public function render()
    {
        $this->hotel->load(['subscriptionInvoices' => fn ($q) => $q->orderByDesc('due_date')->limit(20)]);
        $unpaidInvoices = $this->hotel->subscriptionInvoices->whereIn('status', ['sent', 'overdue', 'draft']);
        $requestsCount = $this->hotel->supportRequests()->count();

        $nextDue = $this->hotel->next_due_date ? \Carbon\Carbon::parse($this->hotel->next_due_date) : null;
        $today = \Carbon\Carbon::today();
        $daysText = '—';
        if ($nextDue) {
            if ($nextDue->gte($today)) {
                $days = $today->diffInDays($nextDue, false);
                $daysText = $days . ' day' . ($days !== 1 ? 's' : '') . ' remaining';
            } else {
                $days = $nextDue->diffInDays($today, false);
                $daysText = $days . ' day' . ($days !== 1 ? 's' : '') . ' past due';
            }
        }

        return view('livewire.ireme.ireme-subscription-show', [
            'unpaidInvoices' => $unpaidInvoices,
            'requestsCount' => $requestsCount,
            'daysText' => $daysText,
        ])->layout('livewire.layouts.ireme-layout', ['title' => 'Subscription · ' . $this->hotel->name]);
    }
}
