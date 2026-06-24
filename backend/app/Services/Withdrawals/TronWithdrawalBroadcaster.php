<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

use App\Exceptions\WithdrawalRetryLaterException;
use App\Models\WalletAddress;
use App\Models\Withdrawal;
use App\Services\AuditLogService;
use App\Services\Tron\TronGridClient;
use App\Services\Tron\TronSweepService;
use App\Services\Tron\TronTxService;
use App\Services\WalletService;
use RuntimeException;

/**
 * TRON (TRC20 USDT) withdrawal broadcaster.
 *
 * Picks a signer holding enough USDT (hot wallet, else the user's deposit address),
 * tops it up with TRX for energy/bandwidth, broadcasts the TRC20 transfer and
 * confirms it via the transaction receipt.
 */
final class TronWithdrawalBroadcaster implements WithdrawalBroadcaster
{
    public function __construct(
        private readonly TronGridClient $tronRpc,
        private readonly TronTxService $tronTxService,
        private readonly WalletService $walletService,
        private readonly AuditLogService $auditLogService,
        private readonly TronSweepService $tronSweepService,
    ) {}

    public function network(): string
    {
        return 'TRC20';
    }

    public function prepare(Withdrawal $withdrawal): PreparedWithdrawal
    {
        $signer = $this->resolveSigner($withdrawal);

        $decimals = (int) config('tron.usdt_decimals', 6);
        $amountRaw = bcmul((string) $withdrawal->amount, bcpow('10', (string) $decimals, 0), 0);

        return new PreparedWithdrawal(
            withdrawal: $withdrawal,
            signerKey: $signer['key'],
            signerAddress: $signer['address'],
            signerSource: $signer['source'],
            amountRaw: $amountRaw,
        );
    }

    public function send(PreparedWithdrawal $prepared): string
    {
        return $this->tronTxService->sendToken(
            $prepared->signerKey,
            $prepared->signerAddress,
            (string) config('tron.usdt_contract'),
            $prepared->withdrawal->to_address,
            $prepared->amountRaw,
        );
    }

    public function confirm(Withdrawal $withdrawal): WithdrawalConfirmation
    {
        $info = $this->tronRpc->getTransactionInfoById((string) $withdrawal->tx_hash);

        if ($info === null) {
            return WithdrawalConfirmation::pending();
        }

        $result = strtoupper((string) ($info['receipt']['result'] ?? ''));

        if ($result === 'SUCCESS') {
            return WithdrawalConfirmation::success();
        }

        return WithdrawalConfirmation::reverted("Tron tx failed (receipt {$result})", ['receipt_result' => $result]);
    }

    public function humanizeError(string $error): string
    {
        return $error;
    }

    /**
     * @return array{key: string, address: string, source: string}
     */
    private function resolveSigner(Withdrawal $withdrawal): array
    {
        if ((bool) config('tron.sweep_enabled')) {
            $this->tronSweepService->run();
        }

        $contract = (string) config('tron.usdt_contract');
        $decimals = (string) config('tron.usdt_decimals', 6);
        $amountRaw = bcmul((string) $withdrawal->amount, bcpow('10', $decimals, 0), 0);

        $hotPath = (string) config('tron.hot_wallet_path');
        $hotAddress = $this->walletService->systemAddress($hotPath, 'TRC20');

        if (bccomp($this->tronRpc->trc20BalanceOf($contract, $hotAddress), $amountRaw, 0) >= 0) {
            $this->ensureGas($withdrawal, $hotAddress, 'hot wallet');

            return [
                'key' => $this->walletService->systemPrivateKey($hotPath),
                'address' => $hotAddress,
                'source' => 'hot_wallet',
            ];
        }

        $depositWallet = WalletAddress::query()
            ->where('user_id', $withdrawal->user_id)
            ->where('network', 'TRC20')
            ->where('asset', $withdrawal->asset)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($depositWallet !== null
            && bccomp($this->tronRpc->trc20BalanceOf($contract, $depositWallet->address), $amountRaw, 0) >= 0
        ) {
            $this->ensureGas($withdrawal, $depositWallet->address, 'депозитный адрес');

            return [
                'key' => $this->walletService->derivePrivateKey((int) $depositWallet->derivation_index, 'TRC20'),
                'address' => $depositWallet->address,
                'source' => 'deposit_wallet',
            ];
        }

        $gasAddress = $this->walletService->systemAddress((string) config('tron.gas_wallet_path'), 'TRC20');
        $hotAvailable = bcdiv($this->tronRpc->trc20BalanceOf($contract, $hotAddress), bcpow('10', $decimals, 0), 4);

        throw new RuntimeException(
            "Недостаточно USDT (TRC20) для вывода №{$withdrawal->id}. "
            ."Hot wallet {$hotAddress}: {$hotAvailable} USDT. Нужно {$withdrawal->amount} USDT. "
            ."Пополните gas wallet {$gasAddress} в TRX — система соберёт USDT и отправит вывод.",
        );
    }

    private function ensureGas(Withdrawal $withdrawal, string $address, string $label): void
    {
        $needed = (string) config('tron.gas_topup_sun');

        if (bccomp($this->tronRpc->getBalanceSun($address), $needed, 0) >= 0) {
            return;
        }

        $gasPath = (string) config('tron.gas_wallet_path');
        $gasAddress = $this->walletService->systemAddress($gasPath, 'TRC20');

        if (bccomp($this->tronRpc->getBalanceSun($gasAddress), $needed, 0) < 0) {
            $trx = bcdiv($needed, '1000000', 6);

            throw new RuntimeException(
                "На {$label} ({$address}) нет TRX для комиссии. "
                ."Пополните gas wallet {$gasAddress} минимум на {$trx} TRX.",
            );
        }

        $gasKey = $this->walletService->systemPrivateKey($gasPath);
        $hash = $this->tronTxService->sendTrx($gasKey, $gasAddress, $address, $needed);

        $this->auditLogService->log(
            action: 'withdrawal.gas_topup',
            userId: null,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: ['to' => $address, 'tx' => $hash, 'label' => $label, 'network' => 'TRC20'],
        );

        throw new WithdrawalRetryLaterException(
            "TRX для комиссии отправлен на {$label}. Вывод №{$withdrawal->id} повторится автоматически через 1–2 минуты.",
        );
    }
}
