<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WithdrawalService;
use Illuminate\Console\Command;

final class WithdrawalsProcessCommand extends Command
{
    protected $signature = 'withdrawals:process';

    protected $description = 'Broadcast approved withdrawals from the hot wallet and confirm sent ones (no-op while WITHDRAWALS_ENABLED=false)';

    public function handle(WithdrawalService $withdrawalService): int
    {
        $result = $withdrawalService->processQueue();

        if (! $result['enabled']) {
            $this->info('Withdrawals disabled (WITHDRAWALS_ENABLED=false), nothing sent.');

            return self::SUCCESS;
        }

        $this->info("Broadcast: {$result['broadcast']}, completed: {$result['completed']}, failed: {$result['failed']}.");

        return self::SUCCESS;
    }
}
