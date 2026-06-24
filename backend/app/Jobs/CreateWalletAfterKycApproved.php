<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\UserNotificationService;
use App\Services\WalletService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CreateWalletAfterKycApproved implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(
        WalletService $walletService,
        UserNotificationService $notifier,
    ): void {
        $user = User::query()->find($this->userId);

        if ($user === null || $user->kyc_status !== 'approved') {
            return;
        }

        $wallets = $walletService->ensureWalletsForUser($user);

        if ($wallets->isEmpty()) {
            return;
        }

        $lines = $wallets
            ->map(fn ($wallet): string => "<b>{$wallet->asset} · {$wallet->network}</b>\n<code>{$wallet->address}</code>")
            ->implode("\n\n");

        $notifier->notifyUser(
            $user,
            "💼 Ваши кошельки созданы!\n\nАдреса для пополнения:\n\n{$lines}",
        );
    }
}
