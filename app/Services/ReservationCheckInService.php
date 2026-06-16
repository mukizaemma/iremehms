<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\RoomUnit;
use App\Models\User;
use App\Support\ActivityLogModule;
use App\Services\OperationalShiftActionGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReservationCheckInService
{
    public static function userCanCheckIn(?User $user): bool
    {
        return $user && ($user->hasPermission('fo_check_in_out') || $user->canNavigateModules() || $user->isSuperAdmin());
    }

    public static function canCheckInToday(Reservation $reservation): bool
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            return false;
        }

        $today = Hotel::getTodayForHotel();

        return $reservation->check_in_date->format('Y-m-d') <= $today;
    }

    /**
     * @param  list<array{full_name: string, id_number?: string|null, phone?: string|null, email?: string|null, country?: string|null, check_in_date?: string|null, check_out_date?: string|null, is_primary?: bool}>  $stayingGuests
     */
    public static function reservationNeedsRoomAssignment(Reservation $reservation): bool
    {
        return $reservation->roomUnits()->count() === 0;
    }

    public static function assignRoomUnit(Reservation $reservation, int $roomUnitId): void
    {
        $hotel = Hotel::getHotel() ?? $reservation->hotel;
        $unit = RoomUnit::with('room')->where('id', $roomUnitId)->where('is_active', true)->first();
        if (! $unit || ! $unit->room || (int) $unit->room->hotel_id !== (int) $reservation->hotel_id) {
            throw ValidationException::withMessages([
                'checkInRoomUnitId' => 'Select a valid room or unit.',
            ]);
        }

        if ($reservation->room_type_id && (int) $unit->room->room_type_id !== (int) $reservation->room_type_id) {
            throw ValidationException::withMessages([
                'checkInRoomUnitId' => 'Selected room does not match the booked room type.',
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
                'checkInRoomUnitId' => 'This room is already booked for the selected dates.',
            ]);
        }

        $reservation->roomUnits()->sync([$roomUnitId]);
        if (! $reservation->room_type_id) {
            $reservation->room_type_id = $unit->room->room_type_id;
            $reservation->save();
        }
    }

    public static function checkIn(
        Reservation $reservation,
        array $stayingGuests,
        bool $contactIsStayingGuest,
        ?User $user = null,
        ?int $roomUnitId = null,
    ): Reservation {
        if (! self::canCheckInToday($reservation)) {
            throw ValidationException::withMessages([
                'check_in' => 'Only confirmed reservations with arrival on or before today can be checked in.',
            ]);
        }

        if ($user && ! self::userCanCheckIn($user)) {
            throw ValidationException::withMessages([
                'check_in' => 'You do not have permission to check in guests.',
            ]);
        }

        $hotel = Hotel::getHotel() ?? $reservation->hotel;
        if ($hotel) {
            OperationalShiftActionGate::assertFrontOfficeActionAllowed($hotel);
        }

        $stayingGuests = array_values(array_filter($stayingGuests, fn ($g) => trim((string) ($g['full_name'] ?? '')) !== ''));
        if ($stayingGuests === []) {
            throw ValidationException::withMessages([
                'checkInGuests' => 'Enter at least one guest who will stay in the room.',
            ]);
        }

        $adultSlots = max(1, (int) ($reservation->adult_count ?? 1));
        if (count($stayingGuests) > $adultSlots) {
            throw ValidationException::withMessages([
                'checkInGuests' => 'You entered more guests than adults on the reservation ('.$adultSlots.').',
            ]);
        }

        if (self::reservationNeedsRoomAssignment($reservation)) {
            if (! $roomUnitId) {
                throw ValidationException::withMessages([
                    'checkInRoomUnitId' => 'Assign a room before checking in.',
                ]);
            }
            self::assignRoomUnit($reservation, $roomUnitId);
            $reservation->load('roomUnits.room');
        }

        self::validateStayingGuestRows($reservation, $stayingGuests);

        return DB::transaction(function () use ($reservation, $stayingGuests, $contactIsStayingGuest, $user) {
            $bookerName = $reservation->booker_name ?: $reservation->guest_name;
            if (! $contactIsStayingGuest && $bookerName === null) {
                $bookerName = $reservation->guest_name;
            }

            $reservation->guests()->delete();

            $primaryName = null;
            foreach ($stayingGuests as $index => $guest) {
                $isPrimary = (bool) ($guest['is_primary'] ?? ($index === 0));
                $fullName = trim((string) $guest['full_name']);
                [$guestCheckIn, $guestCheckOut] = self::resolveGuestStayDates($reservation, $guest);

                ReservationGuest::create([
                    'hotel_id' => $reservation->hotel_id,
                    'reservation_id' => $reservation->id,
                    'is_primary' => $isPrimary,
                    'sort_order' => $index,
                    'full_name' => $fullName,
                    'id_number' => $guest['id_number'] ?? null,
                    'phone' => $guest['phone'] ?? null,
                    'email' => $guest['email'] ?? null,
                    'country' => $guest['country'] ?? null,
                    'check_in_date' => $guestCheckIn,
                    'check_out_date' => $guestCheckOut,
                ]);
                if ($isPrimary) {
                    $primaryName = $fullName;
                    if (! empty($guest['id_number'])) {
                        $reservation->guest_id_number = $guest['id_number'];
                    }
                    if ($contactIsStayingGuest) {
                        if (! empty($guest['phone'])) {
                            $reservation->guest_phone = $guest['phone'];
                        }
                        if (! empty($guest['email'])) {
                            $reservation->guest_email = $guest['email'];
                        }
                        if (! empty($guest['country'])) {
                            $reservation->guest_country = $guest['country'];
                        }
                    }
                }
            }

            $displayNames = collect($stayingGuests)->pluck('full_name')->map(fn ($n) => trim((string) $n))->filter()->values();
            $reservation->guest_name = $displayNames->implode(', ');
            $reservation->booker_name = $contactIsStayingGuest ? null : ($bookerName ?: $reservation->guest_name);
            $previousStatus = $reservation->status;
            $reservation->status = Reservation::STATUS_CHECKED_IN;
            $reservation->save();

            ActivityLogger::log(
                'reservation.check_in',
                sprintf('Checked in guest %s — reservation %s', $reservation->guest_name ?? '—', $reservation->reservation_number ?? $reservation->id),
                Reservation::class,
                $reservation->id,
                ['status' => $previousStatus],
                ['status' => $reservation->status, 'guests' => $displayNames->all()],
                ActivityLogModule::FRONT_OFFICE
            );

            return $reservation->fresh(['guests', 'roomUnits.room', 'roomType']);
        });
    }

    /**
     * @param  list<array{full_name: string, id_number?: string|null, phone?: string|null, email?: string|null, country?: string|null, check_in_date?: string|null, check_out_date?: string|null, is_primary?: bool}>  $stayingGuests
     */
    public static function validateStayingGuestRows(Reservation $reservation, array $stayingGuests): void
    {
        $resCheckIn = $reservation->check_in_date->format('Y-m-d');
        $resCheckOut = $reservation->check_out_date->format('Y-m-d');

        foreach ($stayingGuests as $index => $guest) {
            if ($index === 0) {
                continue;
            }

            $name = trim((string) ($guest['full_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $phone = trim((string) ($guest['phone'] ?? ''));
            $guestLabel = 'Guest '.($index + 1);

            if ($phone === '') {
                throw ValidationException::withMessages([
                    "checkInGuests.{$index}.phone" => 'Enter a phone number for '.$guestLabel.'.',
                ]);
            }

            $checkIn = trim((string) ($guest['check_in_date'] ?? ''));
            $checkOut = trim((string) ($guest['check_out_date'] ?? ''));

            if ($checkIn === '') {
                throw ValidationException::withMessages([
                    "checkInGuests.{$index}.check_in_date" => 'Select a check-in date for '.$guestLabel.'.',
                ]);
            }

            if ($checkOut === '') {
                throw ValidationException::withMessages([
                    "checkInGuests.{$index}.check_out_date" => 'Select a check-out date for '.$guestLabel.'.',
                ]);
            }

            if ($checkOut <= $checkIn) {
                throw ValidationException::withMessages([
                    "checkInGuests.{$index}.check_out_date" => 'Check-out must be after check-in for '.$guestLabel.'.',
                ]);
            }

            if ($checkIn < $resCheckIn || $checkOut > $resCheckOut) {
                throw ValidationException::withMessages([
                    "checkInGuests.{$index}.check_in_date" => 'Stay dates for '.$guestLabel.' must be within the booking ('.$resCheckIn.' to '.$resCheckOut.').',
                ]);
            }
        }
    }

    /**
     * @param  array{check_in_date?: string|null, check_out_date?: string|null}  $guest
     * @return array{0: string, 1: string}
     */
    private static function resolveGuestStayDates(Reservation $reservation, array $guest): array
    {
        $resCheckIn = $reservation->check_in_date->format('Y-m-d');
        $resCheckOut = $reservation->check_out_date->format('Y-m-d');
        $checkIn = trim((string) ($guest['check_in_date'] ?? '')) ?: $resCheckIn;
        $checkOut = trim((string) ($guest['check_out_date'] ?? '')) ?: $resCheckOut;

        return [$checkIn, $checkOut];
    }

    public static function defaultReservationStayDates(Reservation $reservation): array
    {
        return [
            'check_in_date' => $reservation->check_in_date->format('Y-m-d'),
            'check_out_date' => $reservation->check_out_date->format('Y-m-d'),
        ];
    }

    /**
     * @return list<array{full_name: string, id_number: string, phone: string, email: string, country: string, check_in_date: string, check_out_date: string, is_primary: bool}>
     */
    public static function defaultGuestRowsForReservation(Reservation $reservation): array
    {
        $stay = self::defaultReservationStayDates($reservation);

        if ($reservation->guests->isNotEmpty()) {
            return $reservation->guests->sortBy('sort_order')->map(fn (ReservationGuest $g) => [
                'full_name' => $g->full_name,
                'id_number' => $g->id_number ?? '',
                'phone' => $g->phone ?? '',
                'email' => $g->email ?? '',
                'country' => $g->country ?? '',
                'check_in_date' => $g->check_in_date?->format('Y-m-d') ?? $stay['check_in_date'],
                'check_out_date' => $g->check_out_date?->format('Y-m-d') ?? $stay['check_out_date'],
                'is_primary' => (bool) $g->is_primary,
            ])->values()->all();
        }

        $slots = max(1, (int) ($reservation->adult_count ?? 1));
        $rows = [];
        for ($i = 0; $i < $slots; $i++) {
            $rows[] = [
                'full_name' => $i === 0 ? (string) ($reservation->guest_name ?? '') : '',
                'id_number' => $i === 0 ? (string) ($reservation->guest_id_number ?? '') : '',
                'phone' => $i === 0 ? (string) ($reservation->guest_phone ?? '') : '',
                'email' => $i === 0 ? (string) ($reservation->guest_email ?? '') : '',
                'country' => $i === 0 ? (string) ($reservation->guest_country ?? '') : '',
                'check_in_date' => $stay['check_in_date'],
                'check_out_date' => $stay['check_out_date'],
                'is_primary' => $i === 0,
            ];
        }

        return $rows;
    }
}
