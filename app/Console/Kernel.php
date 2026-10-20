<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Uses schedule->call() + Artisan::call() instead of schedule->command()
     * because shared hosting often disables proc_open, which Symfony Process
     * (used by command()/runInBackground()) requires.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process scheduled bulk messages every minute
        $schedule->call(function () {
            Artisan::call('bulk:process-scheduled-messages');
        })
            ->name('bulk:process-scheduled-messages')
            ->everyMinute()
            ->withoutOverlapping();

        // Process pending bulk messages every minute in batches to avoid timeouts
        $schedule->call(function () {
            Artisan::call('bulk:process-pending-messages', ['--limit' => 100]);
        })
            ->name('bulk:process-pending-messages')
            ->everyMinute()
            ->withoutOverlapping();

        // Bill wallet for delivered bulk messages that webhook missed / failed to charge
        $schedule->call(function () {
            Artisan::call('wallet:charge-delivered', ['--limit' => 200]);
        })
            ->name('wallet:charge-delivered')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Process monthly package renewals daily at midnight
        $schedule->call(function () {
            Artisan::call('package:monthly-renewal');
        })
            ->name('package:monthly-renewal')
            ->dailyAt('00:00')
            ->withoutOverlapping();

        // Check for expired packages that need manual reactivation
        $schedule->call(function () {
            Artisan::call('package:check-expired');
        })
            ->name('package:check-expired')
            ->dailyAt('9:00')
            ->withoutOverlapping();

        // Send 6-day plan expiry push notifications via FCM at 1:25 PM daily
        $schedule->call(function () {
            Artisan::call('plan:check-expiry');
        })
            ->name('plan:check-expiry')
            ->dailyAt('9:00')
            ->withoutOverlapping();
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
