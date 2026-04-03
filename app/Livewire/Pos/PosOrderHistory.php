<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\PosSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PosOrderHistory extends Component
{
    use WithPagination;

    public $date_from = '';
    public $date_to = '';
    public $filter_status = '';
    /** When true, ignore date filters and show latest orders by default. */
    public bool $firstLoad = true;

    public function mount()
    {
        $session = PosSession::getOpenForUser(Auth::id());
        if (!$session) {
            session()->flash('error', 'Open a POS session first.');
            $this->redirect(route('pos.home'), navigate: true);
            return;
        }
        $today = now()->toDateString();
        // Pre-fill inputs with today for convenience, but don't apply as filters on first load
        if ($this->date_from === '' && !request()->has('date_from')) {
            $this->date_from = $today;
        }
        if ($this->date_to === '' && !request()->has('date_to')) {
            $this->date_to = $today;
        }
        if (request()->has('date_from')) {
            $this->date_from = request('date_from');
        }
        if (request()->has('date_to')) {
            $this->date_to = request('date_to');
        }
        if (request()->has('status')) {
            $this->filter_status = request('status');
        }

        // If any filter is provided in the URL, treat this as a search (not first load)
        if (request()->hasAny(['date_from', 'date_to', 'status'])) {
            $this->firstLoad = false;
        }
    }

    public function applySearch()
    {
        $this->firstLoad = false;
        $this->resetPage();
    }

    public function getOrdersQuery()
    {
        $query = Order::with(['table', 'waiter', 'invoice' => fn ($q) => $q->with(['reservation', 'room'])])
            ->orderByDesc('created_at');

        $user = Auth::user();
        // Waiters: only see their own orders in history
        if ($user && method_exists($user, 'isWaiter') && $user->isWaiter()) {
            $query->where('waiter_id', $user->id);
        }

        // On first load, ignore date filters and just show latest orders
        if (! $this->firstLoad && $this->date_from) {
            $query->whereDate('created_at', '>=', $this->date_from);
        }
        if (! $this->firstLoad && $this->date_to) {
            $query->whereDate('created_at', '<=', $this->date_to);
        }
        if (! $this->firstLoad && $this->filter_status !== '') {
            $query->where('order_status', $this->filter_status);
        }

        return $query;
    }

    public function render()
    {
        $orders = $this->getOrdersQuery()->paginate(20);

        return view('livewire.pos.pos-order-history', [
            'orders' => $orders,
        ])->layout('livewire.layouts.app-layout');
    }
}
