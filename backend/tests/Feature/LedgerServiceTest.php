<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Двойная запись (double-entry ledger) — фундамент всех движений средств.
 */
final class LedgerServiceTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private LedgerService $ledger;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(LedgerService::class);
        $this->user = $this->createClient();
    }

    public function test_credit_deposit_increases_available_balance(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '100.5', 'deposit', 1);

        $this->assertBcEquals('100.5', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('0', $this->ledger->lockedBalance($this->user->id, 'USDT'));
        $this->assertLedgerBalanced();
    }

    public function test_lock_moves_funds_from_available_to_locked(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '100', 'deposit', 1);

        $this->ledger->lock($this->user->id, 'USDT', '40', 'withdrawal', 1);

        $this->assertBcEquals('60', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('40', $this->ledger->lockedBalance($this->user->id, 'USDT'));
        $this->assertLedgerBalanced();
    }

    public function test_lock_more_than_available_throws(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '10', 'deposit', 1);

        $this->expectException(InsufficientBalanceException::class);

        $this->ledger->lock($this->user->id, 'USDT', '10.000000000000000001', 'withdrawal', 1);
    }

    public function test_unlock_returns_funds_to_available(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '100', 'deposit', 1);
        $this->ledger->lock($this->user->id, 'USDT', '40', 'withdrawal', 1);

        $this->ledger->unlock($this->user->id, 'USDT', '40', 'withdrawal', 1);

        $this->assertBcEquals('100', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('0', $this->ledger->lockedBalance($this->user->id, 'USDT'));
        $this->assertLedgerBalanced();
    }

    public function test_unlock_more_than_locked_throws(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '100', 'deposit', 1);
        $this->ledger->lock($this->user->id, 'USDT', '40', 'withdrawal', 1);

        $this->expectException(InsufficientBalanceException::class);

        $this->ledger->unlock($this->user->id, 'USDT', '41', 'withdrawal', 1);
    }

    public function test_credit_buy_order_credits_net_and_records_fee(): void
    {
        // gross 200, fee 1 → user receives 199, exchange keeps 1.
        $this->ledger->creditBuyOrder($this->user->id, 'USDT', '200', '1', 'exchange_order', 1);

        $this->assertBcEquals('199', $this->ledger->availableBalance($this->user->id, 'USDT'));

        $feeRevenue = LedgerEntry::query()
            ->where('account', 'fee_revenue')
            ->where('ref_type', 'exchange_order')
            ->sum('credit');

        $this->assertBcEquals('1', (string) $feeRevenue);
        $this->assertLedgerBalanced();
    }

    public function test_settle_sell_order_burns_locked_gross(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '150', 'deposit', 1);
        $this->ledger->lock($this->user->id, 'USDT', '100', 'exchange_order', 2);

        $this->ledger->settleSellOrder($this->user->id, 'USDT', '100', '0.5', 'exchange_order', 2);

        $this->assertBcEquals('50', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('0', $this->ledger->lockedBalance($this->user->id, 'USDT'));
        $this->assertLedgerBalanced();
    }

    public function test_settle_sell_order_with_insufficient_lock_throws(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '150', 'deposit', 1);
        $this->ledger->lock($this->user->id, 'USDT', '50', 'exchange_order', 2);

        $this->expectException(InsufficientBalanceException::class);

        $this->ledger->settleSellOrder($this->user->id, 'USDT', '100', '0.5', 'exchange_order', 2);
    }

    public function test_settle_withdrawal_burns_total_debit(): void
    {
        $this->ledger->creditDeposit($this->user->id, 'USDT', '150', 'deposit', 1);
        // total 101 = 100 sent + 0.5 service fee + 0.5 network fee
        $this->ledger->lock($this->user->id, 'USDT', '101', 'withdrawal', 3);

        $this->ledger->settleWithdrawal($this->user->id, 'USDT', '101', '100', '1', 'withdrawal', 3);

        $this->assertBcEquals('49', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('0', $this->ledger->lockedBalance($this->user->id, 'USDT'));
        $this->assertLedgerBalanced();
    }

    public function test_balances_are_isolated_per_user_and_asset(): void
    {
        $other = $this->createClient();

        $this->ledger->creditDeposit($this->user->id, 'USDT', '100', 'deposit', 1);
        $this->ledger->creditDeposit($other->id, 'USDT', '7', 'deposit', 2);

        $this->assertBcEquals('100', $this->ledger->availableBalance($this->user->id, 'USDT'));
        $this->assertBcEquals('7', $this->ledger->availableBalance($other->id, 'USDT'));
        $this->assertBcEquals('0', $this->ledger->availableBalance($this->user->id, 'BTC'));
    }

    /**
     * Главный инвариант двойной записи: сумма дебетов == сумме кредитов.
     */
    private function assertLedgerBalanced(): void
    {
        $debit = (string) LedgerEntry::query()->sum('debit');
        $credit = (string) LedgerEntry::query()->sum('credit');

        $this->assertBcEquals($debit, $credit);
    }

    private function assertBcEquals(string $expected, string $actual): void
    {
        $this->assertSame(
            0,
            bccomp($expected, $actual, 18),
            "Expected {$expected}, got {$actual}",
        );
    }
}
