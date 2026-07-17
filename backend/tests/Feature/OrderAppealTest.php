<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeListing;
use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Services\ExchangeOrderService;
use App\Services\LedgerService;
use App\Services\OrderAppealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\Concerns\InteractsWithAdminHost;
use Tests\TestCase;

final class OrderAppealTest extends TestCase
{
    use ExchangeTestHelpers;
    use InteractsWithAdminHost;
    use RefreshDatabase;

    public function test_client_can_open_appeal_after_payment_deadline(): void
    {
        Storage::fake('local');
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
        $this->travel(16)->minutes();

        $file = UploadedFile::fake()->image('proof.jpg');

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/appeal", [
            'reason' => 'paid_not_confirmed',
            'description' => 'Оплатил, но не подтвердили',
            'attachments' => [$file],
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(ExchangeOrder::STATUS_DISPUTE, $order->status);

        $appeal = OrderAppeal::query()->firstOrFail();
        $this->assertSame(OrderAppeal::SIDE_CLIENT, $appeal->side);
        $this->assertSame('paid_not_confirmed', $appeal->reason);
        $this->assertSame(OrderAppeal::STATUS_OPEN, $appeal->status);
        $this->assertCount(1, $appeal->attachments);
    }

    public function test_client_cannot_open_appeal_before_deadline(): void
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

        $order = ExchangeOrder::query()->firstOrFail();

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/appeal", [
            'reason' => 'paid_not_confirmed',
        ])->assertSessionHasErrors('form');

        $this->assertSame(0, OrderAppeal::query()->count());
    }

    public function test_client_cannot_open_second_appeal(): void
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

        $order = ExchangeOrder::query()->firstOrFail();
        $this->travel(16)->minutes();

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/appeal", [
            'reason' => 'payment_issue',
        ])->assertRedirect();

        $this->actingAs($user)->post("/ru/exchange/orders/{$order->id}/appeal", [
            'reason' => 'other',
        ])->assertSessionHasErrors('form');
    }

    public function test_exchange_admin_can_open_appeal(): void
    {
        $this->fakeExternalApis(500.0);
        $user = $this->createClient();
        $admin = $this->createStaff('exchange_admin');

        $listing = $this->createListing('15_min');

        $this->actingAs($user)->post('/ru/exchange/orders', [
            'direction' => 'buy',
            'listing_id' => $listing->id,
            'kzt_amount' => 50000,
            'payment_bank_code' => 'kaspi',
        ]);

        $order = ExchangeOrder::query()->firstOrFail();
        $this->travel(16)->minutes();

        $appeal = app(OrderAppealService::class)->openAppeal(
            $order,
            $admin,
            OrderAppeal::SIDE_EXCHANGE,
            'client_not_paid',
            'Оплаты нет',
        );

        $this->assertSame(OrderAppeal::SIDE_EXCHANGE, $appeal->side);
        $this->assertSame(ExchangeOrder::STATUS_DISPUTE, $order->fresh()->status);
    }

    public function test_completed_order_cannot_be_appealed(): void
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

        $order = ExchangeOrder::query()->firstOrFail();
        $order->update(['status' => ExchangeOrder::STATUS_COMPLETED, 'completed_at' => now()]);

        $service = app(OrderAppealService::class);
        $this->assertFalse($service->canOpenAppeal($order, OrderAppeal::SIDE_CLIENT));
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
