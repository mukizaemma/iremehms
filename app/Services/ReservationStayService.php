<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ActivityLogModule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ReservationStayService
{
    /** Guests still checked in after checkout date beyond this window are hidden from in-house (stale records). */
    public const IN_HOUSE_OVERSTAY_DAYS = 30;

    public static function userCanManageStay(?User $user): bool
    {
        return $user && ($user->hasPermission('fo_check_in_out') || $user->canNavigateModules() || $user->isSuperAdmin());
    }

    public static function inHouseQuery(Builder $query, string $today): Builder
    {
        $overstayCutoff = Carbon::parse($today)->subDays(self::IN_HOUSE_OVERSTAY_DAYS)->format('Y-m-d');

        return $query
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_in_date', '<=', $today)
            ->where(function ($q) use ($today, $overstayCutoff) {
                $q->whereDate('check_out_date', '>', $today)
                    ->orWhere(function ($q2) use ($today, $overstayCutoff) {
                        $q2->whereDate('check_out_date', '<=', $today)
                            ->whereDate('check_out_date', '>=', $overstayCutoff);
                    });
            });
    }

    public static function extendStay(Reservation $reservation, string $newCheckOutDate, ?User $user = null): Reservation
    {
        if (! self::userCanManageStay($user)) {
            throw ValidationException::withMessages([
                'extend' => 'You do not have permission to extend stays.',
            ]);
        }

        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            throw ValidationException::withMessages([
                'extendNewCheckOutDate' => 'Only in-house guests can have their stay extended.',
            ]);
        }

        $hotel = Hotel::getHotel() ?? $reservation->hotel;
        if ($hotel) {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        }

        $newCheckOut = Carbon::parse($newCheckOutDate)->format('Y-m-d');
        $checkIn = $reservation->check_in_date->format('Y-m-d');
        $previousCheckOut = $reservation->check_out_date->format('Y-m-d');

        if ($newCheckOut <= $checkIn) {
            throw ValidationException::withMessages([
                'extendNewCheckOutDate' => 'New check-out must be after check-in ('.$checkIn.').',
            ]);
        }

        if ($newCheckOut <= $previousCheckOut) {
            throw ValidationException::withMessages([
                'extendNewCheckOutDate' => 'New check-out must be after the current check-out ('.$previousCheckOut.').',
            ]);
        }

        $unitIds = $reservation->roomUnits()->pluck('room_units.id')->all();
        if ($unitIds !== []) {
            $conflict = Reservation::where('hotel_id', $reservation->hotel_id)
                ->where('id', '!=', $reservation->id)
                ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
                ->where('check_out_date', '>', $checkIn)
                ->where('check_in_date', '<', $newCheckOut)
                ->whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'extendNewCheckOutDate' => 'The room is already booked for part of the extended period.',
                ]);
            }
        }

        $reservation->check_out_date = $newCheckOut;
        $reservation->save();

        ActivityLogger::log(
            'reservation.extend_stay',
            sprintf(
                'Extended stay for %s — reservation %s (%s → %s)',
                $reservation->guest_name ?? '—',
                $reservation->reservation_number ?? $reservation->id,
                $previousCheckOut,
                $newCheckOut
            ),
            Reservation::class,
            $reservation->id,
            ['check_out_date' => $previousCheckOut],
            ['check_out_date' => $newCheckOut],
            ActivityLogModule::FRONT_OFFICE
        );

        return $reservation->fresh(['roomUnits.room', 'roomType', 'guests']);
    }
}
