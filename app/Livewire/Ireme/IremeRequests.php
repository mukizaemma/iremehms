<?php

namespace App\Livewire\Ireme;

use App\Models\SupportRequest;
use App\Models\SupportRequestResponse;
use Livewire\Component;
use Livewire\WithPagination;

class IremeRequests extends Component
{
    use WithPagination;

    public $selectedId = null;
    public $reply_message = '';
    public $status = ''; // filter: open, in_progress, resolved, or ''
    public $hotel_id = null; // filter by hotel when coming from subscription show

    public function mount(): void
    {
        $this->hotel_id = request()->query('hotel_id') ? (int) request()->query('hotel_id') : null;
    }

    public function selectRequest(int $id): void
    {
        $this->selectedId = $id;
        $this->reply_message = '';
        $this->resetValidation();
    }

    public function backToList(): void
    {
        $this->selectedId = null;
        $this->reply_message = '';
    }

    public function updateStatus(string $newStatus): void
    {
        $req = SupportRequest::find($this->selectedId);
        if (!$req || !in_array($newStatus, ['open', 'in_progress', 'resolved'])) {
            return;
        }
        $req->update(['status' => $newStatus]);
        session()->flash('message', 'Status updated.');
    }

    public function sendReply(): void
    {
        $this->validate(['reply_message' => 'required|string|min:1'], [], ['reply_message' => 'Message']);

        $req = SupportRequest::find($this->selectedId);
        if (!$req) {
            session()->flash('error', 'Request not found.');
            return;
        }

        SupportRequestResponse::create([
            'support_request_id' => $req->id,
            'user_id' => auth()->id(),
            'message' => $this->reply_message,
        ]);

        $this->reply_message = '';
        session()->flash('message', 'Reply sent.');
    }

    public function render()
    {
        $query = SupportRequest::with(['hotel', 'user'])->orderByDesc('created_at');

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }
        if ($this->hotel_id) {
            $query->where('hotel_id', $this->hotel_id);
        }

        $requests = $query->paginate(15)->withQueryString();

        $selected = null;
        if ($this->selectedId) {
            $selected = SupportRequest::with(['hotel', 'user', 'responses.user'])->find($this->selectedId);
        }

        $filterHotel = $this->hotel_id ? \App\Models\Hotel::find($this->hotel_id) : null;

        return view('livewire.ireme.ireme-requests', [
            'requests' => $requests,
            'selected' => $selected,
            'filterHotel' => $filterHotel,
        ])->layout('livewire.layouts.ireme-layout', ['title' => 'Support requests']);
    }
}
