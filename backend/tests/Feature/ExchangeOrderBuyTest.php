<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeOrder;
use App\Models\FiatPaymentRequest;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 6: покупка USDT за тенге (KZT) с ручным подтверждением оплаты.
 *
 * Курс Binance в тестах: 500 ₸, наценка покупки 1% → курс покупки 505 ₸/USDT.
 * Комиссия по умолчанию 0.5%.
 */
final class ExchangeOrderBuyTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_user_without_kyc_cannot_create_order(): void
    {
        $this->fakeExternalApis();

        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 101000,
        ])->assertForbidden();
    }

    public function test_buy_order_from_kzt_amount_computes_usdt_and_fee(): void
    {
        $this->fakeExternalApis(500.0);

        $user = $this->createClient();

        $response = $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 101000,
        ]);

        $order = ExchangeOrder::query()->firstOrFail();
        $response->assertRedirect(route('exchange.orders.show', $order));

        $this->assertSame('buy', $order->direction);
        $this->assertSame(ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT, $order->status);
        $this->assertSame('KZT', $order->fiat_currency);
        $this->assertSame('USDT', $order->crypto_asset);
        $this->assertSame('BEP20', $order->network);

        // 101000 / 505 = 200 USDT gross; fee 0.5% = 1 USDT; net 199.
        $this->assertSame(0, bccomp('505', (string) $order->rate, 8));
        $this->assertSame(0, bccomp('101000', (string) $order->fiat_amount, 2));
        $this->assertSame(0, bccomp('1', (string) $order->fee_amount, 8));
        $this->assertSame(0, bccomp('199', (string) $order->crypto_amount, 8));

        // Платёжное поручение с реквизитами обменника.
        $paymentRequest = $order->fiatPaymentRequest;
        $this->assertSame(FiatPaymentRequest::DIRECTION_USER_TO_EXCHANGE, $paymentRequest->direction);
        $this->assertSame(FiatPaymentRequest::STATUS_PENDING, $paymentRequest->status);
        $this->assertSame(0, bccomp('101000', (string) $paymentRequest->amount, 2));
        $this->assertNotNull($paymentRequest->recipient_account);

        // Деньги ещё НЕ зачислены.
        $this->assertSame(0, bccomp('0', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));
    }

    public function test_buy_order_from_usdt_amount_derives_kzt(): void
    {
        $this->fakeExternalApis(500.0);

        $user = $this->createClient();

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'usdt_amount' => 100,
        ]);

        $order = ExchangeOrder::query()->firstOrFail();

        // Пользователь хочет получить ~100 USDT чистыми.
        $this->assertEqualsWithDelta(100.0, (float) $order->crypto_amount, 0.0001);
        // gross = 100 / 0.995 ≈ 100.5025; fiat = gross * 505 ≈ 50753.77.
        $this->assertEqualsWithDelta(50753.77, (float) $order->fiat_amount, 0.05);
    }

    public function test_buy_amount_limits_are_enforced(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 999, // min 1000
        ])->assertSessionHasErrors(['form']);

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 5000001, // max 5 000 000
        ])->assertSessionHasErrors(['form']);

        $this->assertSame(0, ExchangeOrder::query()->count());
    }

    public function test_client_uploads_payment_proof(): void
    {
        Storage::fake('local');
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/proof", [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect(route('exchange.orders.show', $order));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);
        $this->assertSame(FiatPaymentRequest::STATUS_PROOF_UPLOADED, $order->fiatPaymentRequest->status);
        Storage::disk('local')->assertExists($order->fiatPaymentRequest->proof_file_path);
    }

    public function test_admin_confirms_payment_and_usdt_is_credited(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user); // 199 USDT net, 1 USDT fee

        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->post("/admin/orders/{$order->id}/confirm-payment", ['comment' => 'KZT получены'])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame($admin->id, $order->confirmed_by);
        $this->assertNotNull($order->completed_at);

        $this->assertSame(
            0,
            bccomp('199', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 8),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.buy.confirmed',
            'user_id' => $admin->id,
        ]);
    }

    public function test_confirmed_order_cannot_be_confirmed_twice(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);
        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)->post("/admin/orders/{$order->id}/confirm-payment");
        $this->actingAsAdmin($admin)
            ->post("/admin/orders/{$order->id}/confirm-payment")
            ->assertSessionHasErrors(['form']);

        // Баланс зачислен ровно один раз.
        $this->assertSame(
            0,
            bccomp('199', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 8),
        );
    }

    public function test_admin_rejects_buy_order(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);
        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => 'Оплата не поступила'])
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_CANCELLED, $order->status);
        $this->assertSame('Оплата не поступила', $order->reject_reason);
        $this->assertSame(0, bccomp('0', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));
    }

    public function test_client_marks_payment_without_proof(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);

        $this->actingAs($user)
            ->post("/ru/exchange/orders/{$order->id}/mark-paid")
            ->assertRedirect(route('exchange.orders.show', ['locale' => 'ru', 'order' => $order]));

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);
        $this->assertNotNull($order->payment_marked_at);
    }

    public function test_client_uploads_proof_after_marking_paid(): void
    {
        Storage::fake('local');
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/mark-paid");

        $this->actingAs($user)
            ->postJson("/ru/exchange/orders/{$order->id}/proof", [
                'proof' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertOk()
            ->assertJson(['message' => 'Скрин оплаты загружен.']);

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);
        $this->assertNotNull($order->fiatPaymentRequest->proof_file_path);
    }

    public function test_client_can_download_own_payment_proof(): void
    {
        Storage::fake('local');
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/proof", [
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $this->actingAs($user)
            ->get("/ru/exchange/orders/{$order->id}/proof")
            ->assertOk();

        $this->actingAs($this->createClient())
            ->get("/ru/exchange/orders/{$order->id}/proof")
            ->assertForbidden();
    }

    public function test_client_cancels_own_buy_order(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $order = $this->createBuyOrder($user);

        $this->actingAs($user)
            ->post("/exchange/orders/{$order->id}/cancel")
            ->assertRedirect(route('exchange'));

        $this->assertSame(ExchangeOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_client_cannot_see_or_cancel_foreign_order(): void
    {
        $this->fakeExternalApis();

        $owner = $this->createClient();
        $order = $this->createBuyOrder($owner);

        $intruder = $this->createClient();

        $this->actingAs($intruder)->get("/exchange/orders/{$order->id}")->assertForbidden();
        $this->actingAs($intruder)->post("/exchange/orders/{$order->id}/cancel")->assertForbidden();
        $this->actingAs($intruder)->post("/exchange/orders/{$order->id}/proof", [
            'proof' => UploadedFile::fake()->image('x.jpg'),
        ])->assertForbidden();
    }

    public function test_subscription_reduces_fee_to_subscription_rate(): void
    {
        $this->fakeExternalApis(500.0);

        $user = $this->createClient(['has_subscription' => true]);

        $this->actingAs($user)->post('/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 101000,
        ]);

        $order = ExchangeOrder::query()->firstOrFail();

        // fee 0.05% от 200 USDT = 0.1; net 199.9.
        $this->assertSame(0, bccomp('0.0500', (string) $order->fee_percent, 4));
        $this->assertSame(0, bccomp('0.1', (string) $order->fee_amount, 8));
        $this->assertSame(0, bccomp('199.9', (string) $order->crypto_amount, 8));
    }

    private function createBuyOrder(User $user): ExchangeOrder
    {
        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'kzt_amount' => 101000,
        ]);

        return ExchangeOrder::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
