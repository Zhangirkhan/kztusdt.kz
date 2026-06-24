<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

use App\Exceptions\WithdrawalRetryLaterException;
use App\Models\Withdrawal;
use RuntimeException;

/**
 * Per-network on-chain broadcasting strategy for withdrawals.
 *
 * Implementations own everything chain-specific (signer selection, gas top-ups,
 * transaction building/broadcasting, receipt confirmation). WithdrawalService
 * stays chain-agnostic and only orchestrates state transitions and the ledger.
 */
interface WithdrawalBroadcaster
{
    /**
     * Network code this broadcaster handles (e.g. BEP20, TRC20).
     */
    public function network(): string;

    /**
     * Resolve a funded signer for the withdrawal. Runs BEFORE the row is claimed:
     * a thrown {@see RuntimeException} (insufficient funds) is safe to retry, and a
     * {@see WithdrawalRetryLaterException} means gas was just topped up — retry soon.
     *
     * @throws RuntimeException
     * @throws WithdrawalRetryLaterException
     */
    public function prepare(Withdrawal $withdrawal): PreparedWithdrawal;

    /**
     * Broadcast the prepared transaction and return its hash. A thrown exception is
     * ambiguous (the tx may already be on-chain) — the caller routes the row to
     * needs_reconcile and never auto-retries.
     */
    public function send(PreparedWithdrawal $prepared): string;

    /**
     * Check the broadcast transaction on-chain.
     */
    public function confirm(Withdrawal $withdrawal): WithdrawalConfirmation;

    /**
     * Turn a raw broadcast error into an operator-friendly message (no-op by default).
     */
    public function humanizeError(string $error): string;
}
