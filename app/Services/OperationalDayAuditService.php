<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Models\BusinessDay;
use App\Models\GuestCommunication;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\StaffMessage;
use App\Support\PaymentCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationalDayAuditService
{
    /**
     * @return array{
     *   audit_date: string,
     *   audit_date_label: string,
     *   currency: string,
     *   business_day_open: bool,
     *   business_day_closed: bool,
     *   summary: array{blockers: int, warnings: int, ok: int},
     *   sections: list<array<string, mixed>>,
     *   payments_today: array{cash: float, total: float, by_method: array<string, float>, advance_deposits: float},
     * }
     */
    public static function build(Hotel $hotel, ?string $auditDateYmd = null): array
    {
        $auditDate = $auditDateYmd
            ? Carbon::parse($auditDateYmd)->format('Y-m-d')
            : Hotel::getTodayForHotel();

        $currency = $hotel->currency ?? 'RWF';
        $sections = [];
        $blockers = 0;
        $warnings = 0;
        $ok = 0;

        $tally = function (string $severity) use (&$blockers, &$warnings, &$ok): void {
            match ($severity) {
                'blocker' => $blockers++,
                'warning' => $warnings++,
                default => $ok++,
            };
        };

        // --- Guests & rooms ---
        $guestItems = [];

        $departuresDue = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', $auditDate)
            ->count();
        $guestItems[] = self::item(
            $departuresDue > 0 ? 'warning' : 'ok',
            'Departures due (still in-house)',
            $departuresDue,
            route('front-office.reservations', ['tab' => 'departures']),
            $departuresDue > 0 ? 'Guests scheduled to leave today who are not checked out yet.' : 'No pending departures for this date.',
        );
        $tally($departuresDue > 0 ? 'warning' : 'ok');

        $checkedOutBalance = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CHECKED_OUT)
            ->whereDate('check_out_date', $auditDate)
            ->whereRaw('COALESCE(total_amount, 0) - COALESCE(paid_amount, 0) > 0.02')
            ->count();
        $guestItems[] = self::item(
            $checkedOutBalance > 0 ? 'blocker' : 'ok',
            'Checked out with folio balance due',
            $checkedOutBalance,
            route('front-office.reservations', ['tab' => 'checked_out_today']),
            'Checked out on this date but accommodation balance is not fully paid.',
        );
        $tally($checkedOutBalance > 0 ? 'blocker' : 'ok');

        $checkedOutUnsettledPos = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CHECKED_OUT)
            ->whereDate('check_out_date', $auditDate)
            ->whereHas('invoices', function ($q) {
                $q->whereNotIn('invoice_status', ['PAID', 'CREDIT']);
            })
            ->count();
        $guestItems[] = self::item(
            $checkedOutUnsettledPos > 0 ? 'blocker' : 'ok',
            'Checked out with unsettled POS / extras',
            $checkedOutUnsettledPos,
            route('recovery.dashboard'),
            'Restaurant or extra charges still open on a guest who already left.',
        );
        $tally($checkedOutUnsettledPos > 0 ? 'blocker' : 'ok');

        $overstay = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_out_date', '<', $auditDate)
            ->count();
        $guestItems[] = self::item(
            $overstay > 0 ? 'warning' : 'ok',
            'In-house overstay (past checkout date)',
            $overstay,
            route('front-office.reservations', ['tab' => 'in_house']),
            'Guest still marked in-house after scheduled checkout date.',
        );
        $tally($overstay > 0 ? 'warning' : 'ok');

        $arrivalsNotIn = Reservation::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->whereDate('check_in_date', $auditDate)
            ->count();
        $guestItems[] = self::item(
            $arrivalsNotIn > 0 ? 'warning' : 'ok',
            'Expected arrivals not checked in',
            $arrivalsNotIn,
            route('front-office.reservations', ['tab' => 'arrivals']),
            'Confirmed reservations with check-in today.',
        );
        $tally($arrivalsNotIn > 0 ? 'warning' : 'ok');

        $sections[] = ['id' => 'guests', 'title' => 'Guests & rooms', 'items' => $guestItems];

        // --- Payments ---
        $paymentItems = [];
        $paymentsByMethod = self::paymentsReceivedOnDateByMethod($hotel->id, $auditDate);
        $cashTotal = (float) ($paymentsByMethod[PaymentCatalog::METHOD_CASH] ?? 0);
        $paymentTotal = array_sum($paymentsByMethod);

        $pendingPayments = ReservationPayment::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereDate('received_at', $auditDate)
            ->whereIn('payment_status', [PaymentCatalog::STATUS_PENDING, PaymentCatalog::STATUS_DEBITS])
            ->count();
        $paymentItems[] = self::item(
            $pendingPayments > 0 ? 'warning' : 'ok',
            'Payments marked Pending / Debit today',
            $pendingPayments,
            route('front-office.daily-accommodation-report', ['date_from' => $auditDate, 'date_to' => $auditDate]),
            'Cash received but settlement status is not fully paid.',
        );
        $tally($pendingPayments > 0 ? 'warning' : 'ok');

        $advanceToday = (float) ReservationPayment::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', 'confirmed')
            ->whereDate('received_at', $auditDate)
            ->where('payment_purpose', PaymentPurpose::AdvanceDeposit->value)
            ->sum('amount');

        $paymentItems[] = self::item(
            'ok',
            'Advance deposits collected (cash today)',
            $advanceToday > 0 ? 1 : 0,
            route('front-office.operational-day-audit', ['date' => $auditDate]),
            $advanceToday > 0
                ? sprintf('%s %s collected — attributed to check-in dates on sales report.', $currency, number_format($advanceToday, 2))
                : 'None recorded today.',
        );
        $tally('ok');

        $sections[] = ['id' => 'payments', 'title' => 'Payments & cash', 'items' => $paymentItems];

        // --- Recovery / POS ---
        $recoveryItems = [];
        $unpaidPos = Invoice::query()
            ->where('invoice_status', 'UNPAID')
            ->where(function ($q) use ($hotel) {
                $q->whereHas('reservation', fn ($r) => $r->where('hotel_id', $hotel->id))
                    ->orWhereHas('room', fn ($rm) => $rm->where('hotel_id', $hotel->id));
            })
            ->count();

        $recoveryItems[] = self::item(
            $unpaidPos > 0 ? 'warning' : 'ok',
            'Open unpaid POS invoices (hotel)',
            $unpaidPos,
            route('recovery.dashboard'),
            'Follow up in Recovery before closing the day if policy requires zero open tickets.',
        );
        $tally($unpaidPos > 0 ? 'warning' : 'ok');

        $sections[] = ['id' => 'recovery', 'title' => 'POS & recovery', 'items' => $recoveryItems];

        // --- Communications ---
        $commItems = [];
        $failedComms = GuestCommunication::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', GuestCommunication::STATUS_FAILED)
            ->whereDate('created_at', $auditDate)
            ->count();
        $commItems[] = self::item(
            $failedComms > 0 ? 'warning' : 'ok',
            'Failed guest messages today',
            $failedComms,
            route('front-office.communications', ['tab' => 'guests']),
            'Review and resend or note in log.',
        );
        $tally($failedComms > 0 ? 'warning' : 'ok');

        if (Schema::hasTable('staff_messages')) {
            $unreadStaff = StaffMessage::query()
                ->where('hotel_id', $hotel->id)
                ->whereNull('read_at')
                ->count();
            $commItems[] = self::item(
                $unreadStaff > 0 ? 'warning' : 'ok',
                'Unread staff messages',
                $unreadStaff,
                route('front-office.communications', ['tab' => 'staff']),
                'Internal messages awaiting read.',
            );
            $tally($unreadStaff > 0 ? 'warning' : 'ok');
        }

        $sections[] = ['id' => 'communications', 'title' => 'Communications', 'items' => $commItems];

        // --- Shifts ---
        $shiftItems = [];
        $foShiftOpen = OperationalShiftService::isEnabled()
            && OperationalShiftService::getOpenShiftForFrontOffice($hotel) !== null;
        $shiftItems[] = self::item(
            $foShiftOpen ? 'warning' : 'ok',
            'Front office operational shift',
            $foShiftOpen ? 1 : 0,
            route('shift.management'),
            $foShiftOpen ? 'Shift is still open — close after audit if policy requires.' : 'No open FO shift (or shifts disabled).',
        );
        $tally($foShiftOpen ? 'warning' : 'ok');

        $bd = BusinessDay::query()
            ->where('hotel_id', $hotel->id)
            ->whereDate('business_date', $auditDate)
            ->first();
        $bdOpen = $bd && $bd->status === 'OPEN';
        $bdClosed = $bd && $bd->status === 'CLOSED';
        $shiftItems[] = self::item(
            $bdClosed ? 'ok' : ($bdOpen ? 'warning' : 'warning'),
            'Business calendar day',
            1,
            route('shift.management'),
            $bdClosed ? 'Day is closed.' : ($bdOpen ? 'Day still open — run close after audit.' : 'No business day record for this date.'),
        );
        $tally($bdClosed ? 'ok' : 'warning');

        $sections[] = ['id' => 'shifts', 'title' => 'Shifts & business day', 'items' => $shiftItems];

        return [
            'audit_date' => $auditDate,
            'audit_date_label' => Carbon::parse($auditDate)->format('d M Y'),
            'currency' => $currency,
            'business_day_open' => (bool) $bdOpen,
            'business_day_closed' => (bool) $bdClosed,
            'summary' => [
                'blockers' => $blockers,
                'warnings' => $warnings,
                'ok' => $ok,
            ],
            'sections' => $sections,
            'payments_today' => [
                'cash' => $cashTotal,
                'total' => $paymentTotal,
                'by_method' => $paymentsByMethod,
                'advance_deposits' => $advanceToday,
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function paymentsReceivedOnDateByMethod(int $hotelId, string $dateYmd): array
    {
        $buckets = array_fill_keys(PaymentCatalog::accommodationReportBucketKeys(), 0.0);

        $payments = ReservationPayment::query()
            ->where('hotel_id', $hotelId)
            ->where('status', 'confirmed')
            ->whereDate('received_at', $dateYmd)
            ->get();

        foreach ($payments as $p) {
            $key = PaymentCatalog::accommodationPaymentReportBucket($p->payment_method ?? '', $p->payment_status ?? '');
            if (! array_key_exists($key, $buckets)) {
                $buckets[$key] = 0.0;
            }
            $buckets[$key] += (float) ($p->amount ?? 0);
        }

        return $buckets;
    }

    public static function canCloseBusinessDay(array $audit): bool
    {
        return ($audit['summary']['blockers'] ?? 0) === 0;
    }

    /**
     * @return array{severity: string, label: string, count: int, href: string, detail: string}
     */
    protected static function item(string $severity, string $label, int $count, string $href, string $detail): array
    {
        return [
            'severity' => $severity,
            'label' => $label,
            'count' => $count,
            'href' => $href,
            'detail' => $detail,
        ];
    }
}
