<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivePackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MonthlyPackageRenewal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'package:monthly-renewal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process monthly package renewals and reset usage counters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly package renewal process...');
        Log::info('Starting monthly package renewal process...');

        // Get all packages that are not yet marked as expired
        $activePackages = ActivePackage::where('status', '!=', ActivePackage::STATUS_EXPIRED)
            ->with('package')
            ->get();

        $renewedCount = 0;
        $expiredCount = 0;
        $activatedCount = 0;

        foreach ($activePackages as $activePackage) {
            $now = Carbon::now();

            // 1. Check if overall package validity has ended -> mark expired
            if ($activePackage->endDate && $now->gt($activePackage->endDate)) {
                $activePackage->update(['status' => ActivePackage::STATUS_EXPIRED]);
                $expiredCount++;
                $this->warn("Package expired for ID: {$activePackage->id} (User: {$activePackage->userId}) - Validity ended");
                continue;
            }

            // 2. Check if an upcoming package has now reached its start date -> mark active
            if ((int) $activePackage->status === ActivePackage::STATUS_UPCOMING && $activePackage->startDate && $now->gte($activePackage->startDate)) {
                $activePackage->update(['status' => ActivePackage::STATUS_ACTIVE]);
                $activatedCount++;
                $this->info("Package activated for ID: {$activePackage->id} (User: {$activePackage->userId}) - Start date reached");
            }

            // 3. Only yearly plans have monthly recurring quota resets within the active validity
            if ($activePackage->is_yearly && (int) $activePackage->status === ActivePackage::STATUS_ACTIVE) {
                $didReset = $activePackage->checkAndPerformMonthlyReset();
                if ($didReset) {
                    $renewedCount++;
                    $this->info("Renewed monthly limits for package ID: {$activePackage->id} (User: {$activePackage->userId}) - Monthly reset completed");
                }
            }
        }

        $this->info("Monthly renewal process completed:");
        $this->info("- Activated packages: {$activatedCount}");
        $this->info("- Renewed monthly quotas: {$renewedCount}");
        $this->info("- Expired packages: {$expiredCount}");

        return 0;
    }
}
