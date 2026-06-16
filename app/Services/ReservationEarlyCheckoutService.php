<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Support\ActivityLogModule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReservationEarlyCheckoutService
{
    /**
     * Normalize departure calendar date vs check-in / booked checkout for preview & settlement.
     *
     * @return array{
     *   effective_departure: \Carbon\Carbon,
     *   departure_ymd: string,
     *   booked_departure_ymd: string,
     *   booked_nights: int,
     *   stayed_nights: int,
     *   ratio: float,
     *   originally_booked_total: float,
     *   adjusted_room_rate_amount: float,
     *   adjusted_meal_plan_supplement: float,
     *   adjusted_total_amount: float,
     *   accommodation_invoice: array<string, mixed>,
     *   is_proration: bool,
     * }
     */
    public static function preview(Reservation $reservation, ?string $candidateDepartureYmd): array
    {
        $reservation->loadMissing(['roomUnits']);

        $checkIn = $reservation->check_in_date->copy()->startOfDay();
        $bookedDepart = $reservation->check_out_date->copy()->startOfDay();
        $bookedNights = max(1, $checkIn->diffInDays($bookedDepart));
        $minDepart = $checkIn->copy()->addDay()->startOfDay();

        $raw = trim((string) ($candidateDepartureYmd ?? ''));
        if ($raw === '') {
            $effective = $bookedDepart->copy();
            $clampNote = '';
        } else {
            $clampNote = '';
            try {
                $effective = Carbon::parse($raw)->startOfDay();
            } catch (\Throwable) {
                $effective = $bookedDepart->copy();
                $clampNote = 'Invalid departure date — using booked checkout.';
            }
            if ($effective->lte($checkIn)) {
                $effective = $minDepart->copy();
                $clampNote = 'Adjusted to earliest valid departure ('.$effective->format('Y-m-d').'). '.trim($clampNote);
            }
            if ($effective->gt($bookedDepart)) {
                $effective = $bookedDepart->copy();
                $clampNote = trim($clampNote.' Departure capped to booked checkout ('.$effective->format('Y-m-d').').');
            }
        }

        $stayedNights = max(1, min($bookedNights, $checkIn->diffInDays($effective)));

        /** If UI produced an inconsistent combo, clamp to booked window */
        if ($stayedNights > $bookedNights) {
            $stayedNights = $bookedNights;
            $effective = $bookedDepart->copy();
        }

        $ratio = min(1.0, $stayedNights / max(1, $bookedNights));
        $isProration = $stayedNights < $bookedNights || round($ratio, 8) + 1e-12 < 1.0;

        $currency = (string) ($reservation->currency ?? 'RWF');

        $origTotal = round((float) ($reservation->total_amount ?? 0), 2);
        $origRoomRate = round((float) ($reservation->room_rate_amount ?? 0), 2);
        $origSupplement = round((float) ($reservation->meal_plan_supplement ?? 0), 2);

        $adjustedTotal = round($origTotal * $ratio, 2);
        $adjustedRoom = round($origRoomRate * $ratio, 2);
        $adjustedSupp = round($origSupplement * $ratio, 2);

        $rooms = max(1, $reservation->roomUnits->count());

        $accommodationInvoice = ReservationFolioService::buildAccommodationInvoiceTable(
            $reservation->mealPlanEnum(),
            max(0, $adjustedRoom),
            max(0, $adjustedSupp),
            $reservation->isRoomComplimentary(),
            $reservation->isMealComplimentary(),
            max(0, $adjustedTotal),
            $stayedNights,
            $rooms,
            $currency,
        );

        return [
            'effective_departure' => $effective->copy(),
            'departure_ymd' => $effective->format('Y-m-d'),
            'booked_departure_ymd' => $bookedDepart->format('Y-m-d'),
            'check_in_ymd' => $checkIn->format('Y-m-d'),
            'booked_nights' => $bookedNights,
            'stayed_nights' => $stayedNights,
            'ratio' => $ratio,
            'originally_booked_total' => $origTotal,
            'adjusted_room_rate_amount' => $adjustedRoom,
            'adjusted_meal_plan_supplement' => $adjustedSupp,
            'adjusted_total_amount' => $adjustedTotal,
            'accommodation_invoice' => $accommodationInvoice,
            'is_proration' => $isProration,
            'clamp_note' => trim((string) $clampNote),
            'hotel_today_ymd' => Hotel::getTodayForHotel(),
            'minimum_departure_ymd' => $checkIn->copy()->addDay()->format('Y-m-d'),
        ];
    }

    /**
     * Persist adjusted folio totals and shortened stay before marking the guest checked out.
     *
     * @throws \Throwable
     */
    public static function applyDepartureSettlement(Reservation $reservation, string $effectiveDepartureYmd, ?User $user): Reservation
    {
        if ($user && ! $user->hasPermission('fo_check_in_out')) {
            throw new RuntimeException('You do not have permission to finalize checkout settlements.');
        }

        $hotel = Hotel::getHotel();
        if ($hotel) {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        }

        return DB::transaction(function () use ($reservation, $effectiveDepartureYmd) {
            $reservation->refresh();
            $ctx = self::preview($reservation, $effectiveDepartureYmd);
            $depart = $ctx['departure_ymd'];

            $before = [
                'check_out_date' => $reservation->check_out_date->format('Y-m-d'),
                'total_amount' => (float) ($reservation->total_amount ?? 0),
                'room_rate_amount' => (float) ($reservation->room_rate_amount ?? 0),
                'meal_plan_supplement' => (float) ($reservation->meal_plan_supplement ?? 0),
                'paid_amount' => (float) ($reservation->paid_amount ?? 0),
            ];

            $reservation->check_out_date = $depart;

            if ($ctx['is_proration'] ?? false) {
                $reservation->room_rate_amount = $ctx['adjusted_room_rate_amount'];
                $reservation->meal_plan_supplement = $ctx['adjusted_meal_plan_supplement'];
                $reservation->total_amount = $ctx['adjusted_total_amount'];
            }

            $reservation->save();
            ReservationPayment::recomputeBalancesForReservation((int) $reservation->id);

            ActivityLogger::log(
                'reservation.checkout_settlement',
                sprintf(
                    'Checkout settlement — %s departed %s (nights billed %s of %s). Folio adjusted from %s to %s.',
                    $reservation->guest_name ?? 'Guest',
                    $depart,
                    (string) $ctx['stayed_nights'],
                    (string) $ctx['booked_nights'],
                    number_format($before['total_amount'], 2, '.', ''),
                    number_format((float) $reservation->total_amount, 2, '.', ''),
                ),
                Reservation::class,
                $reservation->id,
                $before,
                [
                    'check_out_date' => $depart,
                    'total_amount' => (float) ($reservation->total_amount ?? 0),
                    'room_rate_amount' => (float) ($reservation->room_rate_amount ?? 0),
                    'meal_plan_supplement' => (float) ($reservation->meal_plan_supplement ?? 0),
                ],
                ActivityLogModule::FRONT_OFFICE,
            );

            return $reservation->fresh(['roomUnits', 'invoices']);
        });
    }

    /** Default departure date (today vs scheduled checkout), clamped within valid departure window. */
    public static function defaultDepartureCandidate(Reservation $reservation): string
    {
        $hotelToday = Carbon::parse(Hotel::getTodayForHotel())->startOfDay();
        $booked = $reservation->check_out_date->copy()->startOfDay();
        $checkIn = $reservation->check_in_date->copy()->startOfDay();
        $minDepart = $checkIn->copy()->addDay()->startOfDay();

        $pick = $hotelToday->lte($booked) ? $hotelToday->copy() : $booked->copy();

        if ($pick->lt($minDepart)) {
            $pick = $minDepart->copy();
        }
        if ($pick->gt($booked)) {
            $pick = $booked->copy();
        }

        return $pick->format('Y-m-d');
    }
}