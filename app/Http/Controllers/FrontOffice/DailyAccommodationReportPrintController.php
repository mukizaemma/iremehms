<?php

namespace App\Http\Controllers\FrontOffice;

use App\Helpers\VatHelper;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Support\PaymentCatalog;
use App\Traits\ChecksModuleStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DailyAccommodationReportPrintController
{
    use ChecksModuleStatus;

    public function __invoke(Request $request): View
    {
        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        $user = Auth::user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $module = \App\Models\Module::where('slug', 'front-office')->first();
        if ($module && ! $user->hasModuleAccess($module->id)) {
            abort(403, 'You do not have access to Front Office.');
        }

        $canPickStaff = (bool) ($user->isSuperAdmin()
            || $user->isManager()
            || $user->isEffectiveGeneralManager()
            || $user->isReceptionist());

        $legacyDate = (string) ($request->input('date') ?: '');
        $dateFromIn = (string) ($request->input('date_from') ?: '');
        $dateToIn = (string) ($request->input('date_to') ?: '');

        if ($dateFromIn !== '' && $dateToIn !== '') {
            $from = Carbon::parse($dateFromIn)->startOfDay();
            $to = Carbon::parse($dateToIn)->startOfDay();
        } elseif ($legacyDate !== '') {
            $from = Carbon::parse($legacyDate)->startOfDay();
            $to = $from->copy();
        } else {
            $today = Hotel::getTodayForHotel();
            $from = Carbon::parse($today)->startOfDay();
            $to = $from->copy();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $defaultStaffScope = ($canPickStaff && $user->isReceptionist() && ! $user->isSuperAdmin() && ! $user->isManager() && ! $user->isEffectiveGeneralManager())
            ? 'all'
            : 'self';
        $staffScope = (string) ($request->input('staff_scope', $defaultStaffScope));
        $staffId = $request->input('staff_id');
        $staffId = $staffId !== null && $staffId !== '' ? (int) $staffId : null;

        if (! $canPickStaff) {
            $staffScope = 'self';
            $staffId = (int) $user->id;
        }

        if (! in_array($staffScope, ['self', 'all', 'user'], true)) {
            $staffScope = 'self';
        }

        if ($staffScope === 'user' && ($staffId === null || $staffId <= 0)) {
            $staffScope = 'self';
            $staffId = (int) $user->id;
        }

        $receiverPaymentScopeLabel = 'Self';
        $receiverPaymentUserIdFilter = (int) $user->id;
        if ($staffScope === 'all') {
            $receiverPaymentScopeLabel = 'All staff';
            $receiverPaymentUserIdFilter = 0;
        } elseif ($staffScope === 'user') {
            $receiverPaymentScopeLabel = 'Specific user';
            $receiverPaymentUserIdFilter = (int) $staffId;
        }

        $reportsShowVat = $hotel->showsVatOnReports();

        $paymentModeLabel = function (ReservationPayment $p): string {
            return PaymentCatalog::formatPaymentLineForReport($p->payment_method ?? $p->payment_type, $p->payment_status);
        };

        $rows = [];
        $totalGross = 0.0;
        $totalTax = 0.0;
        $totalPaidRow = 0.0;
        $totalCreditRow = 0.0;
        $balanceOncePerReservation = [];
        $includedReceivedByUserIds = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dateStr = $day->format('Y-m-d');

            $reservations = Reservation::with(['roomUnits.room'])
                ->where('hotel_id', $hotel->id)
                ->where('status', '!=', Reservation::STATUS_CANCELLED)
                ->where('status', '!=', Reservation::STATUS_NO_SHOW)
                ->where('check_in_date', '<=', $dateStr)
                ->where('check_out_date', '>', $dateStr)
                ->orderBy('guest_name')
                ->get();

            $reservationIds = $reservations->pluck('id')->all();
            $paymentsByReservation = [];

            if (! empty($reservationIds)) {
                $paymentsQuery = ReservationPayment::where('hotel_id', $hotel->id)
                    ->whereIn('reservation_id', $reservationIds)
                    ->where('status', 'confirmed')
                    ->where(function ($q) use ($dateStr) {
                        $q->whereDate('received_at', $dateStr)
                            ->orWhereDate('revenue_attribution_date', $dateStr);
                    })
                    ->orderBy('received_at');

                if ($receiverPaymentUserIdFilter > 0) {
                    $paymentsQuery->where('received_by', $receiverPaymentUserIdFilter);
                }

                foreach ($paymentsQuery->get() as $p) {
                    $rid = (int) $p->reservation_id;
                    $paymentsByReservation[$rid][] = $p;
                    $uid = (int) ($p->received_by ?? 0);
                    if ($uid > 0) {
                        $includedReceivedByUserIds[$uid] = true;
                    }
                }
            }

            foreach ($reservations as $r) {
                $roomUnits = $r->roomUnits ?? collect();
                $roomNumbers = $roomUnits->pluck('label')->filter()->unique()->values()->join(', ');
                $roomsCount = max(1, (int) $roomUnits->count());
                $nights = max(1, (int) $r->check_in_date->diffInDays($r->check_out_date));

                $dailyGross = (float) ($r->total_amount ?? 0) / $nights;
                $dailyTax = VatHelper::vatFromInclusive((float) $dailyGross);
                $dailyRoomRatePerUnit = $dailyGross / $roomsCount;

                $todayPayments = $paymentsByReservation[(int) $r->id] ?? [];
                $paidToday = 0.0;
                $creditToday = 0.0;
                $modeAmounts = [];

                foreach ($todayPayments as $p) {
                    $amount = (float) ($p->amount ?? 0);
                    $modeLabel = $paymentModeLabel($p);
                    $st = PaymentCatalog::normalizeStatus($p->payment_status ?? PaymentCatalog::STATUS_PAID);

                    if (! isset($modeAmounts[$modeLabel])) {
                        $modeAmounts[$modeLabel] = 0.0;
                    }
                    $modeAmounts[$modeLabel] += $amount;

                    if ($st === PaymentCatalog::STATUS_DEBITS || $st === PaymentCatalog::STATUS_OFFER) {
                        $creditToday += $amount;
                    } else {
                        $paidToday += $amount;
                    }
                }

                $balanceDue = max(0.0, (float) ($r->total_amount ?? 0) - (float) ($r->paid_amount ?? 0));
                $balanceOncePerReservation[(int) $r->id] = $balanceDue;

                $modeString = '';
                if (! empty($modeAmounts)) {
                    $parts = [];
                    foreach ($modeAmounts as $mode => $amt) {
                        $parts[] = $mode . ' ' . number_format((float) $amt, 2, '.', '');
                    }
                    $modeString = implode(', ', $parts);
                }

                $rows[] = [
                    'date' => $dateStr,
                    'guest_name' => $r->guest_name ?? '—',
                    'guest_address' => $r->guest_address ?? '—',
                    'guest_id_number' => $r->guest_id_number ?? '—',
                    'guest_phone' => $r->guest_phone ?? '—',
                    'room_number' => $roomNumbers ?: '—',
                    'nights' => $nights,
                    'room_rate' => number_format((float) $dailyRoomRatePerUnit, 2, '.', ''),
                    'currency' => (string) ($hotel->currency ?? 'RWF'),
                    'payment_mode' => $modeString ?: '—',
                    'paid_today' => number_format((float) $paidToday, 2, '.', ''),
                    'credit_today' => number_format((float) $creditToday, 2, '.', ''),
                    'balance_due' => number_format((float) $balanceDue, 2, '.', ''),
                    'tax_for_row' => number_format((float) $dailyTax, 2, '.', ''),
                ];

                $totalGross += (float) $dailyGross;
                $totalTax += (float) $dailyTax;
                $totalPaidRow += (float) $paidToday;
                $totalCreditRow += (float) $creditToday;
            }
        }

        $totalBalanceDue = (float) array_sum($balanceOncePerReservation);

        $buckets = array_fill_keys(PaymentCatalog::accommodationReportBucketKeys(), 0.0);

        $payQ = ReservationPayment::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereRaw(
                'COALESCE(DATE(revenue_attribution_date), DATE(received_at)) BETWEEN ? AND ?',
                [$from->format('Y-m-d'), $to->format('Y-m-d')]
            );

        if ($receiverPaymentUserIdFilter > 0) {
            $payQ->where('received_by', $receiverPaymentUserIdFilter);
        }

        foreach ($payQ->get() as $p) {
            $key = PaymentCatalog::accommodationPaymentReportBucket($p->payment_method ?? '', $p->payment_status ?? '');
            $amt = (float) ($p->amount ?? 0);
            if (! array_key_exists($key, $buckets)) {
                $buckets[$key] = 0.0;
            }
            $buckets[$key] += $amt;
            $uid = (int) ($p->received_by ?? 0);
            if ($uid > 0) {
                $includedReceivedByUserIds[$uid] = true;
            }
        }

        $includedUsers = [];
        if (! empty($includedReceivedByUserIds)) {
            $includedUsers = User::query()
                ->whereIn('id', array_keys($includedReceivedByUserIds))
                ->pluck('name', 'id')
                ->toArray();
        }

        $signatureDate = Carbon::now()->format('Y-m-d');
        $retrievedAt = Carbon::now()->format('Y-m-d H:i');

        $includedUsersText = '';
        if ($staffScope === 'all') {
            $includedUsersText = implode(', ', array_values(array_slice($includedUsers, 0, 20, true)));
        } elseif ($staffScope === 'user' && $staffId) {
            $includedUsersText = $includedUsers[$staffId] ?? '';
        } else {
            $includedUsersText = $user->name;
        }

        $reportRangeLabel = $from->equalTo($to)
            ? $from->format('Y-m-d')
            : ($from->format('Y-m-d') . ' — ' . $to->format('Y-m-d'));

        $totalPaidPeriod = (float) array_sum($buckets);

        return view('front-office.daily-accommodation-report-print', [
            'hotel' => $hotel,
            'rows' => $rows,
            'date' => $reportRangeLabel,
            'dateFrom' => $from->format('Y-m-d'),
            'dateTo' => $to->format('Y-m-d'),
            'generatedBy' => $user,
            'retrievedAt' => $retrievedAt,
            'receiverPaymentScopeLabel' => $receiverPaymentScopeLabel,
            'includedUsersText' => $includedUsersText,
            'currency' => (string) ($hotel->currency ?? 'RWF'),
            'totalGross' => $totalGross,
            'totalTax' => $totalTax,
            'totalPaidToday' => $totalPaidRow,
            'totalCreditToday' => $totalCreditRow,
            'totalPaidPeriod' => $totalPaidPeriod,
            'totalBalanceDue' => $totalBalanceDue,
            'signatureDate' => $signatureDate,
            'reportsShowVat' => $reportsShowVat,
            'paymentsByType' => $buckets,
            'paymentTypeLabels' => PaymentCatalog::accommodationReportBucketLabels(),
        ]);
    }
}
