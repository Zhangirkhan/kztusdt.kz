<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deposit;
use App\Support\AppLog;
use Illuminate\Support\Facades\DB;

/**
 * Credits confirmed deposits for a single network into the internal ledger.
 *
 * Shared by every chain indexer so confirmation accounting stays network-scoped
 * (a BEP20 head never confirms a TRC20 deposit and vice-versa).
 *
 * Each deposit is credited inside its own transaction with the row locked and
 * the status re-checked, so two indexer passes (or an overlap between the BEP20
 * watcher and a manual run) can never double-credit the same deposit.
 */
final class DepositConfirmationService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly UserNotificationService $notifier,
        private readonly DueDiligenceService $dueDiligenceService,
    ) {}

    public function creditConfirmed(string $network, int $head, int $required): int
    {
        $pending = Deposit::query()
            ->where('network', $network)
            ->whereIn('status', ['detected', 'confirmed'])
            ->with('user')
            ->get();

        $credited = 0;

        /** @var Deposit $deposit */
        foreach ($pending as $deposit) {
            $confirmations = max(0, $head - $deposit->block_number + 1);

            if ($confirmations < $required) {
                $deposit->update(['confirmations' => $confirmations]);

                continue;
            }

            if ($this->creditOne($deposit, $confirmations)) {
                $credited++;

                $this->notifier->notifyKey(
                    $deposit->user,
                    'deposit_credited',
                    [
                        'amount' => $deposit->amount,
                        'asset' => $deposit->asset,
                        'network' => $network,
                        'tx' => $deposit->tx_hash,
                    ],
                );
            }
        }

        if ($credited > 0) {
            AppLog::info('deposit.credited_batch', [
                'network' => $network,
                'count' => $credited,
                'head' => $head,
            ]);
        }

        return $credited;
    }

    /**
     * Credit a single deposit atomically. Returns true only if this call is the
     * one that actually credited it (idempotent: a re-run on an already-credited
     * deposit is a no-op).
     */
    private function creditOne(Deposit $deposit, int $confirmations): bool
    {
        return (bool) DB::transaction(function () use ($deposit, $confirmations): bool {
            $locked = Deposit::query()->whereKey($deposit->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status === 'credited') {
                return false;
            }

            $this->ledgerService->creditDeposit(
                userId: $locked->user_id,
                asset: $locked->asset,
                amount: (string) $locked->amount,
                refType: 'deposit',
                refId: $locked->id,
                memo: $locked->tx_hash,
            );

            $locked->update([
                'status' => 'credited',
                'confirmations' => $confirmations,
                'confirmed_at' => $locked->confirmed_at ?? now(),
                'credited_at' => now(),
            ]);

            if ($this->dueDiligenceService->exceedsThreshold((string) $locked->amount)) {
                $this->dueDiligenceService->markRequired($locked->user);
            }

            return true;
        });
    }
}
