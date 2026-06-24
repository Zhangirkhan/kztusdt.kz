<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tron\TronDepositIndexerService;
use Illuminate\Console\Command;
use Throwable;

final class DepositsScanTronCommand extends Command
{
    protected $signature = 'deposits:scan-tron';

    protected $description = 'Run one TRC20 (TRON USDT) deposit indexing pass';

    public function handle(TronDepositIndexerService $indexer): int
    {
        try {
            $result = $indexer->scan();
        } catch (Throwable $exception) {
            $this->error('TRC20 scan failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'head=%d wallets=%d detected=%d credited=%d',
            $result['head'],
            $result['wallets'],
            $result['detected'],
            $result['credited'],
        ));

        return self::SUCCESS;
    }
}
