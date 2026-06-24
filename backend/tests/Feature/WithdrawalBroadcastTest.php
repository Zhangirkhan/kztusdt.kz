<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\WithdrawalRetryLaterException;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use App\Services\Withdrawals\WithdrawalBroadcasterRegistry;
use App\Services\Withdrawals\WithdrawalConfirmation;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\Support\FakeWithdrawalBroadcaster;
use Tests\TestCase;

/**
 * Broadcast state-machine coverage that runs the real {@see WithdrawalService}
 * orchestration against a scripted {@see FakeWithdrawalBroadcaster} (no real RPC).
 *
 * The point of the refactor is exactly this seam: the safety properties of the
 * money path are now testable without mocking final RPC clients.
 */
final class WithdrawalBroadcastTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_ADDRESS = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';

    private FakeWithdrawalBroadcaster $broadcaster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeExternalApis();
        config(['withdrawal.enabled' => true]);

        $this->broadcaster = new FakeWithdrawalBroadcaster('BEP20');
        $this->app->instance(
            WithdrawalBroadcasterRegistry::class,
            new WithdrawalBroadcasterRegistry(['BEP20' => $this->broadcaster], $this->broadcaster),
        );
    }

    public function test_successful_broadcast_settles_the_withdrawal(): void
    {
        [$user, $withdrawal] = $this->approvedWithdrawal('200', 100);
        $this->broadcaster->willConfirm(WithdrawalConfirmation::success());

        $result = app(WithdrawalService::class)->processQueue();

        $this->assertSame(1, $result['broadcast']);
        $this->assertSame(1, $result['completed']);

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_COMPLETED, $withdrawal->status);
        $this->assertSame('0xfeedface', $withdrawal->tx_hash);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('99.49', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_pre_broadcast_gas_topup_keeps_row_approved_for_retry(): void
    {
        [$user, $withdrawal] = $this->approvedWithdrawal('200', 100);
        $this->broadcaster->prepareException = new WithdrawalRetryLaterException('Газ отправлен, повтор через минуту.');

        app(WithdrawalService::class)->processQueue();

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_APPROVED, $withdrawal->status);
        $this->assertStringContainsString('Газ отправлен', (string) $withdrawal->last_error);
        $this->assertSame(0, $this->broadcaster->sendCalls);

        // Funds stay locked while waiting for the next pass.
        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_pre_broadcast_failure_fails_after_max_attempts_and_keeps_funds_locked(): void
    {
        config(['withdrawal.max_attempts' => 1]);
        [$user, $withdrawal] = $this->approvedWithdrawal('200', 100);
        $this->broadcaster->prepareException = new RuntimeException('Недостаточно USDT.');

        $result = app(WithdrawalService::class)->processQueue();

        $this->assertSame(1, $result['failed']);

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_FAILED, $withdrawal->status);
        $this->assertNull($withdrawal->tx_hash);
        $this->assertSame(0, $this->broadcaster->sendCalls);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_interrupted_broadcast_goes_to_needs_reconcile_and_is_not_retried(): void
    {
        [$user, $withdrawal] = $this->approvedWithdrawal('200', 100);
        $this->broadcaster->sendException = new RuntimeException('RPC timeout mid-broadcast');

        app(WithdrawalService::class)->processQueue();

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_NEEDS_RECONCILE, $withdrawal->status);
        $this->assertNull($withdrawal->tx_hash);
        $this->assertSame(1, $this->broadcaster->sendCalls);
        $this->assertTrue($withdrawal->isFinal());

        // Funds remain locked — a human must reconcile before any retry.
        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));

        // A second pass must NOT re-broadcast (no double-send).
        app(WithdrawalService::class)->processQueue();
        $this->assertSame(1, $this->broadcaster->sendCalls);
    }

    public function test_reverted_transaction_marks_withdrawal_failed_with_funds_locked(): void
    {
        [$user, $withdrawal] = $this->approvedWithdrawal('200', 100);
        $this->broadcaster->willConfirm(WithdrawalConfirmation::reverted('Tx reverted (status 0x0)'));

        app(WithdrawalService::class)->processQueue();

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_FAILED, $withdrawal->status);
        $this->assertSame('0xfeedface', $withdrawal->tx_hash);
        $this->assertStringContainsString('reverted', (string) $withdrawal->last_error);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    /**
     * @return array{0: User, 1: Withdrawal}
     */
    private function approvedWithdrawal(string $balance, float $amount): array
    {
        $user = $this->createClient();
        $this->giveBalance($user, $balance);

        $withdrawal = app(WithdrawalService::class)->create($user, self::VALID_ADDRESS, (string) $amount);
        $withdrawal->update(['status' => Withdrawal::STATUS_APPROVED, 'approved_at' => now()]);

        return [$user, $withdrawal->fresh()];
    }
}
