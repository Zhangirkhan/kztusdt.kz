<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExchangeOrderService;
use Illuminate\Console\Command;

final class ExchangeOrdersExpireCommand extends Command
{
    protected $signature = 'exchange:expire-orders';

    protected $description = 'Cancel exchange orders that exceeded their payment term';

    public function handle(ExchangeOrderService $exchangeOrderService): int
    {
        $count = $exchangeOrderService->expireOverdue();

        $this->info("Expired exchange orders: {$count}.");

        return self::SUCCESS;
    }
}
