<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\ReservationFolioService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReservationFolioPrintController extends Controller
{
    public function __invoke(string $reservation): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403);
        }

        $model = ReservationFolioService::findByRouteKeyForHotel($reservation, $hotel);
        if (! $model) {
            abort(404, 'Reservation not found.');
        }

        $folio = ReservationFolioService::build($model);
        $tz = $hotel->getTimezone();

        return view('front-office.reservation-folio-print', [
            'hotel' => $hotel,
            'reservation' => $model,
            'folio' => $folio,
            'printedAt' => Carbon::now($tz)->format('d/m/Y H:i'),
        ]);
    }
}
