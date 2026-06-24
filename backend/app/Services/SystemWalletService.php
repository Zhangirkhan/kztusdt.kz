<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Tron\TronGridClient;
use App\Support\NetworkRegistry;
use Throwable;

final class SystemWalletService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly BscRpcClient $rpc,
        private readonly TronGridClient $tronRpc,
    ) {}

    /**
     * System (hot/gas) wallets for a single network with on-chain balances.
     *
     * @return list<array{
     *     label: string,
     *     role: string,
     *     network: string,
     *     path: string,
     *     address: string|null,
     *     native: string|null,
     *     native_asset: string,
     *     usdt: string|null,
     *     explorer_address: string,
     *     error: string|null
     * }>
     */
    public function wallets(string $network, bool $withBalances = true): array
    {
        $definitions = $network === 'TRC20'
            ? [
                ['label' => 'Hot wallet', 'role' => 'hot', 'path' => (string) config('tron.hot_wallet_path')],
                ['label' => 'Gas wallet', 'role' => 'gas', 'path' => (string) config('tron.gas_wallet_path')],
            ]
            : [
                ['label' => 'Hot wallet', 'role' => 'hot', 'path' => (string) config('sweep.hot_wallet_path')],
                ['label' => 'Gas wallet', 'role' => 'gas', 'path' => (string) config('sweep.gas_wallet_path')],
            ];

        $nativeAsset = (string) (NetworkRegistry::get($network)['native_asset'] ?? '');
        $explorerAddress = (string) (NetworkRegistry::get($network)['explorer_address'] ?? '');

        $rows = [];

        foreach ($definitions as $definition) {
            $row = [
                'label' => $definition['label'],
                'role' => $definition['role'],
                'network' => $network,
                'path' => $definition['path'],
                'address' => null,
                'native' => null,
                'native_asset' => $nativeAsset,
                'usdt' => null,
                'explorer_address' => $explorerAddress,
                'error' => null,
            ];

            try {
                $row['address'] = $this->walletService->systemAddress($definition['path'], $network);
            } catch (Throwable $exception) {
                $row['error'] = $exception->getMessage();
                $rows[] = $row;

                continue;
            }

            if ($withBalances) {
                try {
                    [$row['native'], $row['usdt']] = $this->balances($network, $row['address']);
                } catch (Throwable $exception) {
                    $row['error'] = 'Баланс недоступен: '.$exception->getMessage();
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{0:string,1:string} [nativeBalance, usdtBalance] (human units)
     */
    private function balances(string $network, string $address): array
    {
        if ($network === 'TRC20') {
            $decimals = (string) config('tron.usdt_decimals', 6);
            $native = bcdiv($this->tronRpc->getBalanceSun($address), bcpow('10', '6', 0), 6);
            $usdt = bcdiv(
                $this->tronRpc->trc20BalanceOf((string) config('tron.usdt_contract'), $address),
                bcpow('10', $decimals, 0),
                6,
            );

            return [$native, $usdt];
        }

        $decimals = (string) config('bsc.usdt_decimals', 18);
        $native = bcdiv($this->rpc->getBalance($address), bcpow('10', '18', 0), 6);
        $usdt = bcdiv(
            $this->rpc->tokenBalanceOf((string) config('bsc.usdt_contract'), $address),
            bcpow('10', $decimals, 0),
            6,
        );

        return [$native, $usdt];
    }
}
