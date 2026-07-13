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

        $this->actingAs($user)->post('/ru/exchange/orders', $this->sellPayload($user, 100));

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
        $this->assertSame('Kaspi Bank', $paymentRequest->bank_name);
        $this->assertSame('Иванов Иван', $paymentRequest->recipient_name);
        $this->assertSame('KZ123456789012345678', $paymentRequest->recipient_account);
    }

    public function test_sell_with_insufficient_balance_is_rejected_atomically(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '10');

        $this->actingAs($user)
            ->post('/ru/exchange/orders', $this->sellPayload($user, 100))
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
            ->post('/ru/exchange/orders', $this->sellPayload($user, 4)) // min 5
            ->assertSessionHasErrors(['form']);

        $this->actingAs($user)
            ->post('/ru/exchange/orders', $this->sellPayload($user, 10001)) // max 10 000
            ->assertSessionHasErrors(['form']);

        $this->assertSame(0, ExchangeOrder::query()->count());
    }

    public function test_sell_requires_card_and_payout_type(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'sell',
            'usdt_amount' => 100,
        ])->assertSessionHasErrors(['card_id', 'payout_type']);
    }

    public function test_sell_rejects_foreign_card(): void
    {
        $this->fakeExternalApis();

        $owner = $this->createClient();
        $attacker = $this->createClient();
        $this->giveBalance($attacker, '500');

        $card = $owner->bankCards()->create([
            'bank_code' => 'kaspi',
            'label' => 'Чужая',
            'holder_name' => 'Владелец',
            'phone' => null,
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($attacker)->post('/ru/exchange/orders', [
            'direction' => 'sell',
            'usdt_amount' => 100,
            'card_id' => $card->id,
            'payout_type' => 'iban',
        ])->assertSessionHasErrors(['card_id']);
    }

    public function test_sell_rejects_unavailable_payout_type(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $card = $user->bankCards()->create([
            'bank_code' => 'kaspi',
            'label' => 'Только IBAN',
            'holder_name' => 'Иванов Иван',
            'phone' => null,
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'sell',
            'usdt_amount' => 100,
            'card_id' => $card->id,
            'payout_type' => 'phone',
        ])->assertSessionHasErrors(['payout_type']);
    }

    public function test_admin_confirms_kzt_payout_and_usdt_is_burned(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)->post("/admin/orders/{$order->id}/mark-kzt-sent")
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_KZT_SENT, $order->status);
        $this->assertNotNull($order->kzt_sent_at);

        $paymentRequest = $order->fiatPaymentRequest;
        $this->assertSame(FiatPaymentRequest::STATUS_CONFIRMED, $paymentRequest->status);
        $this->assertNull($paymentRequest->payment_reference);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('400', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('100', $ledger->lockedBalance($user->id, 'USDT'), 18));

        $this->actingAs($user)
            ->post("/ru/exchange/orders/{$order->id}/mark-received")
            ->assertRedirect(route('exchange.orders.show', ['locale' => 'ru', 'order' => $order]));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->kzt_received_at);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('400', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('0', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_admin_can_mark_kzt_sent_without_extra_fields(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->post("/admin/orders/{$order->id}/mark-kzt-sent")
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(ExchangeOrder::STATUS_KZT_SENT, $order->fresh()->status);
    }

    public function test_client_cannot_cancel_sell_order(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $this->actingAs($user)
            ->from(route('exchange.orders.show', ['locale' => 'ru', 'order' => $order]))
            ->post("/ru/exchange/orders/{$order->id}/cancel")
            ->assertRedirect(route('exchange.orders.show', ['locale' => 'ru', 'order' => $order]))
            ->assertSessionHasErrors('form');

        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->fresh()->status);

        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('400', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('100', $ledger->lockedBalance($user->id, 'USDT'), 18));
    }

    public function test_admin_reject_releases_locked_usdt(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '500');
        $order = $this->createSellOrder($user, 100);

        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)->post("/admin/orders/{$order->id}/reject", [
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
        $this->actingAsAdmin($admin)->post("/admin/orders/{$order->id}/mark-kzt-sent");

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/mark-received");

        $this->actingAs($user)
            ->post("/ru/exchange/orders/{$order->id}/cancel")
            ->assertSessionHasErrors(['form']);

        $this->assertSame(ExchangeOrder::STATUS_COMPLETED, $order->fresh()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function sellPayload(User $user, float $usdt, string $payoutType = 'iban'): array
    {
        $card = $user->bankCards()->first();

        if ($card === null) {
            $card = $user->bankCards()->create([
                'bank_code' => 'kaspi',
                'label' => 'Тестовая Kaspi',
                'holder_name' => 'Иванов Иван',
                'phone' => '+77012345678',
                'iban' => 'KZ123456789012345678',
            ]);
        }

        return [
            'direction' => 'sell',
            'usdt_amount' => $usdt,
            'card_id' => $card->id,
            'payout_type' => $payoutType,
        ];
    }

    private function createSellOrder(User $user, float $usdt): ExchangeOrder
    {
        $this->actingAs($user)->post('/ru/exchange/orders', $this->sellPayload($user, $usdt));

        return ExchangeOrder::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
