<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Business day rollover: at/after 3:00 AM (hotel time) auto-close past day and open new one; auto-open first shift if STRICT_SHIFT
        $schedule->command('business-day:rollover')->everyFiveMinutes();

        // Auto-close expired shifts every 5 minutes
        $schedule->call(function () {
            \App\Services\TimeAndShiftResolver::autoCloseExpiredShifts();
        })->everyFiveMinutes();

        // Subscription: generate invoices 15 days before due (daily)
        $schedule->command('subscription:generate-invoices')->dailyAt('08:00');
        // Subscription: send payment reminders at 7 days and 24 hours before due (daily)
        $schedule->command('subscription:send-reminders')->dailyAt('09:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
