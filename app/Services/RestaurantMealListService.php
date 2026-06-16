<?php

namespace App\Services;

use App\Enums\MealPlan;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RestaurantMealListService
{
    public static function inHouseOnDateQuery(int $hotelId, string $date): Builder
    {
        return Reservation::query()
            ->where('hotel_id', $hotelId)
            ->where('status', Reservation::STATUS_CHECKED_IN)
            ->whereDate('check_in_date', '<=', $date)
            ->whereDate('check_out_date', '>', $date)
            ->with(['guests', 'roomUnits.room', 'roomType'])
            ->orderBy('guest_name');
    }

    /**
     * @return Collection<int, Reservation>
     */
    public static function guestsForMeal(int $hotelId, string $date, string $meal): Collection
    {
        return self::inHouseOnDateQuery($hotelId, $date)
            ->get()
            ->filter(fn (Reservation $reservation) => $reservation->mealIncludes($meal))
            ->values();
    }

    public static function mealGuestCount(Reservation $reservation): int
    {
        $guestRows = $reservation->guests->count();
        if ($guestRows > 0) {
            return $guestRows;
        }

        return max(1, (int) ($reservation->adult_count ?? 1) + (int) ($reservation->child_count ?? 0));
    }

    public static function mealPreferencesForList(Reservation $reservation, string $meal): string
    {
        return match ($meal) {
            'breakfast' => self::formatMealSlot(
                $reservation->breakfast_preferred_time,
                (bool) $reservation->breakfast_in_room
            ),
            'lunch' => self::formatMealSlot(
                $reservation->lunch_preferred_time,
                (bool) $reservation->lunch_in_room
            ),
            'dinner' => self::formatMealSlot(
                $reservation->dinner_preferred_time,
                (bool) $reservation->dinner_in_room
            ),
            default => '—',
        };
    }

    public static function formatMealSlot(?string $time, bool $inRoom): string
    {
        $parts = [];
        if ($time) {
            $parts[] = ReservationManageService::formatTimeForInput($time);
        }
        $parts[] = $inRoom ? 'In room' : 'Restaurant';

        return implode(' · ', $parts);
    }

    public static function roomLabels(Reservation $reservation): string
    {
        $labels = $reservation->roomUnits
            ->map(fn ($u) => $u->label ?? $u->room?->name)
            ->filter()
            ->unique()
            ->values();

        if ($labels->isNotEmpty()) {
            return $labels->implode(', ');
        }

        return $reservation->roomType?->name ?? '—';
    }
}
