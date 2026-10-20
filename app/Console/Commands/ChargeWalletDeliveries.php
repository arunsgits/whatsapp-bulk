<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class ChargeWalletDeliveries extends Command
{
    protected $signature = 'wallet:charge-delivered {--limit=200 : Max delivery rows to process}';

    protected $description = 'Charge wallet for delivered/read bulk messages that were not billed yet (wallet-enabled users)';

    public function handle(WalletService $wallet): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $this->info("Scanning up to {$limit} unbilled delivered messages...");

        $result = $wallet->chargeUnbilledDeliveries($limit);

        $this->info(sprintf(
            'Done. scanned=%d charged=%d skipped=%d failed=%d',
            $result['scanned'],
            $result['charged'],
            $result['skipped'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
