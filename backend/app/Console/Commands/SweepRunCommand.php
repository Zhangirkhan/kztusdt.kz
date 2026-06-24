<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SweepService;
use App\Services\Tron\TronSweepService;
use Illuminate\Console\Command;
use Throwable;

final class SweepRunCommand extends Command
{
    protected $signature = 'sweep:run';

    protected $description = 'Run one sweep pass: fund gas and move confirmed deposits to the hot wallet';

    public function handle(SweepService $service, TronSweepService $tronService): int
    {
        $failed = false;

        try {
            $bep20 = $service->run();
            $this->report('BEP20', $bep20);
        } catch (Throwable $exception) {
            $this->error('BEP20 sweep failed: '.$exception->getMessage());
            $failed = true;
        }

        try {
            $trc20 = $tronService->run();
            $this->report('TRC20', $trc20);
        } catch (Throwable $exception) {
            $this->error('TRC20 sweep failed: '.$exception->getMessage());
            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{enabled:bool, queued:int, processed:int, swept:int}  $result
     */
    private function report(string $network, array $result): void
    {
        if (! $result['enabled']) {
            $this->warn("{$network} sweep disabled. Nothing done.");

            return;
        }

        $this->info(sprintf(
            '%s queued=%d processed=%d swept=%d',
            $network,
            $result['queued'],
            $result['processed'],
            $result['swept'],
        ));
    }
}
