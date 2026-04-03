<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\PosSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PosMySales extends Component
{
    public $session = null;
    public $orders = [];
    public $unpaidOrders = [];
    public $totalCollected = 0;
    public $todayTotalOrders = 0;
    public $paidOrdersCount = 0;
    public $unpaidOrdersCount = 0;
    public $paidAmount = 0;
    public $notPaidAmount = 0;
    public $shiftName = '';

    public function mount()
    {
        $this->session = PosSession::getOpenForUser(Auth::id());
        if ($this->session) {
            $this->session->load(['shiftLog.shift', 'dayShift', 'businessDay']);
        }
        if (!$this->session) {
            session()->flash('error', 'Open a POS session to see your sales.');
            return $this->redirect(route('pos.home'), navigate: true);
        }
        $this->loadData();
    }

    public function loadData()
    {
        $session = PosSession::getOpenForUser(Auth::id());
        if (!$session) {
            return;
        }
        $session->load(['shiftLog.shift', 'dayShift', 'businessDay']);
        $this->shiftName = $session->dayShift->name ?? $session->shiftLog->shift->name ?? $session->businessDay->business_date ?? 'N/A';

        $today = now()->toDateString();
        $paidOrderIds = Order::where('session_id', $session->id)
            ->where('order_status', 'PAID')
            ->whereDate('created_at', $today)
            ->pluck('id');
        $settledOrderIds = Order::where('session_id', $session->id)
            ->whereIn('order_status', ['PAID', 'CREDIT'])
            ->whereDate('created_at', $today)
            ->pluck('id');
        $this->orders = Order::with([
            'table',
            'invoice' => fn ($q) => $q->with(['payments', 'reservation', 'room', 'postedBy']),
        ])
            ->whereIn('id', $settledOrderIds)
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();

        $this->unpaidOrders = Order::with(['table', 'invoice', 'orderItems'])
            ->where('session_id', $session->id)
            ->whereIn('order_status', ['OPEN', 'CONFIRMED'])
            ->whereDate('created_at', $today)
            ->orderByDesc('updated_at')
            ->get()
            ->toArray();

        $invoiceIds = DB::table('invoices')->whereIn('order_id', $paidOrderIds)->where('invoice_status', 'PAID')->pluck('id');
        $this->totalCollected = DB::table('payments')->whereIn('invoice_id', $invoiceIds)->sum('amount');
        $this->paidAmount = $this->totalCollected;

        $this->todayTotalOrders = (int) Order::where('session_id', $session->id)->whereDate('created_at', $today)->count();
        $this->paidOrdersCount = count($this->orders);
        $this->unpaidOrdersCount = count($this->unpaidOrders);

        $this->notPaidAmount = 0;
        foreach ($this->unpaidOrders as $o) {
            if (!empty($o['invoice']['total_amount'])) {
                $this->notPaidAmount += (float) $o['invoice']['total_amount'];
            } else {
                $items = $o['order_items'] ?? $o['orderItems'] ?? [];
                foreach ($items as $item) {
                    $this->notPaidAmount += (float) ($item['line_total'] ?? 0);
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.pos.pos-my-sales')->layout('livewire.layouts.app-layout');
    }
}
