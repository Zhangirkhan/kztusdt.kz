<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deposit;
use App\Models\IndexerState;
use App\Models\WalletAddress;

final class DepositIndexerService
{
    public function __construct(
        private readonly BscRpcClient $rpc,
        private readonly DepositConfirmationService $confirmationService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * Run one indexing pass: scan new blocks for deposits, then credit confirmed ones.
     *
     * @return array{scanned_from:int, scanned_to:int, detected:int, credited:int, head:int}
     */
    public function scan(): array
    {
        $network = (string) config('wallet.network');
        $head = $this->rpc->blockNumber();

        $state = IndexerState::query()->firstOrCreate(
            ['network' => $network],
            ['last_scanned_block' => $this->initialBlock($head)],
        );

        $from = $state->last_scanned_block + 1;
        $to = min($head, $from + (int) config('bsc.scan_batch') - 1);

        $detected = 0;

        if ($from <= $to) {
            $detected = $this->scanRange($network, $from, $to);
            $state->update(['last_scanned_block' => $to]);
        }

        $credited = $this->confirmationService->creditConfirmed(
            $network,
            $head,
            (int) config('bsc.confirmations'),
        );

        return [
            'scanned_from' => $from,
            'scanned_to' => $to,
            'detected' => $detected,
            'credited' => $credited,
            'head' => $head,
        ];
    }

    private function initialBlock(int $head): int
    {
        $start = (int) config('bsc.start_block');

        return $start > 0 ? $start - 1 : max(0, $head - 1);
    }

    private function scanRange(string $network, int $from, int $to): int
    {
        $wallets = WalletAddress::query()
            ->where('network', $network)
            ->where('is_active', true)
            ->get(['id', 'user_id', 'address']);

        if ($wallets->isEmpty()) {
            return 0;
        }

        $byAddress = $wallets->keyBy(fn (WalletAddress $w) => strtolower($w->address));

        $logs = $this->rpc->getLogs(
            $from,
            $to,
            (string) config('bsc.usdt_contract'),
            [
                (string) config('bsc.transfer_topic'),
                null,
                $wallets->map(fn (WalletAddress $w) => $this->addressTopic($w->address))->values()->all(),
            ],
        );

        $detected = 0;

        foreach ($logs as $log) {
            $toAddress = $this->topicToAddress($log['topics'][2] ?? '');
            $wallet = $byAddress->get(strtolower($toAddress));

            if ($wallet === null) {
                continue;
            }

            $amountRaw = $this->hexToDecimal((string) ($log['data'] ?? '0x0'));

            if ($amountRaw === '0') {
                continue;
            }

            $amount = bcdiv($amountRaw, bcpow('10', (string) config('bsc.usdt_decimals'), 0), 18);
            $blockNumber = (int) hexdec((string) $log['blockNumber']);

            $deposit = Deposit::query()->firstOrCreate(
                [
                    'network' => $network,
                    'tx_hash' => $log['transactionHash'],
                    'log_index' => (int) hexdec((string) $log['logIndex']),
                ],
                [
                    'user_id' => $wallet->user_id,
                    'wallet_address_id' => $wallet->id,
                    'asset' => (string) config('wallet.asset'),
                    'from_address' => $this->topicToAddress($log['topics'][1] ?? ''),
                    'to_address' => $toAddress,
                    'amount' => $amount,
                    'amount_raw' => $amountRaw,
                    'block_number' => $blockNumber,
                    'status' => 'detected',
                    'detected_at' => now(),
                ],
            );

            if ($deposit->wasRecentlyCreated) {
                $detected++;

                $this->notifier->notifyKey(
                    $deposit->user,
                    'deposit_detected',
                    [
                        'amount' => $amount,
                        'asset' => $deposit->asset,
                        'network' => $network,
                    ],
                );
            }
        }

        return $detected;
    }

    private function addressTopic(string $address): string
    {
        return '0x'.str_pad(strtolower(ltrim($address, '0x')), 64, '0', STR_PAD_LEFT);
    }

    private function topicToAddress(string $topic): string
    {
        return '0x'.substr($topic, -40);
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim($hex, '0x');

        if ($hex === '') {
            return '0';
        }

        return gmp_strval(gmp_init($hex, 16), 10);
    }
}
