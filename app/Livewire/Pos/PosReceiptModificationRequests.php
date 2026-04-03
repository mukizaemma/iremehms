<?php

namespace App\Livewire\Pos;

use App\Models\ReceiptModificationRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PosReceiptModificationRequests extends Component
{
    public function mount()
    {
        $user = Auth::user();
        if (!$user->hasPermission('pos_approve_receipt_modification') && !$user->isEffectiveGeneralManager() && !$user->canNavigateModules()) {
            abort(403, 'You do not have permission to approve receipt modification requests.');
        }
    }

    public function getPendingRequestsProperty()
    {
        return ReceiptModificationRequest::query()
            ->where('status', ReceiptModificationRequest::STATUS_PENDING)
            ->with(['invoice.order', 'requestedBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function approveRequest(int $id): void
    {
        $user = Auth::user();
        if (!$user->hasPermission('pos_approve_receipt_modification') && !$user->isEffectiveGeneralManager() && !$user->canNavigateModules()) {
            session()->flash('error', 'You do not have permission to approve.');
            return;
        }
        $req = ReceiptModificationRequest::with('invoice')->find($id);
        if (!$req || $req->status !== ReceiptModificationRequest::STATUS_PENDING) {
            session()->flash('error', 'Request not found or already processed.');
            return;
        }
        $req->update([
            'status' => ReceiptModificationRequest::STATUS_APPROVED,
            'approved_by_id' => $user->id,
            'approved_at' => now(),
        ]);
        $req->invoice->update([
            'modification_approved_for_user_id' => $req->requested_by_id,
            'modification_approved_at' => now(),
        ]);
        session()->flash('message', 'Modification request approved. The requester can now modify the receipt.');
    }

    public function rejectRequest(int $id): void
    {
        $user = Auth::user();
        if (!$user->hasPermission('pos_approve_receipt_modification') && !$user->isEffectiveGeneralManager() && !$user->canNavigateModules()) {
            session()->flash('error', 'You do not have permission to reject.');
            return;
        }
        $req = ReceiptModificationRequest::find($id);
        if (!$req || $req->status !== ReceiptModificationRequest::STATUS_PENDING) {
            session()->flash('error', 'Request not found or already processed.');
            return;
        }
        $req->update(['status' => ReceiptModificationRequest::STATUS_REJECTED]);
        session()->flash('message', 'Modification request rejected.');
    }

    public function render()
    {
        return view('livewire.pos.pos-receipt-modification-requests', [
            'pendingRequests' => $this->pendingRequests,
        ])->layout('livewire.layouts.app-layout');
    }
}
