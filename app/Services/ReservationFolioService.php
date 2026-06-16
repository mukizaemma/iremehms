<?php

namespace App\Services;

use App\Enums\MealPlan;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Support\PaymentCatalog;
use Carbon\Carbon;

class ReservationFolioService
{
    /** @var list<string> */
    public const FOLIO_RELATIONS = [
        'roomType',
        'roomUnits.room',
        'guests',
        'invoices.order.table',
        'reservationPayments.receivedBy',
    ];

    public static function findByRouteKeyForHotel(string $routeKey, Hotel $hotel): ?Reservation
    {
        $keyStr = trim($routeKey);
        if ($keyStr === '') {
            return null;
        }

        $q = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->with(self::FOLIO_RELATIONS);

        if (ctype_digit($keyStr)) {
            return $q->where('id', (int) $keyStr)->first();
        }

        return $q->where('reservation_number', $keyStr)->first();
    }

    /**
     * @return array{
     *   nights: int,
     *   currency: string,
     *   stay_lines: list<array{description: string, detail: ?string, amount: float}>,
     *   accommodation_invoice: array<string, mixed>,
     *   folio: array{total: float, paid: float, balance: float},
     *   invoices: list<array<string, mixed>>,
     *   payments: list<array<string, mixed>>,
     * }
     */
    public static function build(Reservation $reservation): array
    {
        $reservation->loadMissing(self::FOLIO_RELATIONS);

        $total = (float) ($reservation->total_amount ?? 0);
        $paid = (float) ($reservation->paid_amount ?? 0);
        $balance = max(0, $total - $paid);
        $currency = (string) ($reservation->currency ?? 'RWF');

        $checkIn = $reservation->check_in_date;
        $checkOut = $reservation->check_out_date;
        $nights = max(1, $checkIn->diffInDays($checkOut));

        $planLabel = $reservation->mealPlanEnum()->label();
        $compLabel = $reservation->complimentaryServicesLabel();
        $roomLabels = $reservation->roomUnits->pluck('label')->filter()->join(', ') ?: null;

        $stayLines = [
            [
                'description' => sprintf('Accommodation & board — %s (%d night%s)', $planLabel, $nights, $nights === 1 ? '' : 's'),
                'detail' => $compLabel !== '—' ? $compLabel : ($roomLabels ? 'Room '.$roomLabels : null),
                'amount' => $total,
            ],
        ];

        $accommodationInvoice = self::buildAccommodationInvoiceTableFromReservation($reservation);

        $invoices = [];
        foreach ($reservation->invoices as $inv) {
            $invoices[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number ?? '—',
                'total_amount' => (float) $inv->total_amount,
                'status' => $inv->invoice_status,
                'charge_type' => $inv->charge_type,
                'charge_type_label' => self::chargeTypeLabel($inv->charge_type),
                'payment_label' => self::invoicePaymentLabel($inv),
                'is_settled' => $inv->invoice_status === 'PAID' || $inv->invoice_status === 'CREDIT',
                'context' => self::invoiceContextLine($inv),
            ];
        }

        $payments = [];
        $hotelPayments = $reservation->reservationPayments
            ->where('status', 'confirmed')
            ->sortBy('received_at');

        foreach ($hotelPayments as $p) {
            $payments[] = [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number ?? '—',
                'amount' => (float) ($p->amount ?? 0),
                'currency' => (string) ($p->currency ?? $currency),
                'payment_method' => (string) ($p->payment_method ?? ''),
                'payment_type' => (string) ($p->payment_type ?? ''),
                'payment_status' => (string) ($p->payment_status ?? PaymentCatalog::STATUS_PAID),
                'received_by' => $p->receivedBy?->name ?? '—',
                'received_at' => $p->received_at ? Carbon::parse($p->received_at)->format('d/m/Y H:i') : '',
                'total_paid_after' => (float) ($p->total_paid_after ?? 0),
                'balance_after' => (float) ($p->balance_after ?? 0),
                'receipt_url' => route('front-office.reservation-payment-receipt', ['payment' => $p->id]),
            ];
        }

        return [
            'nights' => $nights,
            'currency' => $currency,
            'stay_lines' => $stayLines,
            'accommodation_invoice' => $accommodationInvoice,
            'folio' => [
                'total' => $total,
                'paid' => $paid,
                'balance' => $balance,
            ],
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * Itemized accommodation lines (Item, Qty, Unit price, Amount) aligned with POS/email receipt columns.
     *
     * @return array{
     *   lines: list<array{item: string, qty: int, unit_price: float, amount: float}>,
     *   total_amount: float,
     *   currency: string,
     *   nights: int,
     *   rooms: int,
     *   room_nights: int
     * }
     */
    public static function buildAccommodationInvoiceTableFromReservation(Reservation $reservation): array
    {
        $reservation->loadMissing(['roomUnits']);

        $nights = max(1, $reservation->check_in_date->diffInDays($reservation->check_out_date));
        $rooms = max(1, $reservation->roomUnits->count());

        return self::buildAccommodationInvoiceTable(
            $reservation->mealPlanEnum(),
            (float) ($reservation->room_rate_amount ?? 0),
            (float) ($reservation->meal_plan_supplement ?? 0),
            $reservation->isRoomComplimentary(),
            $reservation->isMealComplimentary(),
            (float) ($reservation->total_amount ?? 0),
            $nights,
            $rooms,
            (string) ($reservation->currency ?? 'RWF'),
        );
    }

    /**
     * @see buildAccommodationInvoiceTableFromReservation()
     */
    public static function buildAccommodationInvoiceTable(
        MealPlan $plan,
        float $roomRateReference,
        float $mealSupplementReference,
        bool $roomComplimentary,
        bool $mealComplimentary,
        float $packageTotal,
        int $nights,
        int $rooms,
        string $currency,
    ): array {
        $nights = max(1, $nights);
        $rooms = max(1, $rooms);
        $roomNights = $nights * $rooms;

        [$chargeRoom, $chargeMeal, $computed] = ReservationManageService::computeCharges(
            $plan,
            max(0, $roomRateReference),
            max(0, $mealSupplementReference),
            $roomComplimentary,
            $mealComplimentary,
        );

        $lines = [];

        if ($roomComplimentary) {
            $lines[] = [
                'item' => 'Room ('.$plan->shortLabel().' base) — complimentary',
                'qty' => $roomNights,
                'unit_price' => 0.0,
                'amount' => 0.0,
            ];
        } elseif ($chargeRoom > 0) {
            $lines[] = [
                'item' => 'Room ('.$plan->shortLabel().' base reference)',
                'qty' => $roomNights,
                'unit_price' => round($chargeRoom / $roomNights, 4),
                'amount' => round($chargeRoom, 2),
            ];
        }

        if ($plan->allowsMealSupplement()) {
            if ($mealComplimentary) {
                $lines[] = [
                    'item' => 'Board supplement ('.$plan->shortLabel().') — complimentary',
                    'qty' => $roomNights,
                    'unit_price' => 0.0,
                    'amount' => 0.0,
                ];
            } elseif ($chargeMeal > 0) {
                $lines[] = [
                    'item' => 'Board supplement ('.$plan->shortLabel().')',
                    'qty' => $roomNights,
                    'unit_price' => round($chargeMeal / $roomNights, 4),
                    'amount' => round($chargeMeal, 2),
                ];
            }
        }

        $sumComponents = round(array_sum(array_column($lines, 'amount')), 2);
        $adjustment = round(max(0, $packageTotal) - $sumComponents, 2);
        if (abs($adjustment) >= 0.01) {
            $lines[] = [
                'item' => 'Package / manual adjustment (to match total to pay)',
                'qty' => 1,
                'unit_price' => $adjustment,
                'amount' => $adjustment,
            ];
        }

        $finalSum = round(array_sum(array_column($lines, 'amount')), 2);
        $target = round(max(0, $packageTotal), 2);
        if ($lines === [] || abs($finalSum - $target) > 0.02) {
            $lines = [
                [
                    'item' => 'Accommodation & board ('.$plan->label().') — as booked',
                    'qty' => 1,
                    'unit_price' => $target,
                    'amount' => $target,
                ],
            ];
            $finalSum = $target;
        }

        return [
            'lines' => $lines,
            'total_amount' => $target,
            'currency' => $currency,
            'nights' => $nights,
            'rooms' => $rooms,
            'room_nights' => $roomNights,
            'computed_reference_subtotal' => round($computed, 2),
        ];
    }

    public static function invoicePaymentLabel(Invoice $inv): string
    {
        if ($inv->invoice_status === 'PAID') {
            return 'Paid';
        }
        if ($inv->charge_type === Invoice::CHARGE_TYPE_ROOM) {
            return 'Assigned to room';
        }
        if ($inv->charge_type === Invoice::CHARGE_TYPE_HOTEL_COVERED) {
            return 'Hotel covered';
        }
        if ($inv->invoice_status === 'CREDIT') {
            return 'Credit';
        }

        return 'Outstanding';
    }

    protected static function chargeTypeLabel(?string $chargeType): string
    {
        return match ($chargeType) {
            Invoice::CHARGE_TYPE_POS => 'Restaurant / POS',
            Invoice::CHARGE_TYPE_ROOM => 'Posted to room',
            Invoice::CHARGE_TYPE_HOTEL_COVERED => 'Hotel covered',
            default => $chargeType ? ucfirst(strtolower((string) $chargeType)) : 'Charge',
        };
    }

    protected static function invoiceContextLine(Invoice $inv): ?string
    {
        $order = $inv->order;
        if (! $order) {
            return null;
        }
        $table = $order->table;
        if ($table && ($table->table_number ?? '') !== '') {
            return 'Table '.$table->table_number;
        }

        return 'Order #'.$order->id;
    }
}
