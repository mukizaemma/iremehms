<?php

namespace App\Livewire\FrontOffice;

use App\Enums\MealPlan;
use App\Models\Hotel;
use App\Services\RestaurantMealListService;
use App\Traits\ChecksModuleStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RestaurantMealBoard extends Component
{
    use ChecksModuleStatus;

    public string $mealTab = 'breakfast';

    public string $listDate = '';

    protected $queryString = [
        'mealTab' => ['except' => 'breakfast'],
        'listDate' => ['except' => ''],
    ];

    public function mount(): void
    {
        session(['selected_module' => 'front-office']);
        $this->ensureModuleEnabled('front-office');
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('fo_restaurant_meals')) {
            abort(403, 'You do not have access to the restaurant meal board.');
        }

        if ($this->listDate === '') {
            $this->listDate = Hotel::getTodayForHotel();
        }
    }

    public function setMealTab(string $tab): void
    {
        if (! in_array($tab, ['breakfast', 'lunch', 'dinner'], true)) {
            return;
        }
        $this->mealTab = $tab;
    }

    /**
     * @return array{rows: \Illuminate\Support\Collection, total_covers: int, meal_label: string}
     */
    public function getMealListProperty(): array
    {
        $hotel = Hotel::getHotel();
        $reservations = RestaurantMealListService::guestsForMeal($hotel->id, $this->listDate, $this->mealTab);

        $meal = $this->mealTab;
        $rows = $reservations->map(function ($reservation) use ($meal) {
            return [
                'reservation_id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'guest_name' => $reservation->guest_name,
                'room' => RestaurantMealListService::roomLabels($reservation),
                'meal_plan' => $reservation->isMealComplimentary()
                    ? 'Comp meals'
                    : $reservation->mealPlanEnum()->shortLabel(),
                'meal_plan_label' => $reservation->isMealComplimentary()
                    ? 'Complimentary meals'
                    : $reservation->mealPlanEnum()->label(),
                'covers' => RestaurantMealListService::mealGuestCount($reservation),
                'preferences' => RestaurantMealListService::mealPreferencesForList($reservation, $meal),
                'notes' => $reservation->meal_service_notes,
                'check_out' => $reservation->check_out_date->format('Y-m-d'),
            ];
        });

        return [
            'rows' => $rows,
            'total_covers' => (int) $rows->sum('covers'),
            'meal_label' => match ($this->mealTab) {
                'breakfast' => 'Breakfast',
                'lunch' => 'Lunch',
                'dinner' => 'Dinner',
                default => ucfirst($this->mealTab),
            },
        ];
    }

    public function render()
    {
        return view('livewire.front-office.restaurant-meal-board', [
            'mealPlansLegend' => MealPlan::cases(),
        ])->layout('livewire.layouts.app-layout');
    }
}
