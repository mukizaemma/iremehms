<?php

namespace App\Livewire\Ireme;

use App\Models\Hotel;
use App\Models\SubscriptionInvoice;
use Livewire\Component;
use Livewire\WithPagination;

class IremeInvoices extends Component
{
    use WithPagination;

    public $filter_hotel_id = '';
    public $filter_status = '';

    public function mount(): void
    {
        $this->filter_hotel_id = request()->query('hotel_id', '');
        $this->filter_status = request()->query('status', '');
    }

    public function confirmPayment(int $invoiceId): void
    {
        $invoice = SubscriptionInvoice::find($invoiceId);
        if (!$invoice || $invoice->status === 'paid') {
            session()->flash('error', 'Invoice not found or already paid.');
            return;
        }
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        session()->flash('message', "Invoice {$invoice->invoice_number} marked as paid.");
    }

    public function render()
    {
        $generatedQuery = SubscriptionInvoice::with('hotel')
            ->orderByDesc('due_date');

        if ($this->filter_hotel_id !== '') {
            $generatedQuery->where('hotel_id', (int) $this->filter_hotel_id);
        }
        if ($this->filter_status !== '') {
            $generatedQuery->where('status', $this->filter_status);
        }

        $generatedInvoices = $generatedQuery->paginate(15, ['*'], 'generated_page')->withQueryString();

        $upcomingQuery = SubscriptionInvoice::with('hotel')
            ->where('due_date', '>', now()->toDateString())
            ->where('status', '!=', 'paid')
            ->orderBy('due_date');

        if ($this->filter_hotel_id !== '') {
            $upcomingQuery->where('hotel_id', (int) $this->filter_hotel_id);
        }

        $upcomingInvoices = $upcomingQuery->limit(20)->get();

        $hotels = Hotel::orderBy('name')->get(['id', 'name', 'hotel_code']);

        return view('livewire.ireme.ireme-invoices', [
            'generatedInvoices' => $generatedInvoices,
            'upcomingInvoices' => $upcomingInvoices,
            'hotels' => $hotels,
        ])->layout('livewire.layouts.ireme-layout', ['title' => 'Invoices & Payments']);
    }
}
