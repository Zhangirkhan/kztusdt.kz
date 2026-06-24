<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

use App\Models\Withdrawal;

/**
 * Result of the pre-broadcast preparation step: a resolved, gas-funded signer
 * plus the raw on-chain amount. Produced by {@see WithdrawalBroadcaster::prepare()}
 * and consumed by {@see WithdrawalBroadcaster::send()}.
 *
 * Keeping prepare/send separate is what makes the broadcast safe: failures during
 * prepare are pre-broadcast (no on-chain tx) and safe to retry, while a failure in
 * send is ambiguous and must never auto-retry.
 */
final class PreparedWithdrawal
{
    public function __construct(
        public readonly Withdrawal $withdrawal,
        public readonly string $signerKey,
        public readonly string $signerAddress,
        public readonly string $signerSource,
        public readonly string $amountRaw,
    ) {}
}
