<?php

namespace App\Services;

use App\Enums\MealPlan;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ComplementaryReportService
{
    public static function complementaryQuery(int $hotelId, string $dateFrom, string $dateTo): Builder
    {
        return Reservation::query()
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', [Reservation::STATUS_CANCELLED])
            ->whereDate('check_in_date', '<=', $dateTo)
            ->whereDate('check_out_date', '>=', $dateFrom)
            ->where(function (Builder $q) {
                $q->where('is_room_complimentary', true)
                    ->orWhere('is_meal_complimentary', true)
                    ->orWhere('meal_plan', MealPlan::COMP->value);
            })
            ->with(['roomType', 'roomUnits.room'])
            ->orderBy('check_in_date')
            ->orderBy('guest_name');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function buildRows(int $hotelId, string $dateFrom, string $dateTo): Collection
    {
        return self::complementaryQuery($hotelId, $dateFrom, $dateTo)
            ->get()
            ->map(fn (Reservation $r) => self::rowFromReservation($r));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rowFromReservation(Reservation $r): array
    {
        $nights = max(1, $r->check_in_date->diffInDays($r->check_out_date));
        $roomComp = $r->isRoomComplimentary();
        $mealComp = $r->isMealComplimentary();
        $roomRate = (float) ($r->room_rate_amount ?? 0);
        $supplement = (float) ($r->meal_plan_supplement ?? 0);

        $roomValueWaived = $roomComp ? $roomRate * $nights : 0.0;
        $mealValueWaived = $mealComp ? $supplement * $nights : 0.0;

        return [
            'reservation_id' => $r->id,
            'reservation_number' => $r->reservation_number,
            'guest_name' => $r->guest_name,
            'status' => $r->status,
            'check_in' => $r->check_in_date->format('Y-m-d'),
            'check_out' => $r->check_out_date->format('Y-m-d'),
            'nights' => $nights,
            'room' => RestaurantMealListService::roomLabels($r),
            'services' => $r->complimentaryServicesLabel(),
            'is_room_complimentary' => $roomComp,
            'is_meal_complimentary' => $mealComp,
            'meal_plan' => $r->mealPlanEnum()->shortLabel(),
            'reason' => $r->complimentary_reason ?? '—',
            'room_rate_per_night' => $roomRate,
            'meal_supplement_per_night' => $supplement,
            'room_value_waived' => round($roomValueWaived, 2),
            'meal_value_waived' => round($mealValueWaived, 2),
            'total_value_waived' => round($roomValueWaived + $mealValueWaived, 2),
            'booking_total' => (float) ($r->total_amount ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{count: int, room_waived: float, meal_waived: float, total_waived: float}
     */
    public static function summarize(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'room_waived' => round((float) $rows->sum('room_value_waived'), 2),
            'meal_waived' => round((float) $rows->sum('meal_value_waived'), 2),
            'total_waived' => round((float) $rows->sum('total_value_waived'), 2),
        ];
    }
}
