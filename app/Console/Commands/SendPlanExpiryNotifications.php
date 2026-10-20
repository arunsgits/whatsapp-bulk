<?php

namespace App\Console\Commands;

use App\Jobs\SendPlanExpiryNotification;
use App\Models\UserPackageHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPlanExpiryNotifications extends Command
{
    protected $signature   = 'plan:check-expiry {--days=6 : Days before expiry to notify}';
    protected $description = 'Check plan expiry dates and dispatch FCM push notifications to users expiring in N days';

    public function handle(): int
    {
        $days       = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($days)->toDateString();

        $this->info("[SendPlanExpiryNotifications] Checking for plans expiring on {$targetDate} ({$days} days from now)...");
        Log::info("[SendPlanExpiryNotifications] Running check. Target date: {$targetDate}");

        // Query the user_package_histories table for the LATEST plan per user
        // whose endDate matches target date AND notified_6day = false
        $histories = UserPackageHistory::with('user')
            ->whereDate('endDate', $targetDate)
            ->where(function ($q) {
                // NULL means never notified — treat same as false
                $q->where('notified_6day', false)
                  ->orWhereNull('notified_6day');
            })
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })
            ->get();

        if ($histories->isEmpty()) {
            $this->info("[SendPlanExpiryNotifications] No users to notify today.");
            Log::info("[SendPlanExpiryNotifications] No matching records found.");
            return Command::SUCCESS;
        }

        $dispatched = 0;
        $skipped    = 0;

        foreach ($histories as $history) {
            $user = $history->user;

            if (!$user) {
                $this->warn("  [SKIP] History #{$history->id} — user not found.");
                $skipped++;
                continue;
            }

            $endDateFormatted = $history->endDate
                ? Carbon::parse($history->endDate)->format('d M Y')
                : $targetDate;

            $planName = $history->packageName ?? 'Your Plan';

            $this->info("  [DISPATCH] User #{$user->id} ({$user->name}) — Plan: {$planName} — Expires: {$endDateFormatted}");
            Log::info("[SendPlanExpiryNotifications] Dispatching job for User #{$user->id} ({$user->name})");

            SendPlanExpiryNotification::dispatch(
                $user->id,
                $history->id,
                $planName,
                $endDateFormatted
            );

            $dispatched++;
        }

        $this->info("[SendPlanExpiryNotifications] Done. Dispatched: {$dispatched} | Skipped: {$skipped}");
        Log::info("[SendPlanExpiryNotifications] Complete. Dispatched: {$dispatched} | Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
