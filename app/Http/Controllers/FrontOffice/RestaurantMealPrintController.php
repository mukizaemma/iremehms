<?php

namespace App\Http\Controllers\FrontOffice;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Services\RestaurantMealListService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RestaurantMealPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_restaurant_meals')) {
            abort(403);
        }

        $hotel = Hotel::getHotel();
        if (! $hotel) {
            abort(403, 'Hotel context required.');
        }

        $meal = $request->input('meal', 'breakfast');
        if (! in_array($meal, ['breakfast', 'lunch', 'dinner'], true)) {
            $meal = 'breakfast';
        }

        $date = $request->input('date', Hotel::getTodayForHotel());

        $reservations = RestaurantMealListService::guestsForMeal($hotel->id, $date, $meal);

        $rows = $reservations->map(function ($reservation) use ($meal) {
            return [
                'room' => RestaurantMealListService::roomLabels($reservation),
                'guest_name' => $reservation->guest_name,
                'reservation_number' => $reservation->reservation_number,
                'meal_plan' => $reservation->mealPlanEnum()->shortLabel(),
                'covers' => RestaurantMealListService::mealGuestCount($reservation),
                'preferences' => RestaurantMealListService::mealPreferencesForList($reservation, $meal),
                'notes' => $reservation->meal_service_notes,
                'check_out' => $reservation->check_out_date->format('d/m/Y'),
            ];
        });

        $mealLabel = match ($meal) {
            'breakfast' => 'Breakfast',
            'lunch' => 'Lunch',
            'dinner' => 'Dinner',
            default => ucfirst($meal),
        };

        return view('front-office.restaurant-meal-print', [
            'hotel' => $hotel,
            'mealLabel' => $mealLabel,
            'date' => Carbon::parse($date)->format('d M Y'),
            'rows' => $rows,
            'totalCovers' => (int) $rows->sum('covers'),
            'printedAt' => Carbon::now($hotel->getTimezone())->format('d/m/Y H:i'),
        ]);
    }
}
