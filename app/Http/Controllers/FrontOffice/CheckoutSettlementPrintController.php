<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Services\ReservationEarlyCheckoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Printable accommodation checkout settlement (supports early departure preview).
 */
class CheckoutSettlementPrintController extends Controller
{
    public function __invoke(Request $request, Reservation $reservation): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hotel = Hotel::getHotel();
        if (! $hotel || (int) $reservation->hotel_id !== (int) $hotel->id) {
            abort(404);
        }

        $departure = (string) $request->query('departure', '');
        $reservation->loadMissing(['roomUnits']);

        $ctx = ReservationEarlyCheckoutService::preview($reservation, $departure !== '' ? $departure : null);
        $paid = round((float) ($reservation->paid_amount ?? 0), 2);
        $adj = round((float) ($ctx['adjusted_total_amount'] ?? 0), 2);
        $balance = round($adj - $paid, 2);
        $settled = $balance <= 0.02;

        $tz = $hotel->getTimezone();

        return view('front-office.checkout-settlement-print', [
            'hotel' => $hotel,
            'reservation' => $reservation,
            'ctx' => $ctx,
            'paid' => $paid,
            'balance' => max(0, $balance),
            'balance_signed' => $balance,
            'settled_label' => $settled ? 'FULLY SETTLED' : 'OUTSTANDING BALANCE',
            'printedAt' => Carbon::now($tz)->format('d/m/Y H:i'),
        ]);
    }
}
