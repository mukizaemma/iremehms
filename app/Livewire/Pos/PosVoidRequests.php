<?php

namespace App\Livewire\Pos;

use App\Models\Hotel;
use App\Models\OrderItemVoidRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityLogModule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PosVoidRequests extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $status = 'pending'; // all, pending, approved, rejected
    public ?int $waiter_id = null;

    /** Expanded row to show approve/reject options */
    public ?int $expandedRequestId = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403, 'You must be logged in.');
        }
        $hasRestaurant = $user->getAccessibleModules()->contains('slug', 'restaurant');
        if (! $hasRestaurant) {
            abort(403, 'You do not have access to POS. Cannot view void requests.');
        }
    }

    /** User can approve/reject void requests (managers or granted permission). */
    public function getCanApproveVoidProperty(): bool
    {
        return (bool) Auth::user()?->hasPermission('pos_approve_void');
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingWaiterId(): void
    {
        $this->resetPage();
    }

    public function getRequestsProperty()
    {
        $user = Auth::user();
        $hotel = Hotel::getHotel();

        $query = OrderItemVoidRequest::with(['orderItem.order.table', 'orderItem.menuItem', 'requestedBy', 'approvedBy'])
            ->orderByDesc('created_at');

        if ($hotel) {
            $query->whereHas('orderItem.order.waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            });
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->waiter_id) {
            $query->whereHas('orderItem.order', function ($q) {
                $q->where('waiter_id', $this->waiter_id);
            });
        }

        // Users without approve permission see only requests they submitted
        if (! $user->hasPermission('pos_approve_void')) {
            $query->where('requested_by_id', $user->id);
        }

        return $query->paginate(20);
    }

    public function toggleExpand(int $requestId): void
    {
        $this->expandedRequestId = $this->expandedRequestId === $requestId ? null : $requestId;
    }

    public function approveVoidRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_approve_void')) {
            session()->flash('error', 'You do not have permission to approve void requests.');
            return;
        }
        $request = OrderItemVoidRequest::with('orderItem.order')->find($requestId);
        if (! $request || $request->status !== OrderItemVoidRequest::STATUS_PENDING) {
            session()->flash('error', 'Invalid or already resolved request.');
            return;
        }
        $request->orderItem->update([
            'voided_at' => now(),
            'voided_by_id' => $user->id,
        ]);
        $request->update([
            'status' => OrderItemVoidRequest::STATUS_APPROVED,
            'approved_by_id' => $user->id,
            'resolved_at' => now(),
        ]);

        ActivityLogger::log(
            'pos.void_request_approved',
            sprintf(
                'Approved POS void request #%s (order #%s)',
                $request->id,
                $request->orderItem->order_id ?? '—'
            ),
            OrderItemVoidRequest::class,
            $request->id,
            ['status' => OrderItemVoidRequest::STATUS_PENDING],
            ['status' => OrderItemVoidRequest::STATUS_APPROVED],
            ActivityLogModule::POS
        );

        $this->expandedRequestId = null;
        session()->flash('message', 'Void request approved. Kitchen/bar will see the item as voided.');
    }

    public function rejectVoidRequest(int $requestId): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('pos_approve_void')) {
            session()->flash('error', 'You do not have permission to reject void requests.');
            return;
        }
        $request = OrderItemVoidRequest::with('orderItem')->find($requestId);
        if (! $request || $request->status !== OrderItemVoidRequest::STATUS_PENDING) {
            session()->flash('error', 'Invalid or already resolved request.');
            return;
        }
        $request->update([
            'status' => OrderItemVoidRequest::STATUS_REJECTED,
            'approved_by_id' => $user->id,
            'resolved_at' => now(),
        ]);

        ActivityLogger::log(
            'pos.void_request_rejected',
            sprintf(
                'Rejected POS void request #%s (order #%s)',
                $request->id,
                $request->orderItem->order_id ?? '—'
            ),
            OrderItemVoidRequest::class,
            $request->id,
            ['status' => OrderItemVoidRequest::STATUS_PENDING],
            ['status' => OrderItemVoidRequest::STATUS_REJECTED],
            ActivityLogModule::POS
        );

        $this->expandedRequestId = null;
        session()->flash('message', 'Void request rejected.');
    }

    public function render()
    {
        $hotel = Hotel::getHotel();
        $waiters = User::activeInHotelWithRoleSlug($hotel?->id, 'waiter')
            ->orderBy('name')
            ->get();

        return view('livewire.pos.pos-void-requests', [
            'requests' => $this->requests,
            'waiters' => $waiters,
            'canApproveVoid' => $this->canApproveVoid,
        ])->layout('livewire.layouts.app-layout');
    }
}

