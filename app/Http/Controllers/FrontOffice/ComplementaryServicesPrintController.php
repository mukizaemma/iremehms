<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\ComplementaryReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ComplementaryServicesPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403);
        }

        $from = $request->input('date_from', Hotel::getTodayForHotel());
        $to = $request->input('date_to', $from);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $rows = ComplementaryReportService::buildRows($hotel->id, $from, $to);
        $summary = ComplementaryReportService::summarize($rows);

        return view('front-office.complementary-services-print', [
            'hotel' => $hotel,
            'currency' => $hotel->currency ?? 'RWF',
            'rows' => $rows,
            'summary' => $summary,
            'dateFrom' => Carbon::parse($from)->format('d M Y'),
            'dateTo' => Carbon::parse($to)->format('d M Y'),
            'printedAt' => Carbon::now($hotel->getTimezone())->format('d/m/Y H:i'),
        ]);
    }
}
