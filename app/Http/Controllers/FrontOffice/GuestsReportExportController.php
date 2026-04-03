<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestsReportExportController extends Controller
{
    /**
     * Export guests report as CSV (opens in Excel).
     */
    public function __invoke(Request $request): StreamedResponse
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

        $filename = 'guests-report-' . ($from === $to ? $from : $from . '_to_' . $to) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($reservations, $prepared, $verified, $approved) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Guest Name', 'Phone / Email', 'ID/Passport Number', 'Country', 'Profession', 'Stay Purpose', 'Check-in Date', 'Number of Days']);
            foreach ($reservations as $r) {
                $nights = max(0, $r->check_in_date->diffInDays($r->check_out_date));
                fputcsv($out, [
                    $r->guest_name ?? '—',
                    $r->guestsReportPhoneEmailCsv(),
                    $r->guest_id_number ?? '—',
                    $r->guest_country ?? '—',
                    $r->guest_profession ?? '—',
                    $r->guest_stay_purpose ?? '—',
                    $r->check_in_date?->format('Y-m-d') ?? '—',
                    $nights,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Prepared by (name)', $prepared]);
            fputcsv($out, ['Verified by (name)', $verified]);
            fputcsv($out, ['Approved by (name)', $approved]);
            fclose($out);
        }, $filename, $headers);
    }
}
