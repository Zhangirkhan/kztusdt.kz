<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Withdrawal;
use App\Services\Withdrawals\PreparedWithdrawal;
use App\Services\Withdrawals\WithdrawalBroadcaster;
use App\Services\Withdrawals\WithdrawalConfirmation;
use Throwable;

/**
 * Test double for {@see WithdrawalBroadcaster}. Lets feature tests drive the
 * withdrawal state machine deterministically — without any real RPC — by scripting
 * the prepare / send / confirm outcomes.
 */
final class FakeWithdrawalBroadcaster implements WithdrawalBroadcaster
{
    public int $prepareCalls = 0;

    public int $sendCalls = 0;

    public int $confirmCalls = 0;

    public ?Throwable $prepareException = null;

    public ?Throwable $sendException = null;

    public string $sendHash = '0xfeedface';

    private WithdrawalConfirmation $confirmResult;

    public function __construct(
        private readonly string $network = 'BEP20',
    ) {
        $this->confirmResult = WithdrawalConfirmation::pending();
    }

    public function willConfirm(WithdrawalConfirmation $confirmation): self
    {
        $this->confirmResult = $confirmation;

        return $this;
    }

    public function network(): string
    {
        return $this->network;
    }

    public function prepare(Withdrawal $withdrawal): PreparedWithdrawal
    {
        $this->prepareCalls++;

        if ($this->prepareException !== null) {
            throw $this->prepareException;
        }

        return new PreparedWithdrawal(
            withdrawal: $withdrawal,
            signerKey: 'fake-key',
            signerAddress: 'fake-address',
            signerSource: 'hot_wallet',
            amountRaw: '1000000000000000000',
        );
    }

    public function send(PreparedWithdrawal $prepared): string
    {
        $this->sendCalls++;

        if ($this->sendException !== null) {
            throw $this->sendException;
        }

        return $this->sendHash;
    }

    public function confirm(Withdrawal $withdrawal): WithdrawalConfirmation
    {
        $this->confirmCalls++;

        return $this->confirmResult;
    }

    public function humanizeError(string $error): string
    {
        return $error;
    }
}
