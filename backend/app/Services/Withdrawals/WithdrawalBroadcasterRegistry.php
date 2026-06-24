<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

/**
 * Resolves the {@see WithdrawalBroadcaster} for a given network, falling back to a
 * default (EVM) broadcaster for any network without a dedicated implementation —
 * preserving the historical "TRC20 → Tron, everything else → EVM" routing.
 */
final class WithdrawalBroadcasterRegistry
{
    /**
     * @param  array<string, WithdrawalBroadcaster>  $byNetwork
     */
    public function __construct(
        private readonly array $byNetwork,
        private readonly WithdrawalBroadcaster $default,
    ) {}

    public function for(string $network): WithdrawalBroadcaster
    {
        return $this->byNetwork[$network] ?? $this->default;
    }
}
