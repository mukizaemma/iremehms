<?php

namespace App\Livewire\Pos;

use App\Helpers\VatHelper;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\PaymentCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosReports extends Component
{
    public $date_from;

    public $date_to;

    public string $group_by = 'day'; // day, week, month, year

    /** '' or 'all' = every waiter; otherwise waiter user id (cashier / manager) */
    public string $waiter_filter = '';

    /** Optional name printed on export / print as “Verified by” */
    public string $verified_by_name = '';

    /** Optional name printed on export / print as “Approved by” */
    public string $approved_by_name = '';

    public $dailySummary = [];

    public $byWaiter = [];

    public $byMenuItem = [];

    /** POS collections grouped by unified payment type */
    public $byPaymentType = [];

    public $stockImpact = [];

    public $vatSummary = null;

    public $vatByMonth = [];

    public ?array $profitSummary = null;

    /** Assignment breakdown */
    public ?array $assignmentSummary = null;

    public $assignmentDetails = [];

    /** Front-office-style executive summary for waiters / cashiers */
    public array $executiveSummary = [];

    /** @var list<array{id:int,name:string}> */
    public array $waitersForFilter = [];

    /**
     * One row per calendar day (date order was marked paid) — waiter-friendly multi-day breakdown.
     *
     * @var list<array{date:string,orders_count:int,total_sales:float,amount_received:float}>
     */
    public array $reportByDate = [];

    /**
     * Every non-voided order line in range (waiter-focused detail list).
     *
     * @var list<array{sale_date:string,order_id:int|string,item_name:string,qty:float|int,unit_price:float,line_total:float}>
     */
    public array $soldLineItems = [];

    public function mount()
    {
        $user = Auth::user();
        $slug = $user->getEffectiveRole()?->slug;
        $allowed = $user->isSuperAdmin()
            || $user->canNavigateModules()
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('reports_view_all')
            || $slug === 'waiter'
            || $slug === 'cashier'
            || $user->isRestaurantManager();
        if (! $allowed) {
            abort(403, 'You do not have access to sales reports.');
        }

        $hotel = Hotel::getHotel();
        $today = $hotel ? Hotel::getTodayForHotel() : now()->format('Y-m-d');
        $this->date_from = request('date_from', $today);
        $this->date_to = request('date_to', $today);
        $this->group_by = request('group_by', 'day');
        $this->waiter_filter = (string) request('waiter_id', '');
        if ($this->canFilterByWaiter() && $this->waiter_filter === '') {
            $this->waiter_filter = 'all';
        }

        if ($this->canFilterByWaiter() && $hotel) {
            $this->waitersForFilter = User::activeInHotelWithRoleSlug($hotel->id, 'waiter')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->all();
        }

        $this->loadReports();
    }

    public function canFilterByWaiter(): bool
    {
        $user = Auth::user();
        $slug = $user->getEffectiveRole()?->slug;
        if ($slug === 'waiter') {
            return false;
        }

        return $slug === 'cashier'
            || $user->canNavigateModules()
            || $user->hasPermission('pos_audit')
            || $user->hasPermission('pos_full_oversight')
            || $user->hasPermission('reports_view_all')
            || $user->isRestaurantManager()
            || $user->isSuperAdmin();
    }

    public function isStaffSalesReportUser(): bool
    {
        $slug = Auth::user()->getEffectiveRole()?->slug;

        return in_array($slug, ['waiter', 'cashier'], true);
    }

    /** Waiter UI: daily table replaces group-by / “sales over time” for multi-day ranges. */
    public function isWaiterOnlyReportLayout(): bool
    {
        return Auth::user()->getEffectiveRole()?->slug === 'waiter';
    }

    /** Cashier UI: same compact layout as waiter (then add cashier-only breakdown cards). */
    public function isCashierOnlyReportLayout(): bool
    {
        return Auth::user()->getEffectiveRole()?->slug === 'cashier';
    }

    /** Shared compact layout for waiter + cashier. */
    public function isWaiterLikeReportLayout(): bool
    {
        return $this->isWaiterOnlyReportLayout() || $this->isCashierOnlyReportLayout();
    }

    public function setToday(): void
    {
        $hotel = Hotel::getHotel();
        $today = $hotel ? Hotel::getTodayForHotel() : now()->format('Y-m-d');
        $this->date_from = $today;
        $this->date_to = $today;
        $this->loadReports();
    }

    public function updatedWaiterFilter(): void
    {
        if ($this->canFilterByWaiter()) {
            $this->loadReports();
        }
    }

    public function loadReports()
    {
        $from = $this->date_from.' 00:00:00';
        $to = $this->date_to.' 23:59:59';
        $user = Auth::user();
        $hotel = Hotel::getHotel();
        $effectiveSlug = $user->getEffectiveRole()?->slug;
        $waiterOnly = $effectiveSlug === 'waiter';

        $filterWaiterId = null;
        if ($waiterOnly) {
            $filterWaiterId = (int) $user->id;
        } elseif ($this->canFilterByWaiter() && $this->waiter_filter !== '' && $this->waiter_filter !== 'all') {
            $wid = (int) $this->waiter_filter;
            if ($wid > 0 && $hotel) {
                $valid = User::query()
                    ->where('id', $wid)
                    ->where('hotel_id', $hotel->id)
                    ->whereHas('role', fn ($q) => $q->where('slug', 'waiter'))
                    ->exists();
                if ($valid) {
                    $filterWaiterId = $wid;
                }
            }
        }

        $groupExpr = 'DATE(orders.updated_at)';
        switch ($this->group_by) {
            case 'week':
                $groupExpr = "DATE_FORMAT(orders.updated_at, '%x-W%v')";
                break;
            case 'month':
                $groupExpr = "DATE_FORMAT(orders.updated_at, '%Y-%m')";
                break;
            case 'year':
                $groupExpr = "DATE_FORMAT(orders.updated_at, '%Y')";
                break;
            case 'day':
            default:
                $groupExpr = 'DATE(orders.updated_at)';
                break;
        }

        $orderQuery = Order::where('order_status', 'PAID')
            ->whereBetween('updated_at', [$from, $to]);

        if ($hotel) {
            $orderQuery->whereHas('waiter', function ($q) use ($hotel) {
                $q->where('hotel_id', $hotel->id);
            });
        }
        if ($filterWaiterId !== null) {
            $orderQuery->where('waiter_id', $filterWaiterId);
        }
        $orderIds = $orderQuery->pluck('id');
        if ($orderIds->isEmpty()) {
            $this->dailySummary = [];
            $this->byWaiter = [];
            $this->byMenuItem = [];
            $this->byPaymentType = [];
            $this->stockImpact = [];
            $this->vatSummary = ['total_sales' => 0, 'total_net' => 0, 'total_vat' => 0];
            $this->profitSummary = [
                'total_sales' => 0,
                'cogs' => 0,
                'gross_profit' => 0,
                'gross_margin' => 0,
            ];
            $this->vatByMonth = [];
            $this->assignmentSummary = [
                'total_sales' => 0,
                'amount_received' => 0,
                'amount_assigned_to_rooms' => 0,
                'amount_assigned_to_hotel' => 0,
            ];
            $this->assignmentDetails = [];
            $this->executiveSummary = $this->emptyExecutiveSummary();
            $this->reportByDate = [];
            $this->soldLineItems = [];

            return;
        }

        $invoiceIds = DB::table('invoices')->whereIn('order_id', $orderIds)->pluck('id');

        $dailyQuery = DB::table('orders')
            ->join('invoices', 'orders.id', '=', 'invoices.order_id')
            ->whereIn('orders.id', $orderIds);
        // Cast each row to array so Blade/Livewire never sees stdClass (fixes $r['key'] in views).
        $this->dailySummary = array_values(array_map(
            static fn ($r) => (array) $r,
            $dailyQuery
                ->select(DB::raw("$groupExpr as period"), DB::raw('count(*) as orders_count'), DB::raw('sum(invoices.total_amount) as total'))
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->all()
        ));

        if (! $waiterOnly) {
            $salesByDate = DB::table('orders')
                ->join('invoices', 'orders.id', '=', 'invoices.order_id')
                ->whereIn('orders.id', $orderIds)
                ->select(
                    DB::raw('DATE(orders.updated_at) as report_date'),
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(invoices.total_amount) as total_sales')
                )
                ->groupBy(DB::raw('DATE(orders.updated_at)'))
                ->orderBy('report_date')
                ->get()
                ->keyBy(fn ($r) => (string) $r->report_date);

            $paymentsByDate = DB::table('payments')
                ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
                ->join('orders', 'invoices.order_id', '=', 'orders.id')
                ->whereIn('orders.id', $orderIds)
                ->select(
                    DB::raw('DATE(orders.updated_at) as report_date'),
                    DB::raw('SUM(payments.amount) as amount_received')
                )
                ->groupBy(DB::raw('DATE(orders.updated_at)'))
                ->get()
                ->keyBy(fn ($r) => (string) $r->report_date);

            $allReportDates = $salesByDate->keys()->merge($paymentsByDate->keys())->unique()->sort()->values();
            $this->reportByDate = $allReportDates->map(function (string $d) use ($salesByDate, $paymentsByDate) {
                $s = $salesByDate->get($d);
                $p = $paymentsByDate->get($d);

                return [
                    'date' => $d,
                    'orders_count' => (int) ($s->orders_count ?? 0),
                    'total_sales' => (float) ($s->total_sales ?? 0),
                    'amount_received' => (float) ($p->amount_received ?? 0),
                ];
            })->all();
        } else {
            $this->reportByDate = [];
        }

        $this->soldLineItems = array_values(array_map(
            static fn ($r) => (array) $r,
            DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.menu_item_id')
                ->whereIn('orders.id', $orderIds)
                ->whereNull('order_items.voided_at')
                ->select(
                    DB::raw('DATE(orders.updated_at) as sale_date'),
                    'orders.id as order_id',
                    'menu_items.name as item_name',
                    'order_items.quantity as qty',
                    'order_items.unit_price as unit_price',
                    'order_items.line_total as line_total'
                )
                ->orderBy('orders.updated_at')
                ->orderBy('orders.id')
                ->orderBy('order_items.id')
                ->get()
                ->all()
        ));

        $waiterQuery = DB::table('orders')
            ->whereIn('orders.id', $orderIds)
            ->join('invoices', 'orders.id', '=', 'invoices.order_id')
            ->join('users', 'orders.waiter_id', '=', 'users.id');
        $this->byWaiter = array_values(array_map(
            static fn ($r) => (array) $r,
            $waiterQuery
                ->select('users.name as waiter_name', DB::raw('count(*) as orders_count'), DB::raw('sum(invoices.total_amount) as total'))
                ->groupBy('orders.waiter_id', 'users.name')
                ->get()
                ->all()
        ));

        $menuItemQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.menu_item_id')
            ->whereIn('orders.id', $orderIds);
        $this->byMenuItem = array_values(array_map(
            static fn ($r) => (array) $r,
            $menuItemQuery
                ->select(
                    'menu_items.name as item_name',
                    DB::raw('sum(order_items.quantity) as qty'),
                    DB::raw('sum(order_items.line_total) as total'),
                    DB::raw('CASE WHEN SUM(order_items.quantity)=0 THEN 0 ELSE SUM(order_items.line_total)/SUM(order_items.quantity) END as sale_price')
                )
                ->groupBy('order_items.menu_item_id', 'menu_items.name')
                ->orderByDesc('total')
                ->get()
                ->all()
        ));

        $paymentRows = DB::table('payments')
            ->whereIn('invoice_id', $invoiceIds)
            ->select('payment_method', 'payment_status', DB::raw('sum(amount) as total'))
            ->groupBy('payment_method', 'payment_status')
            ->get();

        $buckets = [];
        foreach ($paymentRows as $pr) {
            $m = PaymentCatalog::normalizePosMethod($pr->payment_method);
            $key = PaymentCatalog::accommodationPaymentReportBucket($m, $pr->payment_status ?? '');
            $t = (float) ($pr->total ?? 0);
            $buckets[$key] = ($buckets[$key] ?? 0) + $t;
        }
        $labels = PaymentCatalog::accommodationReportBucketLabels();
        $this->byPaymentType = collect($buckets)
            ->map(fn (float $total, string $key) => [
                'label' => $labels[$key] ?? $key,
                'total' => $total,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $orderItemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');
        $this->stockImpact = StockMovement::where('movement_type', 'SALE')
            ->whereIn('order_item_id', $orderItemIds)
            ->join('stocks', 'stock_movements.stock_id', '=', 'stocks.id')
            ->select('stocks.name as stock_name', DB::raw('sum(ABS(stock_movements.quantity)) as qty_out'), DB::raw('sum(stock_movements.total_value) as cost_value'))
            ->groupBy('stock_movements.stock_id', 'stocks.name')
            ->get()
            ->toArray();

        $totalSales = (float) DB::table('invoices')
            ->whereIn('order_id', $orderIds)
            ->where('invoice_status', 'PAID')
            ->sum('total_amount');
        $vatBreakdown = VatHelper::fromInclusive($totalSales);
        $this->vatSummary = [
            'total_sales' => $totalSales,
            'total_net' => $vatBreakdown['net'],
            'total_vat' => $vatBreakdown['vat'],
        ];

        $totalCogs = collect($this->stockImpact)->sum(function ($row) {
            return (float) ($row['cost_value'] ?? 0);
        });
        $grossProfit = $totalSales - $totalCogs;
        $this->profitSummary = [
            'total_sales' => $totalSales,
            'cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $totalSales > 0 ? $grossProfit / $totalSales : 0,
        ];

        $vatMonthQuery = DB::table('orders')
            ->join('invoices', 'orders.id', '=', 'invoices.order_id')
            ->whereIn('orders.id', $orderIds);
        $this->vatByMonth = $vatMonthQuery
            ->select(
                DB::raw('DATE_FORMAT(orders.updated_at, "%Y-%m") as month'),
                DB::raw('SUM(invoices.total_amount) as total_sales')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                $breakdown = VatHelper::fromInclusive((float) $row->total_sales);

                return [
                    'month' => $row->month,
                    'total_sales' => (float) $row->total_sales,
                    'total_net' => $breakdown['net'],
                    'total_vat' => $breakdown['vat'],
                ];
            })
            ->toArray();

        $amountReceived = (float) DB::table('payments')->whereIn('invoice_id', $invoiceIds)->sum('amount');
        $amountAssignedToRooms = (float) DB::table('invoices')->whereIn('order_id', $orderIds)->where('invoice_status', 'PAID')->where('charge_type', Invoice::CHARGE_TYPE_ROOM)->sum('total_amount');
        $amountAssignedToHotel = (float) DB::table('invoices')->whereIn('order_id', $orderIds)->where('invoice_status', 'PAID')->where('charge_type', Invoice::CHARGE_TYPE_HOTEL_COVERED)->sum('total_amount');
        $this->assignmentSummary = [
            'total_sales' => $totalSales,
            'amount_received' => $amountReceived,
            'amount_assigned_to_rooms' => $amountAssignedToRooms,
            'amount_assigned_to_hotel' => $amountAssignedToHotel,
        ];

        $this->assignmentDetails = Invoice::query()
            ->whereIn('order_id', $orderIds)
            ->where('invoice_status', 'PAID')
            ->whereIn('charge_type', [Invoice::CHARGE_TYPE_ROOM, Invoice::CHARGE_TYPE_HOTEL_COVERED])
            ->with(['reservation', 'room', 'postedBy', 'order'])
            ->orderByDesc('assigned_at')
            ->get()
            ->map(function ($inv) {
                $row = [
                    'invoice_number' => $inv->invoice_number,
                    'total_amount' => (float) $inv->total_amount,
                    'charge_type' => $inv->charge_type,
                    'assigned_by' => $inv->postedBy?->name ?? '—',
                    'assigned_at' => $inv->assigned_at?->format('d M Y H:i'),
                ];
                if ($inv->charge_type === Invoice::CHARGE_TYPE_ROOM) {
                    $row['guest_name'] = $inv->reservation?->guest_name ?? '—';
                    $row['room_number'] = $inv->room?->room_number ?? '—';
                    $row['checkout'] = $inv->reservation ? ($inv->reservation->check_out_date?->format('d M Y').($inv->reservation->check_out_time ? ' '.\Carbon\Carbon::parse($inv->reservation->check_out_time)->format('H:i') : '')) : '—';
                } else {
                    $row['hotel_covered_names'] = $inv->hotel_covered_names ?? '—';
                    $row['hotel_covered_reason'] = $inv->hotel_covered_reason ?? '—';
                }

                return $row;
            })
            ->toArray();

        $cash = (float) ($buckets[PaymentCatalog::METHOD_CASH] ?? 0);
        $momo = (float) ($buckets[PaymentCatalog::METHOD_MOMO] ?? 0);
        $posCard = (float) ($buckets[PaymentCatalog::METHOD_POS_CARD] ?? 0);
        $bank = (float) ($buckets[PaymentCatalog::METHOD_BANK] ?? 0);
        $pending = (float) ($buckets[PaymentCatalog::STATUS_PENDING] ?? 0);
        $debits = (float) ($buckets[PaymentCatalog::STATUS_DEBITS] ?? 0);
        $offer = (float) ($buckets[PaymentCatalog::STATUS_OFFER] ?? 0);
        $paidCollected = $cash + $momo + $posCard + $bank;
        $ordersCount = $orderIds->count();
        $notPaidCombined = $pending + $debits;

        $this->executiveSummary = [
            'orders_count' => $ordersCount,
            'total_sales' => $totalSales,
            'paid_amount' => $paidCollected,
            'not_paid_amount' => $notPaidCombined,
            'amount_received_total' => $amountReceived,
            'cash' => $cash,
            'momo' => $momo,
            'pos_card' => $posCard,
            'bank' => $bank,
            'pending' => $pending,
            'debits' => $debits,
            'offer' => $offer,
            'assigned_room' => $amountAssignedToRooms,
            'assigned_hotel_meeting' => $amountAssignedToHotel,
        ];
    }

    protected function emptyExecutiveSummary(): array
    {
        return [
            'orders_count' => 0,
            'total_sales' => 0,
            'paid_amount' => 0,
            'not_paid_amount' => 0,
            'amount_received_total' => 0,
            'cash' => 0,
            'momo' => 0,
            'pos_card' => 0,
            'bank' => 0,
            'pending' => 0,
            'debits' => 0,
            'offer' => 0,
            'assigned_room' => 0,
            'assigned_hotel_meeting' => 0,
        ];
    }

    public function applyFilter()
    {
        $this->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);
        $this->loadReports();
    }

    public function updatedGroupBy(): void
    {
        $this->loadReports();
    }

    public function exportCsv(): StreamedResponse
    {
        $this->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);
        $this->loadReports();

        $user = Auth::user();
        $hotel = Hotel::getHotel();
        $es = $this->executiveSummary;
        $filename = 'pos-sales-'.$this->date_from.'-'.$this->date_to.'.csv';
        $roleSlug = $user->getEffectiveRole()?->slug;
        $isCompactExport = in_array($roleSlug, ['waiter', 'cashier'], true);

        return response()->streamDownload(function () use ($es, $user, $hotel, $isCompactExport, $roleSlug) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['POS sales report']);
            fputcsv($out, ['Hotel', $hotel?->name ?? '']);
            fputcsv($out, ['Period', $this->date_from.' to '.$this->date_to]);
            fputcsv($out, ['Prepared by', $user->name]);
            fputcsv($out, ['Verified by', $this->verified_by_name !== '' ? $this->verified_by_name : '']);
            fputcsv($out, ['Approved by', $this->approved_by_name !== '' ? $this->approved_by_name : '']);
            fputcsv($out, []);

            if ($isCompactExport) {
                fputcsv($out, ['Summary (no duplicate breakdowns)']);
                fputcsv($out, ['Metric', 'Value']);
                fputcsv($out, ['Orders (paid)', (string) ($es['orders_count'] ?? 0)]);
                fputcsv($out, ['Total sales (incl. VAT)', (string) ($es['total_sales'] ?? 0)]);
                fputcsv($out, ['Payments recorded (total)', (string) ($es['amount_received_total'] ?? 0)]);
                fputcsv($out, ['Paid (Cash + MoMo + POS + Bank)', (string) ($es['paid_amount'] ?? 0)]);
                fputcsv($out, ['Not paid (Pending + Debit / on account)', (string) ($es['not_paid_amount'] ?? 0)]);
                fputcsv($out, ['Assigned to room (folio)', (string) ($es['assigned_room'] ?? 0)]);
                fputcsv($out, ['Hotel / meeting covered', (string) ($es['assigned_hotel_meeting'] ?? 0)]);
                fputcsv($out, []);

                // Payment methods (same tiles shown in print view)
                fputcsv($out, ['Payment methods']);
                fputcsv($out, ['Type', 'Amount']);
                fputcsv($out, ['Cash', (string) ($es['cash'] ?? 0)]);
                fputcsv($out, ['MoMo', (string) ($es['momo'] ?? 0)]);
                fputcsv($out, ['POS / Card', (string) ($es['pos_card'] ?? 0)]);
                fputcsv($out, ['Bank', (string) ($es['bank'] ?? 0)]);
                fputcsv($out, ['Pending', (string) ($es['pending'] ?? 0)]);
                fputcsv($out, ['Debit / on account', (string) ($es['debits'] ?? 0)]);
                fputcsv($out, ['Offer (complimentary)', (string) ($es['offer'] ?? 0)]);
                fputcsv($out, []);

                fputcsv($out, ['Sold items (each line; date = order marked paid)']);
                fputcsv($out, ['Date', 'Order #', 'Item', 'Qty', 'Unit price', 'Line total']);
                $lineSum = 0.0;
                foreach ($this->soldLineItems as $line) {
                    $lt = (float) ($line['line_total'] ?? 0);
                    $lineSum += $lt;
                    fputcsv($out, [
                        (string) ($line['sale_date'] ?? ''),
                        (string) ($line['order_id'] ?? ''),
                        (string) ($line['item_name'] ?? ''),
                        (string) ($line['qty'] ?? 0),
                        (string) ($line['unit_price'] ?? 0),
                        (string) $lt,
                    ]);
                }
                fputcsv($out, ['TOTAL', '', '', '', '', (string) $lineSum]);

                if ($roleSlug === 'cashier') {
                    // Cashier-specific breakdown cards (same as print layout)
                    fputcsv($out, []);
                    fputcsv($out, ['By waiter']);
                    fputcsv($out, ['Waiter', 'Orders', 'Total']);
                    foreach ($this->byWaiter as $r) {
                        fputcsv($out, [
                            $r['waiter_name'] ?? '',
                            (string) ($r['orders_count'] ?? 0),
                            (string) ($r['total'] ?? 0),
                        ]);
                    }

                    fputcsv($out, []);
                    fputcsv($out, ['Sales by menu item']);
                    fputcsv($out, ['Menu item', 'Sale price', 'Qty sold', 'Amount']);
                    foreach ($this->byMenuItem as $r) {
                        fputcsv($out, [
                            $r['item_name'] ?? '',
                            (string) ($r['sale_price'] ?? 0),
                            (string) ($r['qty'] ?? 0),
                            (string) ($r['total'] ?? 0),
                        ]);
                    }
                    $menuTotal = (float) collect($this->byMenuItem)->sum(fn ($r) => (float) ($r['total'] ?? 0));
                    fputcsv($out, ['TOTAL', '', '', (string) $menuTotal]);
                }
                fclose($out);
                return;
            }

            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Orders (paid)', (string) ($es['orders_count'] ?? 0)]);
            fputcsv($out, ['Total sales (incl. VAT)', (string) ($es['total_sales'] ?? 0)]);
            fputcsv($out, ['Paid amount (Cash + MoMo + POS + Bank)', (string) ($es['paid_amount'] ?? 0)]);
            fputcsv($out, ['Not paid (Pending + Debit / on account)', (string) ($es['not_paid_amount'] ?? 0)]);
            fputcsv($out, ['Total payment lines recorded', (string) ($es['amount_received_total'] ?? 0)]);
            fputcsv($out, ['Cash', (string) ($es['cash'] ?? 0)]);
            fputcsv($out, ['MoMo', (string) ($es['momo'] ?? 0)]);
            fputcsv($out, ['POS / Card', (string) ($es['pos_card'] ?? 0)]);
            fputcsv($out, ['Bank', (string) ($es['bank'] ?? 0)]);
            fputcsv($out, ['Pending', (string) ($es['pending'] ?? 0)]);
            fputcsv($out, ['Debit / on account', (string) ($es['debits'] ?? 0)]);
            fputcsv($out, ['Offer (complimentary)', (string) ($es['offer'] ?? 0)]);
            fputcsv($out, ['Assigned to room (folio)', (string) ($es['assigned_room'] ?? 0)]);
            fputcsv($out, ['Hotel / meeting covered', (string) ($es['assigned_hotel_meeting'] ?? 0)]);
            fputcsv($out, []);
            if (count($this->reportByDate) > 0) {
                fputcsv($out, ['Sales by date (order paid date)']);
                fputcsv($out, ['Date', 'Orders (paid)', 'Total sales (incl. VAT)', 'Payments recorded']);
                foreach ($this->reportByDate as $row) {
                    fputcsv($out, [
                        $row['date'] ?? '',
                        (string) ($row['orders_count'] ?? 0),
                        (string) ($row['total_sales'] ?? 0),
                        (string) ($row['amount_received'] ?? 0),
                    ]);
                }
                fputcsv($out, [
                    'TOTAL',
                    (string) collect($this->reportByDate)->sum(fn ($r) => (int) ($r['orders_count'] ?? 0)),
                    (string) collect($this->reportByDate)->sum(fn ($r) => (float) ($r['total_sales'] ?? 0)),
                    (string) collect($this->reportByDate)->sum(fn ($r) => (float) ($r['amount_received'] ?? 0)),
                ]);
                fputcsv($out, []);
            }
            fputcsv($out, ['Payment type (detail)', 'Amount']);
            foreach ($this->byPaymentType as $row) {
                fputcsv($out, [$row['label'], (string) $row['total']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Menu item (detail)', 'Sale price', 'Qty sold', 'Amount']);
            foreach ($this->byMenuItem as $r) {
                fputcsv($out, [
                    $r['item_name'] ?? '',
                    (string) ($r['sale_price'] ?? 0),
                    (string) ($r['qty'] ?? 0),
                    (string) ($r['total'] ?? 0),
                ]);
            }
            $menuTotal = (float) collect($this->byMenuItem)->sum(fn ($r) => (float) ($r['total'] ?? 0));
            fputcsv($out, ['TOTAL', '', '', (string) $menuTotal]);
            fputcsv($out, []);
            fputcsv($out, ['Waiter', 'Orders', 'Total']);
            foreach ($this->byWaiter as $r) {
                fputcsv($out, [$r['waiter_name'] ?? '', (string) ($r['orders_count'] ?? 0), (string) ($r['total'] ?? 0)]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render()
    {
        return view('livewire.pos.pos-reports')->layout('livewire.layouts.app-layout');
    }
}
