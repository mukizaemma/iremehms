<?php

namespace App\Services;

use App\Enums\MealPlan;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomUnit;
use App\Models\User;
use App\Support\ActivityLogModule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReservationManageService
{
    public static function userCanEdit(?User $user): bool
    {
        return $user && (
            $user->hasPermission('fo_check_in_out')
            || $user->hasPermission('fo_create_reservation')
            || $user->canNavigateModules()
            || $user->isSuperAdmin()
        );
    }

    public static function userCanMoveRoom(?User $user): bool
    {
        return self::userCanEdit($user);
    }

    public static function assertEditable(Reservation $reservation): void
    {
        if (in_array($reservation->status, [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Cancelled or checked-out reservations cannot be edited here.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $guestRows
     */
    public static function updateReservation(Reservation $reservation, array $data, ?array $guestRows, ?User $user = null): Reservation
    {
        if ($user && ! self::userCanEdit($user)) {
            throw ValidationException::withMessages(['reservation' => 'You do not have permission to edit reservations.']);
        }

        self::assertEditable($reservation);

        $hotel = Hotel::getHotel() ?? $reservation->hotel;
        if ($hotel) {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        }

        $checkIn = Carbon::parse((string) $data['check_in_date'])->format('Y-m-d');
        $checkOut = Carbon::parse((string) $data['check_out_date'])->format('Y-m-d');

        if ($checkOut <= $checkIn) {
            throw ValidationException::withMessages([
                'editCheckOutDate' => 'Check-out must be after check-in.',
            ]);
        }

        $unitIds = $reservation->roomUnits()->pluck('room_units.id')->all();
        if ($unitIds !== []) {
            self::assertRoomsAvailable($reservation, $unitIds, $checkIn, $checkOut);
        }

        $plan = MealPlan::parse((string) ($data['meal_plan'] ?? $reservation->meal_plan));
        $roomRef = max(0, (float) ($data['room_rate_amount'] ?? 0));
        $suppRef = $plan->allowsMealSupplement() ? max(0, (float) ($data['meal_plan_supplement'] ?? 0)) : 0;
        $roomComp = (bool) ($data['is_room_complimentary'] ?? false);
        $mealComp = (bool) ($data['is_meal_complimentary'] ?? false);
        self::validateComplimentary($roomComp, $mealComp, $data['complimentary_reason'] ?? null);

        $chargeRoom = $roomComp ? 0.0 : $roomRef;
        $chargeMeal = $mealComp ? 0.0 : $suppRef;
        $total = max(0, (float) ($data['total_amount'] ?? ($chargeRoom + $chargeMeal)));

        return DB::transaction(function () use ($reservation, $data, $guestRows, $checkIn, $checkOut, $plan, $roomRef, $suppRef, $roomComp, $mealComp, $total, $user) {
            $before = $reservation->only([
                'check_in_date', 'check_out_date', 'meal_plan', 'total_amount',
            ]);

            $reservation->check_in_date = $checkIn;
            $reservation->check_out_date = $checkOut;
            $reservation->meal_plan = $plan->value;
            $reservation->room_rate_amount = $roomRef;
            $reservation->meal_plan_supplement = $suppRef;
            $reservation->is_room_complimentary = $roomComp;
            $reservation->is_meal_complimentary = $mealComp;
            $reservation->complimentary_reason = ($roomComp || $mealComp)
                ? trim((string) ($data['complimentary_reason'] ?? ''))
                : null;
            $reservation->total_amount = $total;

            if (isset($data['guest_name'])) {
                $reservation->guest_name = trim((string) $data['guest_name']) ?: $reservation->guest_name;
            }
            if (array_key_exists('guest_phone', $data)) {
                $reservation->guest_phone = $data['guest_phone'] ?: null;
            }
            if (array_key_exists('guest_email', $data)) {
                $reservation->guest_email = $data['guest_email'] ?: null;
            }

            $reservation->breakfast_preferred_time = self::normalizeTime($data['breakfast_preferred_time'] ?? null);
            $reservation->lunch_preferred_time = self::normalizeTime($data['lunch_preferred_time'] ?? null);
            $reservation->dinner_preferred_time = self::normalizeTime($data['dinner_preferred_time'] ?? null);
            $reservation->breakfast_in_room = (bool) ($data['breakfast_in_room'] ?? false);
            $reservation->lunch_in_room = (bool) ($data['lunch_in_room'] ?? false);
            $reservation->dinner_in_room = (bool) ($data['dinner_in_room'] ?? false);
            $reservation->meal_service_notes = trim((string) ($data['meal_service_notes'] ?? '')) ?: null;

            $reservation->save();

            if ($guestRows !== null) {
                self::syncGuestRows($reservation, $guestRows, $checkIn, $checkOut);
            }

            ActivityLogger::log(
                'reservation.updated',
                sprintf('Updated reservation %s — stay %s to %s, board %s', $reservation->reservation_number ?? $reservation->id, $checkIn, $checkOut, $plan->shortLabel()),
                Reservation::class,
                $reservation->id,
                $before,
                $reservation->only(['check_in_date', 'check_out_date', 'meal_plan', 'total_amount']),
                ActivityLogModule::FRONT_OFFICE
            );

            return $reservation->fresh(['roomType', 'roomUnits.room', 'guests']);
        });
    }

    public static function moveToRoom(Reservation $reservation, int $roomUnitId, ?string $reason, ?User $user = null): Reservation
    {
        if ($user && ! self::userCanMoveRoom($user)) {
            throw ValidationException::withMessages(['moveRoom' => 'You do not have permission to move guests.']);
        }

        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN], true)) {
            throw ValidationException::withMessages([
                'moveRoom' => 'Only confirmed or in-house reservations can be moved to another room.',
            ]);
        }

        $hotel = Hotel::getHotel() ?? $reservation->hotel;
        if ($hotel) {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        }

        $unit = RoomUnit::with('room')->where('id', $roomUnitId)->where('is_active', true)->first();
        if (! $unit || ! $unit->room || (int) $unit->room->hotel_id !== (int) $reservation->hotel_id) {
            throw ValidationException::withMessages(['moveRoomUnitId' => 'Select a valid room or unit.']);
        }

        if ($reservation->room_type_id && (int) $unit->room->room_type_id !== (int) $reservation->room_type_id) {
            throw ValidationException::withMessages([
                'moveRoomUnitId' => 'Selected room does not match the booked room type. Change room type on the booking first or pick a matching unit.',
            ]);
        }

        $checkIn = $reservation->check_in_date->format('Y-m-d');
        $checkOut = $reservation->check_out_date->format('Y-m-d');

        $conflict = Reservation::where('hotel_id', $reservation->hotel_id)
            ->where('id', '!=', $reservation->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>', $checkIn)
            ->where('check_in_date', '<', $checkOut)
            ->whereHas('roomUnits', fn ($q) => $q->where('room_units.id', $roomUnitId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'moveRoomUnitId' => 'This room is already booked for the selected dates.',
            ]);
        }

        $fromLabels = $reservation->roomUnits->pluck('label')->join(', ') ?: '—';

        return DB::transaction(function () use ($reservation, $roomUnitId, $reason, $fromLabels, $unit) {
            $reservation->roomUnits()->sync([$roomUnitId]);
            if (! $reservation->room_type_id) {
                $reservation->room_type_id = $unit->room->room_type_id;
                $reservation->save();
            }

            ActivityLogger::log(
                'reservation.room_move',
                sprintf(
                    'Moved reservation %s from %s to %s%s',
                    $reservation->reservation_number ?? $reservation->id,
                    $fromLabels,
                    $unit->label,
                    $reason ? ' — '.$reason : ''
                ),
                Reservation::class,
                $reservation->id,
                ['from_rooms' => $fromLabels],
                ['to_room' => $unit->label, 'reason' => $reason],
                ActivityLogModule::FRONT_OFFICE
            );

            return $reservation->fresh(['roomType', 'roomUnits.room', 'guests']);
        });
    }

    /**
     * @param  list<int>  $unitIds
     */
    public static function assertRoomsAvailable(Reservation $reservation, array $unitIds, string $checkIn, string $checkOut): void
    {
        $conflict = Reservation::where('hotel_id', $reservation->hotel_id)
            ->where('id', '!=', $reservation->id)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_CHECKED_OUT])
            ->where('check_out_date', '>', $checkIn)
            ->where('check_in_date', '<', $checkOut)
            ->whereHas('roomUnits', fn ($q) => $q->whereIn('room_units.id', $unitIds))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'editCheckOutDate' => 'Assigned room is not available for the new dates. Adjust dates or move the guest first.',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $guestRows
     */
    protected static function syncGuestRows(Reservation $reservation, array $guestRows, string $resCheckIn, string $resCheckOut): void
    {
        foreach ($guestRows as $row) {
            $guestId = (int) ($row['id'] ?? 0);
            if ($guestId <= 0) {
                continue;
            }

            $guest = ReservationGuest::where('reservation_id', $reservation->id)->where('id', $guestId)->first();
            if (! $guest) {
                continue;
            }

            if (isset($row['full_name']) && trim((string) $row['full_name']) !== '') {
                $guest->full_name = trim((string) $row['full_name']);
            }
            if (isset($row['check_in_date'])) {
                $guest->check_in_date = $row['check_in_date'] ?: $resCheckIn;
            }
            if (isset($row['check_out_date'])) {
                $guest->check_out_date = $row['check_out_date'] ?: $resCheckOut;
            }

            $guest->breakfast_preferred_time = self::normalizeTime($row['breakfast_preferred_time'] ?? null);
            $guest->dinner_preferred_time = self::normalizeTime($row['dinner_preferred_time'] ?? null);
            $guest->breakfast_in_room = (bool) ($row['breakfast_in_room'] ?? false);
            $guest->dinner_in_room = (bool) ($row['dinner_in_room'] ?? false);
            $guest->meal_service_notes = trim((string) ($row['meal_service_notes'] ?? '')) ?: null;
            $guest->save();
        }
    }

    public static function validateComplimentary(bool $roomComp, bool $mealComp, ?string $reason): void
    {
        if (! $roomComp && ! $mealComp) {
            return;
        }

        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'complimentaryReason' => 'Enter the reason for offering complimentary room and/or meals.',
                'checkInComplimentaryReason' => 'Enter the reason for offering complimentary room and/or meals.',
                'editComplimentaryReason' => 'Enter the reason for offering complimentary room and/or meals.',
            ]);
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float} charge room, charge meal, total
     */
    public static function computeCharges(
        MealPlan $plan,
        float $roomRef,
        float $suppRef,
        bool $roomComp,
        bool $mealComp,
    ): array {
        $chargeRoom = $roomComp ? 0.0 : $roomRef;
        $chargeMeal = $mealComp ? 0.0 : ($plan->allowsMealSupplement() ? $suppRef : 0.0);

        return [$chargeRoom, $chargeMeal, $chargeRoom + $chargeMeal];
    }

    public static function normalizeTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function formatTimeForInput(?string $dbTime): string
    {
        if (! $dbTime) {
            return '';
        }

        try {
            return Carbon::parse($dbTime)->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function mealPreferenceSummary(Reservation $reservation): string
    {
        $parts = [];

        if ($reservation->breakfast_preferred_time || $reservation->breakfast_in_room) {
            $b = 'Breakfast';
            if ($reservation->breakfast_preferred_time) {
                $b .= ' '.self::formatTimeForInput($reservation->breakfast_preferred_time);
            }
            $b .= $reservation->breakfast_in_room ? ' (in room)' : ' (restaurant)';
            $parts[] = $b;
        }

        if ($reservation->lunch_preferred_time || $reservation->lunch_in_room) {
            $l = 'Lunch';
            if ($reservation->lunch_preferred_time) {
                $l .= ' '.self::formatTimeForInput($reservation->lunch_preferred_time);
            }
            $l .= $reservation->lunch_in_room ? ' (in room)' : ' (restaurant)';
            $parts[] = $l;
        }

        if ($reservation->dinner_preferred_time || $reservation->dinner_in_room) {
            $d = 'Dinner';
            if ($reservation->dinner_preferred_time) {
                $d .= ' '.self::formatTimeForInput($reservation->dinner_preferred_time);
            }
            $d .= $reservation->dinner_in_room ? ' (in room)' : ' (restaurant)';
            $parts[] = $d;
        }

        if ($reservation->meal_service_notes) {
            $parts[] = 'Note: '.$reservation->meal_service_notes;
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }
}
