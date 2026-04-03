<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\DayShiftService;
use Illuminate\Console\Command;

/**
 * Run business day rollover: auto-close past business day at rollover time (e.g. 3:00 AM)
 * and ensure the new business day exists. Optionally auto-open the first shift so POS can run.
 */
class BusinessDayRolloverCommand extends Command
{
    protected $signature = 'business-day:rollover {--hotel= : Hotel ID (default: first hotel with a user)}';

    protected $description = 'Auto-close past business day at rollover and ensure new day is open; optionally auto-open first shift.';

    public function handle(): int
    {
        $hotelId = $this->option('hotel');
        $hotel = $hotelId ? Hotel::find($hotelId) : Hotel::first();
        if (!$hotel) {
            $this->warn('No hotel found. Skipping rollover.');
            return 0;
        }

        $user = User::where('hotel_id', $hotel->id)->first();
        if (!$user) {
            $this->warn("No user for hotel [{$hotel->name}]. Run rollover from a logged-in session or create a hotel user.");
            return 0;
        }

        auth()->login($user);
        if (session()->isStarted() === false) {
            session()->start();
        }
        session()->put('current_hotel_id', $hotel->id);

        try {
            $open = BusinessDayService::getOpenBusinessDay();
            if (!$open) {
                $created = BusinessDayService::ensureOneOpenBusinessDay();
                if ($created) {
                    $this->info("Opened new business day: {$created->business_date->format('Y-m-d')}.");
                }
            }

            $open = BusinessDayService::getOpenBusinessDay();
            if ($open && $hotel->isStrictShiftMode()) {
                $firstPending = $open->dayShifts()->where('status', 'PENDING')->orderBy('start_at')->first();
                if ($firstPending) {
                    try {
                        DayShiftService::openShift($firstPending->id, $user->id);
                        $this->info("Auto-opened first shift: {$firstPending->name}.");
                    } catch (\Throwable $e) {
                        $this->warn("Could not auto-open first shift: {$e->getMessage()}");
                    }
                }
            }
        } finally {
            auth()->logout();
            session()->forget('current_hotel_id');
        }

        return 0;
    }
}
