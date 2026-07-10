<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeListing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class ExchangeListingTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_admin_can_create_and_publish_listing(): void
    {
        $this->fakeExternalApis(481.70);

        $admin = $this->createStaff('exchange_admin');

        $response = $this->actingAs($admin)->post('/admin/listings', [
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FLOATING,
            'margin_percent' => 20,
            'total_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 100000,
            'payment_methods' => ['kaspi', 'halyk'],
            'payment_term' => '15_min',
            'conditions_text' => 'Оплата только с личного счёта.',
            'publish' => true,
        ]);

        $response->assertRedirect(route('admin.listings.index'));

        $this->assertDatabaseHas('exchange_listings', [
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FLOATING,
            'payment_term' => '15_min',
            'is_active' => true,
        ]);
    }

    public function test_client_sees_active_buy_listings_on_exchange_page(): void
    {
        $this->fakeExternalApis(481.70);
        $user = $this->createClient();

        ExchangeListing::query()->create([
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FIXED,
            'fixed_rate' => 482.5,
            'total_usdt' => 1000,
            'remaining_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 100000,
            'payment_methods' => ['kaspi'],
            'payment_term' => '15_min',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/ru/exchange?direction=buy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Exchange')
                ->has('buyListings', 1)
                ->where('buyListings.0.payment_term_label', '15 мин'));
    }

    public function test_buy_order_uses_listing_rate_and_limits(): void
    {
        $this->fakeExternalApis(481.70);
        $user = $this->createClient();

        $listing = ExchangeListing::query()->create([
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FIXED,
            'fixed_rate' => 500,
            'total_usdt' => 1000,
            'remaining_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 100000,
            'payment_methods' => ['kaspi'],
            'payment_term' => 't_plus_1',
            'conditions_text' => 'Точная сумма.',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('exchange_orders', [
            'user_id' => $user->id,
            'exchange_listing_id' => $listing->id,
            'payment_term' => 't_plus_1',
            'payment_bank_code' => 'kaspi',
            'rate' => '500.00000000',
        ]);

        $listing->refresh();
        $this->assertLessThan(1000, (float) $listing->remaining_usdt);
    }

    public function test_payment_term_minutes_are_resolved_from_listing_term_codes(): void
    {
        $service = app(\App\Services\ExchangeListingService::class);

        $this->assertSame(15, $service->paymentTermMinutes('15_min'));
        $this->assertSame(30, $service->paymentTermMinutes('30_min'));
        $this->assertSame(24 * 60, $service->paymentTermMinutes('t_plus_1'));
        $this->assertNull($service->paymentTermMinutes(null));
    }

    public function test_order_show_includes_payment_deadline_from_listing_term(): void
    {
        $this->fakeExternalApis(481.70);
        $user = $this->createClient();
        $admin = $this->createStaff('exchange_admin');

        $this->actingAs($admin)->post('/admin/listings', [
            'direction' => ExchangeListing::DIRECTION_SELL_USDT,
            'price_type' => ExchangeListing::PRICE_FLOATING,
            'margin_percent' => 20,
            'total_usdt' => 1000,
            'min_limit_kzt' => 5000,
            'max_limit_kzt' => 100000,
            'payment_methods' => ['kaspi'],
            'payment_term' => '30_min',
            'conditions_text' => 'Оплата только с личного счёта.',
            'publish' => true,
        ]);

        $listing = ExchangeListing::query()->firstOrFail();

        $this->travelTo(now());

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ])->assertRedirect();

        $order = \App\Models\ExchangeOrder::query()->firstOrFail();

        $response = $this->actingAs($user)->get(route('exchange.orders.show', ['locale' => 'ru', 'order' => $order]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Exchange/OrderShow')
            ->where('timers.payment_term_minutes', 30)
            ->where('timers.payment_deadline', now()->addMinutes(30)->toIso8601String()));
    }
}
