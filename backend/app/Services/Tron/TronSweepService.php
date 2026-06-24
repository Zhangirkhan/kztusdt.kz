<?php

declare(strict_types=1);

namespace App\Services\Tron;

use App\Models\Deposit;
use App\Models\Sweep;
use App\Models\WalletAddress;
use App\Services\AuditLogService;
use App\Services\WalletService;
use App\Support\AppLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Collects ("sweeps") confirmed TRC20 deposits from per-user deposit addresses
 * into the platform TRON hot wallet. A gas worker first tops up the deposit
 * address with TRX so it can pay the transfer's energy/bandwidth cost.
 *
 * State machine mirrors the EVM sweeper:
 *   waiting_gas -> gas_sent -> sweeping -> swept  (\-> manual_review / failed)
 */
final class TronSweepService
{
    private const NETWORK = 'TRC20';

    public function __construct(
        private readonly TronGridClient $client,
        private readonly TronTxService $txService,
        private readonly WalletService $walletService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{enabled:bool, queued:int, processed:int, swept:int}
     */
    public function run(): array
    {
        if (! (bool) config('tron.sweep_enabled')) {
            return ['enabled' => false, 'queued' => 0, 'processed' => 0, 'swept' => 0];
        }

        $queued = $this->enqueueCreditedDeposits();

        $sweeps = Sweep::query()
            ->where('network', self::NETWORK)
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

    private function enqueueCreditedDeposits(): int
    {
        $hotWallet = $this->walletService->systemAddress(
            (string) config('tron.hot_wallet_path'),
            self::NETWORK,
        );
        $minAmount = (string) config('tron.min_sweep_amount', '0');

        $deposits = Deposit::query()
            ->where('network', self::NETWORK)
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
     * double-send TRX gas or duplicate a sweep broadcast for the same address.
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
        $contract = (string) config('tron.usdt_contract');
        $tokenBalance = $this->client->trc20BalanceOf($contract, $sweep->from_address);

        if (bccomp($tokenBalance, $sweep->amount_raw, 0) < 0) {
            $this->review($sweep, "Token balance {$tokenBalance} < expected {$sweep->amount_raw}");

            return;
        }

        $topup = (string) config('tron.gas_topup_sun');

        if (bccomp($this->client->getBalanceSun($sweep->from_address), $topup, 0) >= 0) {
            $sweep->update(['status' => Sweep::STATUS_GAS_SENT, 'gas_sent_at' => now()]);

            return;
        }

        $gasPath = (string) config('tron.gas_wallet_path');
        $gasAddress = $this->walletService->systemAddress($gasPath, self::NETWORK);
        $gasKey = $this->walletService->systemPrivateKey($gasPath);

        $hash = $this->txService->sendTrx($gasKey, $gasAddress, $sweep->from_address, $topup);

        $sweep->update([
            'status' => Sweep::STATUS_GAS_SENT,
            'gas_tx_hash' => $hash,
            'gas_sent_at' => now(),
            'attempts' => $sweep->attempts + 1,
        ]);

        $this->auditLogService->log('sweep.gas_sent', null, 'sweep', $sweep->id, [
            'to' => $sweep->from_address,
            'tx' => $hash,
            'network' => self::NETWORK,
        ]);
    }

    private function handleSweep(Sweep $sweep): void
    {
        if ($sweep->gas_tx_hash !== null && ! $this->isMined($sweep->gas_tx_hash)) {
            return;
        }

        $topup = (string) config('tron.gas_topup_sun');

        if (bccomp($this->client->getBalanceSun($sweep->from_address), $topup, 0) < 0) {
            return; // gas not yet landed; retry next pass
        }

        $key = $this->walletService->derivePrivateKey($this->derivationIndex($sweep), self::NETWORK);

        $hash = $this->txService->sendToken(
            $key,
            $sweep->from_address,
            (string) config('tron.usdt_contract'),
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
            'network' => self::NETWORK,
        ]);
    }

    private function handleConfirm(Sweep $sweep): void
    {
        $info = $this->client->getTransactionInfoById((string) $sweep->sweep_tx_hash);

        if ($info === null) {
            return;
        }

        $result = strtoupper((string) ($info['receipt']['result'] ?? ''));

        if ($result === 'SUCCESS') {
            $sweep->update(['status' => Sweep::STATUS_SWEPT, 'swept_at' => now()]);

            $this->auditLogService->log('sweep.completed', null, 'sweep', $sweep->id, [
                'tx' => $sweep->sweep_tx_hash,
                'network' => self::NETWORK,
            ]);

            AppLog::info('sweep.completed', [
                'sweep_id' => $sweep->id,
                'network' => self::NETWORK,
                'tx' => $sweep->sweep_tx_hash,
            ]);

            return;
        }

        $this->review($sweep, "Sweep tx failed (receipt {$result})");
    }

    private function isMined(string $txid): bool
    {
        $info = $this->client->getTransactionInfoById($txid);

        if ($info === null) {
            return false;
        }

        // Only treat the gas top-up as landed when the receipt is a success,
        // otherwise the sweep could be broadcast before TRX actually arrived.
        return strtoupper((string) ($info['receipt']['result'] ?? '')) === 'SUCCESS';
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
        $status = $attempts >= (int) config('tron.max_attempts', 5)
            ? Sweep::STATUS_MANUAL_REVIEW
            : $sweep->status;

        $sweep->update([
            'attempts' => $attempts,
            'last_error' => $error,
            'status' => $status,
        ]);

        AppLog::warning('sweep.error', [
            'sweep_id' => $sweep->id,
            'network' => self::NETWORK,
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
            'network' => self::NETWORK,
        ]);
    }
}
