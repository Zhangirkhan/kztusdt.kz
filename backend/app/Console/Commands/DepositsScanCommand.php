<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DepositIndexerService;
use Illuminate\Console\Command;
use Throwable;

final class DepositsScanCommand extends Command
{
    protected $signature = 'deposits:scan';

    protected $description = 'Run one BEP20 deposit indexing pass';

    public function handle(DepositIndexerService $indexer): int
    {
        try {
            $result = $indexer->scan();
        } catch (Throwable $exception) {
            $this->error('Scan failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'head=%d scanned=%d..%d detected=%d credited=%d',
            $result['head'],
            $result['scanned_from'],
            $result['scanned_to'],
            $result['detected'],
            $result['credited'],
        ));

        return self::SUCCESS;
    }
}
