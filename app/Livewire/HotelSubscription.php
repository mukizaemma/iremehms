<?php

namespace App\Livewire;

use App\Models\Hotel;
use App\Models\SupportRequest;
use Livewire\Component;

class HotelSubscription extends Component
{
    public $showSupportModal = false;
    public $support_subject = '';
    public $support_message = '';
    public $expandedRequestId = null;

    public function expandRequest(int $id): void
    {
        $this->expandedRequestId = $this->expandedRequestId === $id ? null : $id;
    }

    public function openSupportRequest(): void
    {
        $this->support_subject = '';
        $this->support_message = '';
        $this->showSupportModal = true;
    }

    public function closeSupportModal(): void
    {
        $this->showSupportModal = false;
        $this->resetValidation();
    }

    public function submitSupportRequest(): void
    {
        $this->validate([
            'support_subject' => 'required|string|min:3|max:255',
            'support_message' => 'required|string|min:10',
        ], [], [
            'support_subject' => 'Subject',
            'support_message' => 'Message',
        ]);

        $hotel = Hotel::getHotel();
        if (!$hotel) {
            session()->flash('error', 'Hotel not found.');
            return;
        }

        SupportRequest::create([
            'hotel_id' => $hotel->id,
            'user_id' => auth()->id(),
            'subject' => $this->support_subject,
            'message' => $this->support_message,
            'status' => 'open',
        ]);

        session()->flash('message', 'Support request sent. Ireme will respond shortly.');
        $this->closeSupportModal();
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $recentInvoices = collect();
        $upcomingInvoices = collect();
        $notPaidAmount = 0;
        $recentRequests = collect();

        if ($hotel) {
            $recentInvoices = $hotel->subscriptionInvoices()
                ->whereIn('status', ['paid', 'sent', 'overdue'])
                ->orderByDesc('due_date')
                ->limit(10)
                ->get();
            $upcomingInvoices = $hotel->subscriptionInvoices()
                ->where('due_date', '>', now()->toDateString())
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')
                ->limit(10)
                ->get();
            $notPaidAmount = (float) $hotel->subscriptionInvoices()
                ->whereIn('status', ['sent', 'overdue', 'draft'])
                ->sum('amount');
            $recentRequests = $hotel->supportRequests()->limit(5)->get();
        }

        $expandedRequest = $this->expandedRequestId
            ? SupportRequest::with('responses.user')->find($this->expandedRequestId)
            : null;

        return view('livewire.hotel-subscription', [
            'hotel' => $hotel,
            'recentInvoices' => $recentInvoices,
            'upcomingInvoices' => $upcomingInvoices,
            'notPaidAmount' => $notPaidAmount,
            'recentRequests' => $recentRequests,
            'expandedRequest' => $expandedRequest,
        ])->layout('livewire.layouts.app-layout', ['title' => 'Subscription']);
    }
}
