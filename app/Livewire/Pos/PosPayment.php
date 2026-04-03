<?php

namespace App\Livewire\Pos;

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ReceiptModificationRequest;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\ActivityLogger;
use App\Services\StockDeductionService;
use App\Support\ActivityLogModule;
use App\Support\PaymentCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PosPayment extends Component
{
    public $invoiceId;
    public $invoice = null;
    /** Single field: Cash, MoMo, POS Card, Bank, Pending, Debits, Offer */
    public $payment_unified = 'Cash';
    public $payment_client_reference = '';
    public $amount = '';
    public $tip_amount = '0';
    public $tip_handling = 'HOTEL'; // 'WAITER' | 'HOTEL'
    public $cash_submit_later = false; // true = waiter keeps cash, submitted_at null
    /** Helpers for split bills */
    public int $split_parts = 2;
    /** Assign invoice: none | room | hotel_covered */
    public string $assignment_type = 'none';
    /** Post to room: search term and selected reservation */
    public string $room_search = '';
    public ?int $room_reservation_id = null;
    /** Hotel covered: names and reason */
    public string $hotel_covered_names = '';
    public string $hotel_covered_reason = '';

    /** Receipt modification request (when locked, only creator can request) */
    public string $modification_request_reason = '';

    /** Permissions */
    public bool $canReceivePayment = false;
    public bool $canSplitBill = false;

    public function mount($invoice)
    {
        $this->requireSession();
        $user = Auth::user();
        if (! $user) {
            session()->flash('error', 'You must be logged in to manage payments.');
            return $this->redirect(route('pos.orders'), navigate: true);
        }
        $this->canReceivePayment = $user->hasPermission('pos_confirm_payment');
        $this->canSplitBill = $user->hasPermission('pos_split_bill');
        if (! $this->canReceivePayment && ! $this->canSplitBill) {
            session()->flash('error', 'You do not have permission to receive payments or split bills.');
            return $this->redirect(route('pos.orders'), navigate: true);
        }
        $this->invoiceId = $invoice;
        $this->refreshInvoice();
        if ($this->invoice && $this->invoice->isPaid() && ! $this->canSplitBill) {
            session()->flash('message', 'Invoice already paid.');
            return $this->redirect(route('pos.receipt', ['order' => $this->invoice->order_id]), navigate: true);
        }
        $this->payment_unified = PaymentCatalog::METHOD_CASH;
        $this->payment_client_reference = '';
        $this->fillAmountWithBalance();
        $this->hydrateAssignmentFromInvoice();
    }

    /** Whether the current user can modify this (confirmed) receipt. Authorized users or approved request. */
    public function getCanModifyReceiptProperty(): bool
    {
        $user = Auth::user();
        return !$this->invoice || $this->invoice->canBeModifiedBy($user);
    }

    /** Whether the current user can submit a modification request (order creator/controller only). */
    public function getCanRequestModificationProperty(): bool
    {
        $user = Auth::user();
        return $this->invoice && $this->invoice->canRequestModificationBy($user);
    }

    /** Whether there is a pending modification request from the current user for this invoice. */
    public function getHasPendingModificationRequestProperty(): bool
    {
        if (!$this->invoice || !Auth::id()) {
            return false;
        }
        return $this->invoice->modificationRequests()
            ->where('requested_by_id', Auth::id())
            ->where('status', ReceiptModificationRequest::STATUS_PENDING)
            ->exists();
    }

    protected function hydrateAssignmentFromInvoice(): void
    {
        if (!$this->invoice) {
            return;
        }
        if ($this->invoice->charge_type === Invoice::CHARGE_TYPE_ROOM && $this->invoice->reservation_id) {
            $this->assignment_type = 'room';
            $this->room_reservation_id = $this->invoice->reservation_id;
        } elseif ($this->invoice->charge_type === Invoice::CHARGE_TYPE_HOTEL_COVERED) {
            $this->assignment_type = 'hotel_covered';
            $this->hotel_covered_names = (string) $this->invoice->hotel_covered_names;
            $this->hotel_covered_reason = (string) $this->invoice->hotel_covered_reason;
        } else {
            $this->assignment_type = 'none';
        }
    }

    protected function refreshInvoice(): void
    {
        $this->invoice = Invoice::with(['order.orderItems.menuItem', 'payments', 'reservation', 'room', 'modificationRequests'])->find($this->invoiceId);
        if (!$this->invoice) {
            session()->flash('error', 'Invoice not found.');
            $this->redirect(route('pos.orders'), navigate: true);
        }
    }

    /**
     * In-house reservations for "Assign to guest room" (room number, guest name(s), checkout).
     */
    public function getInHouseReservationsProperty()
    {
        $hotel = Hotel::getHotel();
        if (!$hotel) {
            return collect();
        }
        $query = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', \App\Models\Reservation::STATUS_CHECKED_IN)
            ->where('check_out_date', '>=', now()->toDateString())
            ->with(['roomUnits.room']);
        if ($this->room_search !== '') {
            $term = trim($this->room_search);
            $query->where(function ($q) use ($term) {
                $q->where('guest_name', 'like', '%' . $term . '%')
                    ->orWhere('reservation_number', 'like', '%' . $term . '%')
                    ->orWhereHas('roomUnits.room', fn ($r) => $r->where('room_number', 'like', '%' . $term . '%')->orWhere('name', 'like', '%' . $term . '%'));
            });
        }
        return $query->orderBy('check_out_date')->orderBy('check_out_time')->limit(50)->get();
    }

    /**
     * Apply assignment (room or hotel covered) to invoice. Call when recording payment or pay later.
     */
    protected function applyAssignment(Invoice $invoice): void
    {
        $userId = Auth::id();
        $invoice->update([
            'reservation_id' => null,
            'room_id' => null,
            'charge_type' => Invoice::CHARGE_TYPE_POS,
            'posted_by_id' => null,
            'hotel_covered_names' => null,
            'hotel_covered_reason' => null,
            'assigned_at' => null,
        ]);
        if ($this->assignment_type === 'room' && $this->room_reservation_id) {
            $res = Reservation::with('roomUnits.room')->find($this->room_reservation_id);
            if ($res) {
                $roomId = null;
                $firstUnit = $res->roomUnits->first();
                if ($firstUnit && $firstUnit->room) {
                    $roomId = $firstUnit->room->id;
                }
                $invoice->update([
                    'reservation_id' => $res->id,
                    'room_id' => $roomId,
                    'charge_type' => Invoice::CHARGE_TYPE_ROOM,
                    'posted_by_id' => $userId,
                    'assigned_at' => now(),
                ]);
            }
        } elseif ($this->assignment_type === 'hotel_covered') {
            $names = trim($this->hotel_covered_names);
            $reason = trim($this->hotel_covered_reason);
            if ($names !== '' || $reason !== '') {
                $invoice->update([
                    'charge_type' => Invoice::CHARGE_TYPE_HOTEL_COVERED,
                    'hotel_covered_names' => $names ?: null,
                    'hotel_covered_reason' => $reason ?: null,
                    'posted_by_id' => $userId,
                    'assigned_at' => now(),
                ]);
            }
        }
    }

    public function fillAmountWithBalance(): void
    {
        if ($this->invoice && $this->invoice->balance > 0) {
            $this->amount = (string) number_format($this->invoice->balance, 2, '.', '');
        }
    }

    protected function requireSession()
    {
        if (!PosSession::getOpenForUser(Auth::id())) {
            session()->flash('error', 'Open a POS session first.');
            return $this->redirect(route('pos.home'), navigate: true);
        }
    }

    public function suggestSplitAmount(?int $parts = null): void
    {
        $this->refreshInvoice();
        if (! $this->invoice) {
            return;
        }
        $parts = $parts ?: $this->split_parts;
        $parts = max(1, (int) $parts);
        if ($parts < 1) {
            return;
        }
        // When there is an outstanding balance, we split the balance.
        // When the invoice is fully paid, we still allow splitting the
        // total invoice amount so staff can help guests divide the bill.
        $baseAmount = $this->invoice->balance > 0
            ? $this->invoice->balance
            : (float) $this->invoice->total_amount;
        if ($baseAmount <= 0) {
            return;
        }
        $share = $baseAmount / $parts;
        $this->amount = (string) number_format($share, 2, '.', '');
    }

    public function updatedSplitParts($value): void
    {
        $this->split_parts = max(1, (int) $value);
    }

    public function addPayment()
    {
        if (! $this->canReceivePayment) {
            session()->flash('error', 'You are not allowed to record payments. Ask the cashier or manager.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if ($invoice && $invoice->isModificationLocked() && !$invoice->canBeModifiedBy(Auth::user())) {
            session()->flash('error', 'This receipt is confirmed and cannot be modified. Request modification (as order creator) or ask a manager/GM to approve.');
            return;
        }
        $rules = [
            'payment_unified' => ['required', Rule::in(PaymentCatalog::unifiedPosValues())],
            'amount' => 'required|numeric|min:0.01',
            'tip_amount' => 'nullable|numeric|min:0',
            'tip_handling' => 'nullable|in:WAITER,HOTEL',
        ];
        if (PaymentCatalog::unifiedChoiceRequiresClientDetails($this->payment_unified)) {
            $rules['payment_client_reference'] = 'required|string|min:2|max:500';
        }
        $this->validate($rules, [], [
            'payment_unified' => 'Payment type',
            'payment_client_reference' => 'Client / account details',
            'amount' => 'Amount',
        ]);
        if ($this->assignment_type === 'room' && !$this->room_reservation_id) {
            session()->flash('error', 'Please select a guest room to assign this invoice to.');
            return;
        }
        if ($this->assignment_type === 'hotel_covered' && trim($this->hotel_covered_reason) === '') {
            session()->flash('error', 'Please enter a reason when setting invoice as covered by the hotel.');
            return;
        }

        $invoice = $invoice ?? Invoice::with('order')->find($this->invoiceId);
        if ($invoice->invoice_status === 'PAID') {
            session()->flash('error', 'Invoice is already paid.');
            return;
        }

        // Prevent confirming very old unpaid bills in the current shift without manager oversight.
        // When invoice/order date is before the current hotel business day and there is an outstanding
        // balance, waiters should request manager review instead of recording payment directly.
        $hotelDate = Hotel::getTodayForHotel();
        if ($hotelDate && $invoice->order && $invoice->balance > 0) {
            $orderDate = $invoice->order->created_at?->format('Y-m-d');
            if ($orderDate !== null && $orderDate < $hotelDate) {
                session()->flash('error', 'This order is from a previous day. Please ask a manager or accountant to review and confirm payment so that reports remain accurate.');
                return;
            }
        }

        $amount = (float) $this->amount;
        $balance = (float) $invoice->balance;
        $tipAmount = (float) ($this->tip_amount ?: 0);

        $payAmount = min($amount, $balance);
        if ($amount > $balance && $tipAmount <= 0) {
            $tipAmount = $amount - $balance;
        }
        $newBalance = $balance - $payAmount;

        $cashLater = $this->payment_unified === PaymentCatalog::METHOD_CASH && $this->cash_submit_later;
        $expanded = PaymentCatalog::expandUnifiedToStorage($this->payment_unified, $cashLater);
        $payMethod = PaymentCatalog::normalizePosMethod($expanded['payment_method']);
        $payStatus = PaymentCatalog::normalizeStatus($expanded['payment_status']);
        $clientRef = trim($this->payment_client_reference ?? '') !== ''
            ? trim((string) $this->payment_client_reference)
            : null;

        $submittedAt = null;
        if ($payMethod === PaymentCatalog::METHOD_CASH && ! $this->cash_submit_later) {
            $submittedAt = now();
        }

        $this->applyAssignment($invoice);

        if ($newBalance <= 0) {
            $hotel = Hotel::getHotel();
            // Always allow payment; when stock is insufficient we record pending deductions
            $insufficient = StockDeductionService::checkSufficientStock($invoice->order);
            try {
                $lastPayment = null;
                DB::transaction(function () use ($invoice, $payAmount, $tipAmount, $submittedAt, $hotel, $insufficient, $payMethod, $payStatus, $clientRef, &$lastPayment) {
                    $lastPayment = Payment::create([
                        'invoice_id' => $invoice->id,
                        'payment_method' => $payMethod,
                        'payment_status' => $payStatus,
                        'amount' => $payAmount,
                        'tip_amount' => $tipAmount,
                        'tip_handling' => $tipAmount > 0 ? $this->tip_handling : null,
                        'received_by' => Auth::id(),
                        'received_at' => now(),
                        'submitted_at' => $submittedAt,
                        'client_reference' => $clientRef,
                    ]);
                    $invoice->update(['invoice_status' => 'PAID']);
                    $invoice->clearModificationApproval();
                    $invoice->order->update(['order_status' => 'PAID']);
                    if ($invoice->order->table_id) {
                        RestaurantTable::where('id', $invoice->order->table_id)->update(['is_active' => true]);
                    }
                    if (empty($insufficient)) {
                        StockDeductionService::deductForOrder($invoice->order);
                    } else {
                        StockDeductionService::recordPendingDeductionsForOrder($invoice->order);
                    }
                });
                if ($lastPayment) {
                    ActivityLogger::log(
                        'pos.payment_recorded',
                        sprintf(
                            'POS payment %s %s on invoice %s (order #%s)',
                            $invoice->currency ?? (Hotel::getHotel()->currency ?? ''),
                            number_format($payAmount, 2, '.', ''),
                            $invoice->invoice_number ?? $invoice->id,
                            $invoice->order_id
                        ),
                        Payment::class,
                        $lastPayment->id,
                        null,
                        [
                            'amount' => $payAmount,
                            'payment_method' => $payMethod,
                            'invoice_id' => $invoice->id,
                        ],
                        ActivityLogModule::POS
                    );
                }
            } catch (\Throwable $e) {
                session()->flash('error', 'Payment failed: ' . $e->getMessage());
                $this->refreshInvoice();
                $this->fillAmountWithBalance();
                return;
            }
            $msg = empty($insufficient)
                ? 'Invoice paid. Stock deducted.'
                : 'Invoice paid. Some items were sold without stock; see Pending stock deductions for store keeper.';
            session()->flash('message', $msg);
            return $this->redirect(route('pos.receipt', ['order' => $invoice->order_id]), navigate: true);
        }

        $partialPayment = Payment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => $payMethod,
            'payment_status' => $payStatus,
            'amount' => $payAmount,
            'tip_amount' => $tipAmount,
            'tip_handling' => $tipAmount > 0 ? $this->tip_handling : null,
            'received_by' => Auth::id(),
            'received_at' => now(),
            'submitted_at' => $submittedAt,
            'client_reference' => $clientRef,
        ]);

        ActivityLogger::log(
            'pos.payment_recorded',
            sprintf(
                'POS partial payment %s %s on invoice %s (balance was %s)',
                $invoice->currency ?? (Hotel::getHotel()->currency ?? ''),
                number_format($payAmount, 2, '.', ''),
                $invoice->invoice_number ?? $invoice->id,
                number_format($balance, 2, '.', '')
            ),
            Payment::class,
            $partialPayment->id,
            null,
            [
                'amount' => $payAmount,
                'payment_method' => $payMethod,
                'invoice_id' => $invoice->id,
            ],
            ActivityLogModule::POS
        );

        $invoice->clearModificationApproval();
        $this->tip_amount = '0';
        $this->refreshInvoice();
        $this->fillAmountWithBalance();
        session()->flash('message', 'Payment recorded. Balance: ' . number_format($newBalance, 2));
    }

    public function markAsPayLater(): void
    {
        if (! $this->canReceivePayment) {
            session()->flash('error', 'You are not allowed to change invoice status. Ask the cashier or manager.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if ($invoice && $invoice->isModificationLocked() && !$invoice->canBeModifiedBy(Auth::user())) {
            session()->flash('error', 'This receipt is confirmed and cannot be modified. Request modification or ask a manager/GM to approve.');
            return;
        }
        if ($this->assignment_type === 'room' && !$this->room_reservation_id) {
            session()->flash('error', 'Please select a guest room to assign this invoice to.');
            return;
        }
        if ($this->assignment_type === 'hotel_covered' && trim($this->hotel_covered_reason) === '') {
            session()->flash('error', 'Please enter a reason when setting invoice as covered by the hotel.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if (!$invoice || $invoice->invoice_status === 'PAID') {
            session()->flash('error', 'Cannot set as pay later.');
            return;
        }
        $this->applyAssignment($invoice);
        $invoice->update(['invoice_status' => 'CREDIT']);
        $invoice->clearModificationApproval();
        if ($invoice->order->table_id) {
            RestaurantTable::where('id', $invoice->order->table_id)->update(['is_active' => true]);
        }
        ActivityLogger::log(
            'pos.invoice_pay_later',
            sprintf('Invoice %s set as pay later / credit (order #%s)', $invoice->invoice_number ?? $invoice->id, $invoice->order_id),
            Invoice::class,
            $invoice->id,
            null,
            ['invoice_status' => 'CREDIT'],
            ActivityLogModule::POS
        );
        session()->flash('message', 'Order set as Pay later / Credit. Table is active. You can receive payment later from Orders.');
        $this->redirect(route('pos.orders'), navigate: true);
    }

    /**
     * Assign invoice to guest room (no payment here; guest pays at checkout).
     */
    public function assignToRoom(): void
    {
        if (! $this->canReceivePayment) {
            session()->flash('error', 'You do not have permission to assign invoices.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if ($invoice && $invoice->isModificationLocked() && !$invoice->canBeModifiedBy(Auth::user())) {
            session()->flash('error', 'This receipt is confirmed and cannot be modified. Request modification or ask a manager/GM to approve.');
            return;
        }
        if (! $this->room_reservation_id) {
            session()->flash('error', 'Please select a guest room.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if (! $invoice || $invoice->invoice_status === 'PAID') {
            session()->flash('error', 'Invoice not found or already paid.');
            return;
        }
        $this->assignment_type = 'room';
        $this->applyAssignment($invoice);
        $invoice->update(['invoice_status' => 'CREDIT']);
        $invoice->clearModificationApproval();
        if ($invoice->order->table_id) {
            RestaurantTable::where('id', $invoice->order->table_id)->update(['is_active' => true]);
        }
        ActivityLogger::log(
            'pos.invoice_post_to_room',
            sprintf('Invoice %s posted to room reservation #%s (order #%s)', $invoice->invoice_number ?? $invoice->id, $this->room_reservation_id ?? '—', $invoice->order_id),
            Invoice::class,
            $invoice->id,
            null,
            ['charge_type' => Invoice::CHARGE_TYPE_ROOM, 'reservation_id' => $this->room_reservation_id],
            ActivityLogModule::POS
        );
        session()->flash('message', 'Invoice assigned to room. Guest will pay at checkout.');
        $this->redirect(route('pos.orders'), navigate: true);
    }

    /**
     * Mark invoice as covered by the hotel (no payment).
     */
    public function assignAsHotelCovered(): void
    {
        if (! $this->canReceivePayment) {
            session()->flash('error', 'You do not have permission to assign invoices.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if ($invoice && $invoice->isModificationLocked() && !$invoice->canBeModifiedBy(Auth::user())) {
            session()->flash('error', 'This receipt is confirmed and cannot be modified. Request modification or ask a manager/GM to approve.');
            return;
        }
        if (trim($this->hotel_covered_reason) === '') {
            session()->flash('error', 'Please enter a reason.');
            return;
        }
        $invoice = Invoice::with('order')->find($this->invoiceId);
        if (! $invoice || $invoice->invoice_status === 'PAID') {
            session()->flash('error', 'Invoice not found or already paid.');
            return;
        }
        $this->assignment_type = 'hotel_covered';
        $this->applyAssignment($invoice);
        $invoice->update(['invoice_status' => 'CREDIT']);
        if ($invoice->order->table_id) {
            RestaurantTable::where('id', $invoice->order->table_id)->update(['is_active' => true]);
        }
        ActivityLogger::log(
            'pos.invoice_hotel_covered',
            sprintf('Invoice %s marked hotel covered (order #%s)', $invoice->invoice_number ?? $invoice->id, $invoice->order_id),
            Invoice::class,
            $invoice->id,
            null,
            ['charge_type' => Invoice::CHARGE_TYPE_HOTEL_COVERED],
            ActivityLogModule::POS
        );
        session()->flash('message', 'Invoice set as covered by the hotel.');
        $this->redirect(route('pos.orders'), navigate: true);
    }

    public function requestReceiptModification(): void
    {
        if (!$this->invoice || !$this->canRequestModification) {
            session()->flash('error', 'You cannot request modification for this receipt.');
            return;
        }
        $reason = trim($this->modification_request_reason);
        if ($reason === '') {
            session()->flash('error', 'Please enter a reason for the modification request.');
            return;
        }
        if ($this->hasPendingModificationRequest) {
            session()->flash('error', 'You already have a pending modification request for this receipt.');
            return;
        }
        $req = ReceiptModificationRequest::create([
            'invoice_id' => $this->invoice->id,
            'requested_by_id' => Auth::id(),
            'reason' => $reason,
            'status' => ReceiptModificationRequest::STATUS_PENDING,
        ]);
        ActivityLogger::log(
            'pos.receipt_modification_requested',
            sprintf('Receipt modification requested for invoice %s', $this->invoice->invoice_number ?? $this->invoice->id),
            ReceiptModificationRequest::class,
            $req->id,
            null,
            ['invoice_id' => $this->invoice->id],
            ActivityLogModule::POS
        );
        $this->modification_request_reason = '';
        $this->refreshInvoice();
        session()->flash('message', 'Modification request submitted. A manager or General Manager must approve it before you can modify this receipt.');
    }

    public function render()
    {
        return view('livewire.pos.pos-payment', [
            'inHouseReservations' => $this->inHouseReservations,
            'canModifyReceipt' => $this->canModifyReceipt,
            'canRequestModification' => $this->canRequestModification,
            'hasPendingModificationRequest' => $this->hasPendingModificationRequest,
        ])->layout('livewire.layouts.app-layout');
    }
}
