<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

final class WebPushVapidCommand extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push (PWA) notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->info('VAPID keys generated. Add these to your .env:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->components->warn('Keep the private key secret. Changing keys invalidates existing subscriptions.');

        return self::SUCCESS;
    }
}
