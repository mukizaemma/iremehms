<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GuestsReportPrintController extends Controller
{
    /**
     * Print view: A4 page with hotel name/contacts in header and guests table.
     */
    public function __invoke(Request $request): View
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        $from = $request->input('date_from', Carbon::now($hotel->getTimezone())->format('Y-m-d'));
        $to = $request->input('date_to', $from);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $reservations = Reservation::where('hotel_id', $hotel->id)
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->where('check_in_date', '<=', $to)
            ->where('check_out_date', '>=', $from)
            ->orderBy('check_in_date')
            ->get();

        $guests = $reservations->map(function (Reservation $r) {
            $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
            return [
                'guest_name' => $r->guest_name ?? '—',
                'phone_email' => $r->guestsReportPhoneEmailDisplay(),
                'guest_id_number' => $r->guest_id_number ?? '—',
                'guest_country' => $r->guest_country ?? '—',
                'guest_profession' => $r->guest_profession ?? '—',
                'guest_stay_purpose' => $r->guest_stay_purpose ?? '—',
                'check_in_date' => $r->check_in_date?->format('Y-m-d') ?? '—',
                'nights' => $nights,
            ];
        });

        $sanitize = static function (?string $v): string {
            $v = $v ?? '';

            return mb_substr(trim(strip_tags($v)), 0, 255);
        };

        $prepared = $sanitize($request->input('prepared_by'));
        $verified = $sanitize($request->input('verified_by'));
        $approved = $sanitize($request->input('approved_by'));

        if (Schema::hasColumn('hotels', 'guests_report_signature_prepared_default')) {
            if ($prepared === '') {
                $prepared = (string) ($hotel->guests_report_signature_prepared_default ?? Auth::user()?->name ?? '');
            }
            if ($verified === '') {
                $verified = (string) ($hotel->guests_report_signature_verified_default ?? '');
            }
            if ($approved === '') {
                $approved = (string) ($hotel->guests_report_signature_approved_default ?? '');
            }
        } elseif ($prepared === '') {
            $prepared = (string) (Auth::user()?->name ?? '');
        }

        return view('front-office.guests-report-print', [
            'hotel' => $hotel,
            'guests' => $guests,
            'date_from' => $from,
            'date_to' => $to,
            'prepared_by_name' => $prepared,
            'verified_by_name' => $verified,
            'approved_by_name' => $approved,
        ]);
    }
}
