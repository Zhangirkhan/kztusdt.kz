<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExchangeOrderService;
use Illuminate\Console\Command;

final class ExchangeOrdersExpireCommand extends Command
{
    protected $signature = 'exchange:expire-orders';

    protected $description = 'Report exchange orders past their payment term (appeals remain available)';

    public function handle(ExchangeOrderService $exchangeOrderService): int
    {
        $count = $exchangeOrderService->expireOverdue();

        $this->info("Overdue exchange orders (not auto-cancelled): {$count}.");

        return self::SUCCESS;
    }
}
