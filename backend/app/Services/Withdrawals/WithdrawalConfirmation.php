<?php

declare(strict_types=1);

namespace App\Services\Withdrawals;

/**
 * Outcome of checking a broadcast withdrawal on-chain.
 *
 *  - pending  — not yet mined; check again later.
 *  - success  — mined successfully; settle the ledger.
 *  - reverted — mined but failed; funds stay locked for a human decision.
 */
final class WithdrawalConfirmation
{
    public const PENDING = 'pending';

    public const SUCCESS = 'success';

    public const REVERTED = 'reverted';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        public readonly array $payload = [],
    ) {}

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function success(): self
    {
        return new self(self::SUCCESS);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function reverted(string $reason, array $payload = []): self
    {
        return new self(self::REVERTED, $reason, $payload);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isReverted(): bool
    {
        return $this->status === self::REVERTED;
    }
}
