<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationPaymentReceiptController extends Controller
{
    public function print(ReservationPayment $payment): View
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }
        if ((int) $payment->hotel_id !== (int) $hotel->id) {
            abort(403, 'Invalid hotel.');
        }

        $payment->load(['reservation', 'receivedBy']);
        $reservation = $payment->reservation;
        if (! $reservation) {
            abort(404);
        }

        $balanceAfter = (float) ($payment->balance_after ?? 0);
        $paidInFull = $balanceAfter <= 0;

        return view('front-office.reservation-payment-receipt', [
            'hotel' => $hotel,
            'reservation' => $reservation,
            'payment' => $payment,
            'receiptStatusLabel' => $payment->status === 'voided' ? 'VOIDED' : ($paidInFull ? 'FULL PAID' : 'PARTIALLY PAID'),
            'printedAt' => now(),
            'preview' => false,
        ]);
    }

    public function preview(Request $request): View
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        $reservationId = (int) $request->query('reservation_id');
        $amount = (float) $request->query('payment_amount', 0);
        if ($reservationId <= 0) {
            abort(400, 'Reservation id required.');
        }

        $reservation = Reservation::where('hotel_id', $hotel->id)->find($reservationId);
        if (! $reservation) {
            abort(404);
        }

        $balanceNow = (float) ($reservation->total_amount ?? 0) - (float) ($reservation->paid_amount ?? 0);
        $balanceNow = max(0, $balanceNow);

        return view('front-office.reservation-payment-receipt', [
            'hotel' => $hotel,
            'reservation' => $reservation,
            'payment' => null,
            'receiptStatusLabel' => 'UNPAID (Preview)',
            'printedAt' => now(),
            'preview' => true,
            'previewAmount' => $amount,
            'previewBalanceDue' => $balanceNow,
        ]);
    }
}

