<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DepositIndexerService;
use Illuminate\Console\Command;
use Throwable;

final class DepositsWatchCommand extends Command
{
    protected $signature = 'deposits:watch {--interval=15 : Seconds between passes}';

    protected $description = 'Continuously index BEP20 deposits (long-running)';

    public function handle(DepositIndexerService $indexer): int
    {
        $interval = max(5, (int) $this->option('interval'));

        $this->info("Deposit indexer started (interval={$interval}s). Ctrl+C to stop.");

        while (true) {
            try {
                $result = $indexer->scan();

                if ($result['detected'] > 0 || $result['credited'] > 0) {
                    $this->info(sprintf(
                        '[%s] head=%d detected=%d credited=%d',
                        now()->toTimeString(),
                        $result['head'],
                        $result['detected'],
                        $result['credited'],
                    ));
                }
            } catch (Throwable $exception) {
                $this->error('['.now()->toTimeString().'] '.$exception->getMessage());
            }

            sleep($interval);
        }
    }
}
