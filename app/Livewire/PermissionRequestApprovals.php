<?php

namespace App\Livewire;

use App\Models\UserPermissionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PermissionRequestApprovals extends Component
{
    public $rejectRequestId = null;
    public $rejectionReason = '';

    public function mount()
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->hasPermission('hotel_manage_users') && !$user->hasPermission('hotel_assign_roles')) {
            abort(403, 'Only Super Admin or users with Hotel: Manage users / Assign roles permission can manage permission requests.');
        }
    }

    public function getPendingRequestsProperty()
    {
        $query = UserPermissionRequest::with(['user', 'permission', 'requestedBy'])
            ->where('status', UserPermissionRequest::STATUS_PENDING)
            ->orderByDesc('created_at');
        if (!Auth::user()->isSuperAdmin() && Auth::user()->hotel_id) {
            $query->whereHas('user', fn ($q) => $q->where('hotel_id', Auth::user()->hotel_id));
        }
        return $query->get();
    }

    public function approve($id)
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('hotel_manage_users') && !Auth::user()->hasPermission('hotel_assign_roles')) {
            session()->flash('error', 'You do not have permission to approve.');
            return;
        }
        $req = UserPermissionRequest::find($id);
        if (!$req || !$req->isPending()) {
            session()->flash('error', 'Request not found or already processed.');
            return;
        }
        $req->user->permissions()->syncWithoutDetaching([$req->permission_id]);
        $req->update([
            'status' => UserPermissionRequest::STATUS_APPROVED,
            'approved_by_id' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
        session()->flash('message', 'Permission approved. User can now use it.');
    }

    public function reject($id, $reason = null)
    {
        if (!Auth::user()->isSuperAdmin() && !Auth::user()->hasPermission('hotel_manage_users') && !Auth::user()->hasPermission('hotel_assign_roles')) {
            session()->flash('error', 'You do not have permission to reject.');
            return;
        }
        $req = UserPermissionRequest::find($id);
        if (!$req || !$req->isPending()) {
            session()->flash('error', 'Request not found or already processed.');
            return;
        }
        $req->update([
            'status' => UserPermissionRequest::STATUS_REJECTED,
            'approved_by_id' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $reason !== null && $reason !== '' ? $reason : 'Rejected by Super Admin',
        ]);
        $this->rejectRequestId = null;
        $this->rejectionReason = '';
        session()->flash('message', 'Permission request rejected.');
    }

    public function openRejectModal($id)
    {
        $this->rejectRequestId = $id;
        $this->rejectionReason = '';
    }

    public function cancelReject()
    {
        $this->rejectRequestId = null;
        $this->rejectionReason = '';
    }

    public function confirmReject()
    {
        if ($this->rejectRequestId) {
            $this->reject($this->rejectRequestId, $this->rejectionReason);
        }
    }

    public function render()
    {
        return view('livewire.permission-request-approvals')
            ->layout('livewire.layouts.app-layout');
    }
}
