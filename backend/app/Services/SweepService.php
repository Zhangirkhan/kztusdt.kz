<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deposit;
use App\Models\Sweep;
use App\Models\WalletAddress;
use App\Support\AppLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects ("sweeps") confirmed BEP20 deposits from per-user deposit addresses
 * into the platform hot wallet. A gas worker first tops up the deposit address
 * with BNB so it can pay for the token transfer.
 *
 * State machine: pending -> waiting_gas -> gas_sent -> sweeping -> swept
 *                                     \-> manual_review / failed
 */
final class SweepService
{
    public function __construct(
        private readonly BscRpcClient $rpc,
        private readonly WalletService $walletService,
        private readonly EthereumTxService $txService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Run one full sweep pass.
     *
     * @return array{enabled:bool, queued:int, processed:int, swept:int}
     */
    public function run(): array
    {
        if (! (bool) config('sweep.enabled')) {
            return ['enabled' => false, 'queued' => 0, 'processed' => 0, 'swept' => 0];
        }

        $queued = $this->enqueueCreditedDeposits();

        $sweeps = Sweep::query()
            ->where('network', (string) config('wallet.network'))
            ->whereIn('status', [
                Sweep::STATUS_WAITING_GAS,
                Sweep::STATUS_GAS_SENT,
                Sweep::STATUS_SWEEPING,
            ])
            ->orderBy('id')
            ->get();

        $swept = 0;

        foreach ($sweeps as $sweep) {
            try {
                $advanced = $this->advanceLocked($sweep->id);

                if ($advanced !== null && $advanced->status === Sweep::STATUS_SWEPT) {
                    $swept++;
                }
            } catch (Throwable $e) {
                $this->fail($sweep, $e->getMessage());
            }
        }

        return [
            'enabled' => true,
            'queued' => $queued,
            'processed' => $sweeps->count(),
            'swept' => $swept,
        ];
    }

    /** Create sweep rows for credited deposits that don't have one yet. */
    private function enqueueCreditedDeposits(): int
    {
        $hotWallet = $this->walletService->systemAddress((string) config('sweep.hot_wallet_path'));
        $minAmount = (string) config('sweep.min_sweep_amount', '0');

        $deposits = Deposit::query()
            ->where('network', (string) config('wallet.network'))
            ->where('status', 'credited')
            ->whereDoesntHave('sweepRelation')
            ->with('walletAddress')
            ->get();

        $queued = 0;

        /** @var Deposit $deposit */
        foreach ($deposits as $deposit) {
            if (bccomp((string) $deposit->amount, $minAmount, 18) < 0) {
                continue;
            }

            Sweep::query()->create([
                'deposit_id' => $deposit->id,
                'user_id' => $deposit->user_id,
                'wallet_address_id' => $deposit->wallet_address_id,
                'network' => $deposit->network,
                'asset' => $deposit->asset,
                'from_address' => $deposit->to_address,
                'to_address' => $hotWallet,
                'amount' => $deposit->amount,
                'amount_raw' => $deposit->amount_raw,
                'status' => Sweep::STATUS_WAITING_GAS,
            ]);

            $queued++;
        }

        return $queued;
    }

    /**
     * Advance a single sweep with the row locked for the whole transition, so a
     * parallel pass (scheduler vs. the inline run during withdrawals) can never
     * double-send gas or duplicate a sweep broadcast for the same deposit address.
     */
    private function advanceLocked(int $sweepId): ?Sweep
    {
        return DB::transaction(function () use ($sweepId): ?Sweep {
            $sweep = Sweep::query()->whereKey($sweepId)->lockForUpdate()->first();

            if ($sweep === null || ! in_array($sweep->status, [
                Sweep::STATUS_WAITING_GAS,
                Sweep::STATUS_GAS_SENT,
                Sweep::STATUS_SWEEPING,
            ], true)) {
                return null;
            }

            $this->advance($sweep);

            return $sweep;
        });
    }

    private function advance(Sweep $sweep): void
    {
        match ($sweep->status) {
            Sweep::STATUS_WAITING_GAS => $this->handleGas($sweep),
            Sweep::STATUS_GAS_SENT => $this->handleSweep($sweep),
            Sweep::STATUS_SWEEPING => $this->handleConfirm($sweep),
            default => null,
        };
    }

    private function handleGas(Sweep $sweep): void
    {
        $contract = (string) config('bsc.usdt_contract');
        $tokenBalance = $this->rpc->tokenBalanceOf($contract, $sweep->from_address);

        // Tokens already gone (manually moved or double-processed) — needs a human.
        if (bccomp($tokenBalance, $sweep->amount_raw, 0) < 0) {
            $this->review($sweep, "Token balance {$tokenBalance} < expected {$sweep->amount_raw}");

            return;
        }

        $gasNeeded = $this->gasNeeded();
        $bnbBalance = $this->rpc->getBalance($sweep->from_address);

        if (bccomp($bnbBalance, $gasNeeded, 0) >= 0) {
            $sweep->update(['status' => Sweep::STATUS_GAS_SENT, 'gas_sent_at' => now()]);

            return;
        }

        $topup = (string) config('sweep.gas_topup_wei');
        $gasWalletPath = (string) config('sweep.gas_wallet_path');
        $gasWalletAddress = $this->walletService->systemAddress($gasWalletPath);
        $gasWalletKey = $this->walletService->systemPrivateKey($gasWalletPath);

        $hash = $this->txService->sendBnb($gasWalletKey, $gasWalletAddress, $sweep->from_address, $topup);

        $sweep->update([
            'status' => Sweep::STATUS_GAS_SENT,
            'gas_tx_hash' => $hash,
            'gas_sent_at' => now(),
            'attempts' => $sweep->attempts + 1,
        ]);

        $this->auditLogService->log('sweep.gas_sent', null, 'sweep', $sweep->id, [
            'to' => $sweep->from_address,
            'tx' => $hash,
        ]);
    }

    private function handleSweep(Sweep $sweep): void
    {
        // If a gas top-up tx was sent, make sure it landed before spending it.
        if ($sweep->gas_tx_hash !== null && ! $this->isMined($sweep->gas_tx_hash)) {
            return;
        }

        $gasNeeded = $this->gasNeeded();
        $bnbBalance = $this->rpc->getBalance($sweep->from_address);

        if (bccomp($bnbBalance, $gasNeeded, 0) < 0) {
            // Gas not yet available; retry on next pass.
            return;
        }

        $key = $this->walletService->derivePrivateKey($this->derivationIndex($sweep));

        $hash = $this->txService->sendToken(
            $key,
            $sweep->from_address,
            (string) config('bsc.usdt_contract'),
            $sweep->to_address,
            $sweep->amount_raw,
        );

        $sweep->update([
            'status' => Sweep::STATUS_SWEEPING,
            'sweep_tx_hash' => $hash,
            'attempts' => $sweep->attempts + 1,
        ]);

        $this->auditLogService->log('sweep.broadcast', null, 'sweep', $sweep->id, [
            'from' => $sweep->from_address,
            'to' => $sweep->to_address,
            'amount' => $sweep->amount,
            'tx' => $hash,
        ]);
    }

    private function handleConfirm(Sweep $sweep): void
    {
        $receipt = $this->rpc->getTransactionReceipt((string) $sweep->sweep_tx_hash);

        if ($receipt === null) {
            return; // still pending
        }

        $status = strtolower((string) ($receipt['status'] ?? ''));

        if ($status === '0x1') {
            $sweep->update(['status' => Sweep::STATUS_SWEPT, 'swept_at' => now()]);

            $this->auditLogService->log('sweep.completed', null, 'sweep', $sweep->id, [
                'tx' => $sweep->sweep_tx_hash,
            ]);

            AppLog::info('sweep.completed', [
                'sweep_id' => $sweep->id,
                'network' => $sweep->network,
                'tx' => $sweep->sweep_tx_hash,
            ]);

            return;
        }

        $this->review($sweep, "Sweep tx reverted (status {$status})");
    }

    private function isMined(string $txHash): bool
    {
        $receipt = $this->rpc->getTransactionReceipt($txHash);

        return $receipt !== null && strtolower((string) ($receipt['status'] ?? '')) === '0x1';
    }

    private function gasNeeded(): string
    {
        $gasPrice = $this->rpc->gasPrice();
        $gasLimit = (string) (int) config('sweep.transfer_gas_limit', 100000);

        return bcmul($gasPrice, $gasLimit, 0);
    }

    private function derivationIndex(Sweep $sweep): int
    {
        /** @var WalletAddress $wallet */
        $wallet = $sweep->walletAddress()->firstOrFail();

        return (int) $wallet->derivation_index;
    }

    private function fail(Sweep $sweep, string $error): void
    {
        $attempts = $sweep->attempts + 1;
        $status = $attempts >= (int) config('sweep.max_attempts', 5)
            ? Sweep::STATUS_MANUAL_REVIEW
            : $sweep->status;

        $sweep->update([
            'attempts' => $attempts,
            'last_error' => $error,
            'status' => $status,
        ]);

        AppLog::warning('sweep.error', [
            'sweep_id' => $sweep->id,
            'network' => $sweep->network,
            'error' => $error,
        ]);
    }

    private function review(Sweep $sweep, string $reason): void
    {
        $sweep->update([
            'status' => Sweep::STATUS_MANUAL_REVIEW,
            'last_error' => $reason,
        ]);

        $this->auditLogService->log('sweep.manual_review', null, 'sweep', $sweep->id, [
            'reason' => $reason,
        ]);
    }
}
