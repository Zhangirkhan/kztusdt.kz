<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RateService;
use Illuminate\Console\Command;

final class RatesRefreshCommand extends Command
{
    protected $signature = 'rates:refresh';

    protected $description = 'Fetch and cache USDT/KZT exchange rate (background job)';

    public function handle(RateService $rateService): int
    {
        $rate = $rateService->refresh();

        $this->info(sprintf(
            'Rate cached: %.4f KZT (source: %s%s)',
            $rate['rate'],
            $rate['source'],
            $rate['stale'] ? ', stale' : '',
        ));

        return self::SUCCESS;
    }
}
