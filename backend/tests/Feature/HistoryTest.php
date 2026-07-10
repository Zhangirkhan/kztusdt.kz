<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExchangeOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class HistoryTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_exchange_history_buy_filter_returns_only_buy_orders(): void
    {
        $user = $this->createClient();

        ExchangeOrder::query()->create([
            'user_id' => $user->id,
            'direction' => ExchangeOrder::DIRECTION_BUY,
            'status' => ExchangeOrder::STATUS_COMPLETED,
            'rate' => '505',
            'fiat_amount' => '101000',
            'crypto_amount' => '199',
            'fee_percent' => '0.5',
            'fee_amount' => '1',
        ]);

        ExchangeOrder::query()->create([
            'user_id' => $user->id,
            'direction' => ExchangeOrder::DIRECTION_SELL,
            'status' => ExchangeOrder::STATUS_COMPLETED,
            'rate' => '495',
            'fiat_amount' => '99000',
            'crypto_amount' => '200',
            'fee_percent' => '0.5',
            'fee_amount' => '1',
        ]);

        $response = $this->actingAs($user)->get('/wallet/history?section=exchange&filter=buy');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->where('section', 'exchange')
            ->where('filter', 'buy')
            ->has('items', 1)
            ->where('items.0.kind', 'buy'));
    }

    public function test_exchange_history_sell_filter_returns_only_sell_orders(): void
    {
        $user = $this->createClient();

        ExchangeOrder::query()->create([
            'user_id' => $user->id,
            'direction' => ExchangeOrder::DIRECTION_BUY,
            'status' => ExchangeOrder::STATUS_COMPLETED,
            'rate' => '505',
            'fiat_amount' => '101000',
            'crypto_amount' => '199',
            'fee_percent' => '0.5',
            'fee_amount' => '1',
        ]);

        ExchangeOrder::query()->create([
            'user_id' => $user->id,
            'direction' => ExchangeOrder::DIRECTION_SELL,
            'status' => ExchangeOrder::STATUS_COMPLETED,
            'rate' => '495',
            'fiat_amount' => '99000',
            'crypto_amount' => '200',
            'fee_percent' => '0.5',
            'fee_amount' => '1',
        ]);

        $response = $this->actingAs($user)->get('/wallet/history?section=exchange&filter=sell');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->where('section', 'exchange')
            ->where('filter', 'sell')
            ->has('items', 1)
            ->where('items.0.kind', 'sell'));
    }
}
