<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeListing;
use App\Models\ExchangeOrder;
use App\Models\FiatPaymentRequest;
use App\Models\UserBankCard;
use App\Services\ExchangeOrderService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class ExchangeOrderExpireTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_buy_order_is_not_cancelled_after_payment_term(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();

        $listing = $this->createListing('15_min');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ])->assertRedirect();

        $order = ExchangeOrder::query()->firstOrFail();
        $this->assertSame(ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT, $order->status);

        $this->travel(16)->minutes();

        $count = app(ExchangeOrderService::class)->expireOverdue();

        $this->assertSame(0, $count);
        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT, $order->status);
        $this->assertNull($order->cancelled_at);
    }

    public function test_buy_order_stays_active_before_payment_term(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();
        $listing = $this->createListing('30_min');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ])->assertRedirect();

        $this->travel(10)->minutes();

        $count = app(ExchangeOrderService::class)->expireOverdue();

        $this->assertSame(0, $count);
        $this->assertSame(
            ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
            ExchangeOrder::query()->firstOrFail()->status,
        );
    }

    public function test_buy_order_marked_paid_is_not_cancelled_by_payment_timer(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();
        $listing = $this->createListing('15_min');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ])->assertRedirect();

        $order = ExchangeOrder::query()->firstOrFail();

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/mark-paid")->assertRedirect();

        $this->travel(20)->minutes();

        $count = app(ExchangeOrderService::class)->expireOverdue();

        $this->assertSame(0, $count);
        $this->assertSame(
            ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
            $order->fresh()->status,
        );
    }

    public function test_sell_order_is_not_cancelled_after_payment_term(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();
        $this->giveBalance($user, '200');

        $listing = ExchangeListing::query()->create([
            'direction' => ExchangeListing::DIRECTION_BUY_USDT,
            'price_type' => ExchangeListing::PRICE_FIXED,
            'fixed_rate' => 490,
            'total_usdt' => 1000,
            'remaining_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 500000,
            'payment_methods' => ['kaspi'],
            'payment_term' => '15_min',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $card = $user->bankCards()->create([
            'bank_code' => 'kaspi',
            'bik' => 'CASPKZKA',
            'label' => 'Kaspi',
            'holder_name' => 'Иванов Иван',
            'iban' => 'KZ123456789012345678',
        ]);

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'sell',
            'listing_id' => $listing->id,
            'usdt_amount' => 100,
            'card_id' => $card->id,
            'payout_type' => 'iban',
        ])->assertRedirect();

        $order = ExchangeOrder::query()->firstOrFail();
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);

        $this->travel(16)->minutes();

        $count = app(ExchangeOrderService::class)->expireOverdue();

        $this->assertSame(0, $count);
        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, $order->status);
        $this->assertNull($order->cancelled_at);
    }

    public function test_expire_command_reports_count(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();
        $listing = $this->createListing('15_min');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ]);

        $this->travel(16)->minutes();

        $this->artisan('exchange:expire-orders')
            ->expectsOutput('Overdue exchange orders (not auto-cancelled): 0.')
            ->assertSuccessful();
    }

    private function createListing(string $paymentTerm): ExchangeListing
    {
        return ExchangeListing::query()->create([
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FIXED,
            'fixed_rate' => 482.5,
            'total_usdt' => 1000,
            'remaining_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 100000,
            'payment_methods' => ['kaspi'],
            'payment_term' => $paymentTerm,
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
