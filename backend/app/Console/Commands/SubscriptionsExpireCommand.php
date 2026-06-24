<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

final class SubscriptionsExpireCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Mark overdue active subscriptions as expired';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $count = $subscriptionService->expireOverdue();

        $this->info("Expired subscriptions: {$count}.");

        return self::SUCCESS;
    }
}
