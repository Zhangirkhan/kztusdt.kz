<?php

declare(strict_types=1);

namespace App\Services\Tron;

use App\Models\Deposit;
use App\Models\WalletAddress;
use App\Services\DepositConfirmationService;
use App\Services\UserNotificationService;
use Throwable;

/**
 * Indexes TRC20 (USDT on TRON) deposits.
 *
 * Unlike the EVM indexer (block log scanning), TronGrid is queried per deposit
 * address for incoming, already-confirmed TRC20 transfers. Detected deposits are
 * stamped with their block number and credited once the chain head advances past
 * the required confirmation depth (shared with the EVM credit logic).
 */
final class TronDepositIndexerService
{
    private const NETWORK = 'TRC20';

    private const MAX_PAGES = 10;

    public function __construct(
        private readonly TronGridClient $client,
        private readonly DepositConfirmationService $confirmationService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * @return array{detected:int, credited:int, head:int, wallets:int}
     */
    public function scan(): array
    {
        $head = $this->client->blockNumber();
        $contract = (string) config('tron.usdt_contract');
        $decimals = (int) config('tron.usdt_decimals');
        $limit = (int) config('tron.scan_limit', 200);
        $asset = (string) config('networks.networks.TRC20.asset', 'USDT');

        $wallets = WalletAddress::query()
            ->where('network', self::NETWORK)
            ->where('is_active', true)
            ->get(['id', 'user_id', 'address']);

        $detected = 0;

        foreach ($wallets as $wallet) {
            $detected += $this->scanWallet($wallet, $contract, $decimals, $limit, $asset, $head);
        }

        $credited = $this->confirmationService->creditConfirmed(
            self::NETWORK,
            $head,
            (int) config('tron.confirmations'),
        );

        return [
            'detected' => $detected,
            'credited' => $credited,
            'head' => $head,
            'wallets' => $wallets->count(),
        ];
    }

    private function scanWallet(
        WalletAddress $wallet,
        string $contract,
        int $decimals,
        int $limit,
        string $asset,
        int $head,
    ): int {
        $knownHashes = Deposit::query()
            ->where('wallet_address_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('tx_hash')
            ->flip();

        $fingerprint = null;
        $detected = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            [$transfers, $nextFingerprint] = $this->client->incomingTrc20TransfersPage(
                $wallet->address,
                $contract,
                $limit,
                $fingerprint,
            );

            if ($transfers === []) {
                break;
            }

            $hitKnown = false;

            foreach ($transfers as $transfer) {
                if ((string) ($transfer['type'] ?? 'Transfer') !== 'Transfer') {
                    continue;
                }

                $txid = (string) ($transfer['transaction_id'] ?? '');
                $amountRaw = (string) ($transfer['value'] ?? '0');

                if ($txid === '' || $amountRaw === '0' || $amountRaw === '') {
                    continue;
                }

                if ($knownHashes->has($txid)) {
                    $hitKnown = true;

                    continue;
                }

                // Guard against a different token reusing the endpoint shape.
                $tokenContract = (string) ($transfer['token_info']['address'] ?? $contract);

                if ($tokenContract !== $contract) {
                    continue;
                }

                $amount = bcdiv($amountRaw, bcpow('10', (string) $decimals, 0), 18);

                $deposit = Deposit::query()->firstOrCreate(
                    [
                        'network' => self::NETWORK,
                        'tx_hash' => $txid,
                        'log_index' => 0,
                    ],
                    [
                        'user_id' => $wallet->user_id,
                        'wallet_address_id' => $wallet->id,
                        'asset' => $asset,
                        'from_address' => (string) ($transfer['from'] ?? ''),
                        'to_address' => $wallet->address,
                        'amount' => $amount,
                        'amount_raw' => $amountRaw,
                        'block_number' => $this->resolveBlockNumber($txid, $head),
                        'status' => 'detected',
                        'detected_at' => now(),
                    ],
                );

                if ($deposit->wasRecentlyCreated) {
                    $detected++;
                    $knownHashes[$txid] = true;

                    $this->notifier->notifyKey(
                        $deposit->user,
                        'deposit_detected',
                        [
                            'amount' => $amount,
                            'asset' => $deposit->asset,
                            'network' => 'TRC20',
                        ],
                    );
                }
            }

            if ($hitKnown || $nextFingerprint === null) {
                break;
            }

            $fingerprint = $nextFingerprint;
        }

        return $detected;
    }

    private function resolveBlockNumber(string $txid, int $head): int
    {
        try {
            $info = $this->client->getTransactionInfoById($txid);
        } catch (Throwable) {
            $info = null;
        }

        $block = (int) ($info['blockNumber'] ?? 0);

        return $block > 0 ? $block : $head;
    }
}
