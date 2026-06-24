<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeOrder;
use App\Models\FiatPaymentRequest;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 7: продажа USDT за тенге — блокировка средств и ручная выплата KZT.
 *
 * Курс Binance в тестах: 500 ₸, скидка продажи 1% → курс продажи 495 ₸/USDT.
 */
final class ExchangeOrderSellTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_sell_order_locks_usdt_immediately(): void
    {
        $this->fakeExternalApis(500.0);

        $user = $this->createClient();
        $this->giveBalance($user, '500');

        $this->actingAs($user)->post('/exchange/orders', $this->sellPayload(100));

        $order = ExchangeOrder::query()->firstOrFail();

        $this->assertSame('sell', $order->direction);
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);
        $this->assertSame(0, bccomp('495', (string) $order->rate, 8));
        $this->assertSame(0, bccomp('100', (string) $order->crypto_amount, 8));
        // fee 0.5% = 0.5 USDT; net 99.5 USDT * 495 = 49252.50 ₸.
        $this->assertSame(0, bccomp('0.5', (string) $order->fee_amount, 8));
        $this->assertSame(0, bccomp('49252.50', (string) $order->fiat_amount, 2));

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('400', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('100', $ledger->lockedBalance($user->id, 'USDT'), 18));

        // Реквизиты клиента для выплаты.
        $paymentRequest = $order->fiatPaymentRequest;
        $this->assertSame(FiatPaymentRequest::DIRECTION_EXCHANGE_TO_USER, $paymentRequest->direction);
        $this->assertSame('Kaspi Gold', $paymentRequest->bank_name);
    }

    public function test_sell_with_insufficient_balance_is_rejected_atomically(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '10');

        $this->actingAs($user)
            ->post('/exchange/orders', $this->sellPayload(100))
            ->assertSessionHasErrors(['form']);

        // Транзакция откатилась полностью — нет ни заявки, ни блокировки.
        $this->assertSame(0, ExchangeOrder::query()->count());

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('10', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_sell_limits_are_enforced(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '20000');

        $this->actingAs($user)
            ->post('/exchange/orders', $this->sellPayload(4)) // min 5
            ->assertSessionHasErrors(['form']);

        $this->actingAs($user)
            ->post('/exchange/orders', $this->sellPayload(10001)) // max 10 000
            ->assertSessionHasErrors(['form']);

        $this->assertSame(0, ExchangeOrder::query()->count());
    }

    public function test_sell_requires_bank_details(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'sell',
            'usdt_amount' => 100,
        ])->assertSessionHasErrors(['bank_name', 'recipient_name', 'recipient_account']);
    }

    public function test_admin_confirms_kzt_payout_and_usdt_is_burned(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/mark-kzt-sent", [
            'payment_reference' => 'KASPI-REF-123',
            'comment' => 'Выплачено',
        ])->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->kzt_sent_at);

        $paymentRequest = $order->fiatPaymentRequest;
        $this->assertSame(FiatPaymentRequest::STATUS_CONFIRMED, $paymentRequest->status);
        $this->assertSame('KASPI-REF-123', $paymentRequest->payment_reference);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('400', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_payout_requires_payment_reference(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/mark-kzt-sent", [])
            ->assertSessionHasErrors(['payment_reference']);
    }

    public function test_client_cancel_releases_locked_usdt(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $this->actingAs($user)
            ->post("/exchange/orders/{$order->id}/cancel")
            ->assertRedirect(route('exchange'));

        $this->assertSame(ExchangeOrder::STATUS_CANCELLED, $order->fresh()->status);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('500', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_admin_reject_releases_locked_usdt(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/reject", [
            'reason' => 'Подозрительная активность',
        ]);

        $this->assertSame(ExchangeOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(
            0,
            bccomp('500', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18),
        );
    }

    public function test_completed_sell_cannot_be_cancelled(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/mark-kzt-sent", [
            'payment_reference' => 'REF-1',
        ]);

        $this->actingAs($user)
            ->post("/exchange/orders/{$order->id}/cancel")
            ->assertSessionHasErrors(['form']);

        $this->assertSame(ExchangeOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function sellPayload(float $usdt): array
    {
        return [
            'direction' => 'sell',
            'usdt_amount' => $usdt,
            'bank_name' => 'Kaspi Gold',
            'recipient_name' => 'Иванов Иван',
            'recipient_account' => 'KZ12345678901234567890',
        ];
    }

    private function createSellOrder(User $user, float $usdt): ExchangeOrder
    {
        $this->actingAs($user)->post('/exchange/orders', $this->sellPayload($usdt));

        return ExchangeOrder::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
