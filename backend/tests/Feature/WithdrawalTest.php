<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 8: вывод USDT BEP20 — все заявки на проверку СБ, без Telegram-подтверждения.
 *
 * Комиссия 0.5% + сетевой сбор 0.5 USDT.
 */
final class WithdrawalTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_ADDRESS = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';

    private const TELEGRAM_ID = '777000111';

    public function test_user_without_kyc_cannot_withdraw(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->post('/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 10,
        ])->assertForbidden();
    }

    public function test_address_format_is_validated(): void
    {
        $user = $this->preparedClient('200');

        $this->actingAs($user)->post('/withdraw', [
            'to_address' => 'not-an-address',
            'amount' => 10,
        ])->assertSessionHasErrors(['to_address']);
    }

    public function test_bad_eip55_checksum_is_rejected(): void
    {
        $user = $this->preparedClient('200');

        // Валидный по формату, но контрольная сумма EIP-55 нарушена.
        $this->actingAs($user)->post('/withdraw', [
            'to_address' => '0x5aaeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
            'amount' => 10,
        ])->assertSessionHasErrors(['form']);

        $this->assertSame(0, Withdrawal::query()->count());
    }

    public function test_minimum_amount_is_enforced(): void
    {
        $user = $this->preparedClient('200');

        $this->actingAs($user)->post('/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 0.5, // min 1 USDT
        ])->assertSessionHasErrors(['form']);
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $user = $this->preparedClient('50');

        $this->actingAs($user)->post('/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 100,
        ])->assertSessionHasErrors(['form']);

        $this->assertSame(0, Withdrawal::query()->count());
        $this->assertSame(
            0,
            bccomp('50', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18),
        );
    }

    public function test_withdrawal_locks_total_and_goes_to_pending_review(): void
    {
        $user = $this->preparedClient('200');

        $this->actingAs($user)->post('/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 100,
        ])->assertRedirect(route('withdraw'));

        $withdrawal = Withdrawal::query()->firstOrFail();

        $this->assertSame(Withdrawal::STATUS_PENDING_REVIEW, $withdrawal->status);
        $this->assertTrue($withdrawal->requires_manual_approval);
        $this->assertSame('BEP20', $withdrawal->network);
        // fee 0.5% = 0.5; network fee 0.01; total 100.51.
        $this->assertSame(0, bccomp('0.5', (string) $withdrawal->fee_amount, 8));
        $this->assertSame(0, bccomp('0.01', (string) $withdrawal->network_fee, 8));
        $this->assertSame(0, bccomp('100.51', (string) $withdrawal->total_debit, 8));

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'withdrawal',
            'entity_id' => $withdrawal->id,
            'required_role' => 'security_officer',
            'status' => 'pending',
        ]);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('99.49', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_small_withdrawal_also_requires_security_review(): void
    {
        $user = $this->preparedClient('200');
        $withdrawal = $this->createWithdrawal($user, 100);

        $this->assertSame(Withdrawal::STATUS_PENDING_REVIEW, $withdrawal->status);
        $this->assertTrue($withdrawal->requires_manual_approval);
        $this->assertNull($withdrawal->approved_at);
    }

    public function test_large_withdrawal_requires_security_review(): void
    {
        $user = $this->preparedClient('700');
        $withdrawal = $this->createWithdrawal($user, 600);

        $this->assertSame(Withdrawal::STATUS_PENDING_REVIEW, $withdrawal->status);
        $this->assertTrue($withdrawal->requires_manual_approval);
        $this->assertNull($withdrawal->approved_at);

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'withdrawal',
            'entity_id' => $withdrawal->id,
            'required_role' => 'security_officer',
            'status' => 'pending',
        ]);
    }

    public function test_client_cancels_withdrawal_from_site(): void
    {
        $user = $this->preparedClient('200');
        $withdrawal = $this->createWithdrawal($user, 100);

        $this->actingAs($user)
            ->post("/withdraw/{$withdrawal->id}/cancel")
            ->assertRedirect(route('withdraw'));

        $this->assertSame(Withdrawal::STATUS_CANCELLED, $withdrawal->fresh()->status);
        $this->assertSame(
            0,
            bccomp('200', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18),
        );
    }

    public function test_client_cannot_cancel_foreign_withdrawal(): void
    {
        $user = $this->preparedClient('200');
        $withdrawal = $this->createWithdrawal($user, 100);

        $intruder = $this->createClient();

        $this->actingAs($intruder)
            ->post("/withdraw/{$withdrawal->id}/cancel")
            ->assertForbidden();
    }

    public function test_security_officer_approves_withdrawal(): void
    {
        $user = $this->preparedClient('700');
        $withdrawal = $this->createWithdrawal($user, 600);

        $officer = $this->createStaff('security_officer');

        $this->actingAs($officer)
            ->post("/admin/withdrawals/{$withdrawal->id}/approve", ['comment' => 'Проверено'])
            ->assertRedirect(route('admin.withdrawals.index'));

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_APPROVED, $withdrawal->status);
        $this->assertSame($officer->id, $withdrawal->approved_by);

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'withdrawal',
            'entity_id' => $withdrawal->id,
            'status' => 'approved',
        ]);
    }

    public function test_security_officer_approves_small_withdrawal(): void
    {
        $user = $this->preparedClient('200');
        $withdrawal = $this->createWithdrawal($user, 100);

        $officer = $this->createStaff('security_officer');

        $this->actingAs($officer)
            ->post("/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertRedirect(route('admin.withdrawals.index'));

        $this->assertSame(Withdrawal::STATUS_APPROVED, $withdrawal->fresh()->status);
    }

    public function test_security_officer_rejects_withdrawal_and_funds_return(): void
    {
        $user = $this->preparedClient('700');
        $withdrawal = $this->createWithdrawal($user, 600);

        $officer = $this->createStaff('security_officer');

        $this->actingAs($officer)
            ->post("/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'Высокий риск'])
            ->assertRedirect(route('admin.withdrawals.index'));

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_REJECTED, $withdrawal->status);
        $this->assertSame('Высокий риск', $withdrawal->reject_reason);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('700', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_queue_does_not_broadcast_while_withdrawals_disabled(): void
    {
        $user = $this->preparedClient('200');
        $withdrawal = $this->createWithdrawal($user, 100);

        $officer = $this->createStaff('security_officer');
        $this->actingAs($officer)->post("/admin/withdrawals/{$withdrawal->id}/approve");

        $result = app(WithdrawalService::class)->processQueue();

        $this->assertFalse($result['enabled']);
        $this->assertSame(0, $result['broadcast']);
        // Заявка безопасно ждёт включения WITHDRAWALS_ENABLED.
        $this->assertSame(Withdrawal::STATUS_APPROVED, $withdrawal->fresh()->status);
    }

    /**
     * KYC-approved клиент с балансом, Telegram-аккаунтом и включённым ботом.
     */
    private function preparedClient(string $balance): User
    {
        $this->enableTelegram();
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->linkTelegram($user, self::TELEGRAM_ID);
        $this->giveBalance($user, $balance);

        return $user;
    }

    private function createWithdrawal(User $user, float $amount): Withdrawal
    {
        $this->actingAs($user)->post('/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => $amount,
        ]);

        return Withdrawal::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
