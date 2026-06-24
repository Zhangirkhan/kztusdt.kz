<?php

use App\Support\NetworkRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sweeper runs every minute but is a no-op unless SWEEP_ENABLED=true.
Schedule::command('sweep:run')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// BEP20 (BSC USDT) deposit indexer. Scheduled only while the network is enabled
// so deposits are credited without relying on the long-running deposits:watch.
if (NetworkRegistry::isEnabled('BEP20')) {
    Schedule::command('deposits:scan')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// TRC20 (TRON USDT) deposit indexer. Polls TronGrid per deposit address.
if (NetworkRegistry::isEnabled('TRC20')) {
    Schedule::command('deposits:scan-tron')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Withdrawal sender runs every minute but is a no-op unless WITHDRAWALS_ENABLED=true.
Schedule::command('withdrawals:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Expire subscriptions that passed their expires_at.
Schedule::command('subscriptions:expire')->hourly();

Schedule::command('rates:refresh')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
