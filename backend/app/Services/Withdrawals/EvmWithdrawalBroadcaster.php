<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

use App\Exceptions\WithdrawalRetryLaterException;
use App\Models\WalletAddress;
use App\Models\Withdrawal;
use App\Services\AuditLogService;
use App\Services\BscRpcClient;
use App\Services\EthereumTxService;
use App\Services\SweepService;
use App\Services\WalletService;
use RuntimeException;

/**
 * EVM (BEP20 / BSC USDT) withdrawal broadcaster.
 *
 * Picks a signer holding enough USDT (hot wallet, else the user's deposit address),
 * tops it up with BNB for gas, broadcasts the ERC20 transfer and confirms it.
 */
final class EvmWithdrawalBroadcaster implements WithdrawalBroadcaster
{
    public function __construct(
        private readonly BscRpcClient $rpc,
        private readonly EthereumTxService $txService,
        private readonly WalletService $walletService,
        private readonly AuditLogService $auditLogService,
        private readonly SweepService $sweepService,
    ) {}

    public function network(): string
    {
        return (string) config('wallet.network');
    }

    public function prepare(Withdrawal $withdrawal): PreparedWithdrawal
    {
        $signer = $this->resolveSigner($withdrawal);

        $decimals = (int) config('bsc.usdt_decimals', 18);
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
        return $this->txService->sendToken(
            $prepared->signerKey,
            $prepared->signerAddress,
            (string) config('bsc.usdt_contract'),
            $prepared->withdrawal->to_address,
            $prepared->amountRaw,
        );
    }

    public function confirm(Withdrawal $withdrawal): WithdrawalConfirmation
    {
        $receipt = $this->rpc->getTransactionReceipt((string) $withdrawal->tx_hash);

        if ($receipt === null) {
            return WithdrawalConfirmation::pending();
        }

        $status = strtolower((string) ($receipt['status'] ?? ''));

        if ($status === '0x1') {
            return WithdrawalConfirmation::success();
        }

        return WithdrawalConfirmation::reverted("Tx reverted (status {$status})", ['receipt_status' => $status]);
    }

    public function humanizeError(string $error): string
    {
        if (str_contains($error, 'insufficient funds for gas')) {
            $hotAddress = $this->walletService->systemAddress((string) config('sweep.hot_wallet_path'));
            $gasAddress = $this->walletService->systemAddress((string) config('sweep.gas_wallet_path'));

            return "На hot wallet нет BNB для газа. Пополните gas wallet {$gasAddress} — "
                ."газ будет автоматически переведён на hot wallet {$hotAddress}.";
        }

        return $error;
    }

    /**
     * @return array{key: string, address: string, source: string}
     */
    private function resolveSigner(Withdrawal $withdrawal): array
    {
        if ((bool) config('sweep.enabled')) {
            $this->sweepService->run();
        }

        $contract = (string) config('bsc.usdt_contract');
        $decimals = (string) config('bsc.usdt_decimals', 18);
        $amountRaw = bcmul((string) $withdrawal->amount, bcpow('10', $decimals, 0), 0);

        $hotPath = (string) config('sweep.hot_wallet_path');
        $hotAddress = $this->walletService->systemAddress($hotPath);
        $hotUsdtRaw = $this->rpc->tokenBalanceOf($contract, $hotAddress);

        if (bccomp($hotUsdtRaw, $amountRaw, 0) >= 0) {
            $this->ensureAddressGas($withdrawal, $hotAddress, 'hot wallet');

            return [
                'key' => $this->walletService->systemPrivateKey($hotPath),
                'address' => $hotAddress,
                'source' => 'hot_wallet',
            ];
        }

        $depositWallet = WalletAddress::query()
            ->where('user_id', $withdrawal->user_id)
            ->where('network', (string) config('wallet.network'))
            ->where('asset', (string) config('wallet.asset'))
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($depositWallet !== null) {
            $depositUsdtRaw = $this->rpc->tokenBalanceOf($contract, $depositWallet->address);

            if (bccomp($depositUsdtRaw, $amountRaw, 0) >= 0) {
                $this->ensureAddressGas($withdrawal, $depositWallet->address, 'депозитный адрес');

                return [
                    'key' => $this->walletService->derivePrivateKey((int) $depositWallet->derivation_index),
                    'address' => $depositWallet->address,
                    'source' => 'deposit_wallet',
                ];
            }
        }

        $gasAddress = $this->walletService->systemAddress((string) config('sweep.gas_wallet_path'));
        $hotAvailable = bcdiv($hotUsdtRaw, bcpow('10', $decimals, 0), 4);
        $depositAvailable = '0';

        if ($depositWallet !== null) {
            $depositAvailable = bcdiv(
                $this->rpc->tokenBalanceOf($contract, $depositWallet->address),
                bcpow('10', $decimals, 0),
                4,
            );
        }

        throw new RuntimeException(
            "Недостаточно USDT для вывода №{$withdrawal->id}. "
            ."Hot wallet {$hotAddress}: {$hotAvailable} USDT. "
            .($depositWallet !== null
                ? "Депозит {$depositWallet->address}: {$depositAvailable} USDT. "
                : '')
            ."Нужно {$withdrawal->amount} USDT. "
            ."Пополните gas wallet {$gasAddress} минимум на 0.002 BNB — система сама соберёт USDT и отправит вывод.",
        );
    }

    private function ensureAddressGas(Withdrawal $withdrawal, string $address, string $label): void
    {
        $gasNeeded = $this->gasNeeded();

        if (bccomp($this->rpc->getBalance($address), $gasNeeded, 0) >= 0) {
            return;
        }

        $gasPath = (string) config('sweep.gas_wallet_path');
        $gasAddress = $this->walletService->systemAddress($gasPath);
        $gasWalletBnb = $this->rpc->getBalance($gasAddress);
        $topup = (string) config('sweep.gas_topup_wei');
        $bnbTransferCost = bcmul(
            $this->rpc->gasPrice(),
            (string) (int) config('sweep.bnb_transfer_gas_limit', 21000),
            0,
        );
        $gasWalletMinimum = bcadd($topup, $bnbTransferCost, 0);

        if (bccomp($gasWalletBnb, $gasWalletMinimum, 0) < 0) {
            throw new RuntimeException(
                "На {$label} ({$address}) нет BNB для газа. "
                ."Пополните gas wallet {$gasAddress} минимум на 0.002 BNB.",
            );
        }

        $gasKey = $this->walletService->systemPrivateKey($gasPath);
        $hash = $this->txService->sendBnb($gasKey, $gasAddress, $address, $topup);

        $this->auditLogService->log(
            action: 'withdrawal.gas_topup',
            userId: null,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: ['to' => $address, 'tx' => $hash, 'label' => $label],
        );

        throw new WithdrawalRetryLaterException(
            "BNB для газа отправлен на {$label}. Вывод №{$withdrawal->id} повторится автоматически через 1–2 минуты.",
        );
    }

    private function gasNeeded(): string
    {
        $gasPrice = $this->rpc->gasPrice();
        $gasLimit = (string) (int) config('sweep.transfer_gas_limit', 100000);

        return bcmul($gasPrice, $gasLimit, 0);
    }
}
