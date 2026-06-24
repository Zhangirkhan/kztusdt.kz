<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SystemWalletService;
use App\Support\NetworkRegistry;
use Illuminate\Console\Command;

final class WalletSystemCommand extends Command
{
    protected $signature = 'wallet:system {--balances : Also query on-chain native/USDT balances}';

    protected $description = 'Show platform system wallet addresses (hot wallet, gas wallet) per network';

    public function handle(SystemWalletService $systemWalletService): int
    {
        $withBalances = (bool) $this->option('balances');

        foreach (NetworkRegistry::enabledCodes() as $network) {
            $this->line('');
            $this->info(NetworkRegistry::label($network));

            $rows = $systemWalletService->wallets($network, $withBalances);

            if ($withBalances) {
                $this->table(
                    ['Wallet', 'Path', 'Address', 'Native', 'USDT'],
                    array_map(
                        fn (array $row): array => [
                            $row['label'],
                            $row['path'],
                            $row['address'] ?? '—',
                            $row['native'] !== null ? $row['native'].' '.$row['native_asset'] : '—',
                            $row['usdt'] !== null ? $row['usdt'].' USDT' : '—',
                        ],
                        $rows,
                    ),
                );
            } else {
                $this->table(
                    ['Wallet', 'Path', 'Address'],
                    array_map(
                        fn (array $row): array => [$row['label'], $row['path'], $row['address'] ?? '—'],
                        $rows,
                    ),
                );
            }

            foreach ($rows as $row) {
                if ($row['error'] !== null) {
                    $this->warn("{$row['label']}: {$row['error']}");
                }
            }
        }

        $this->line('');
        $this->comment('Fund each GAS wallet with the native asset so sweepers can pay transfer fees.');

        return self::SUCCESS;
    }
}
