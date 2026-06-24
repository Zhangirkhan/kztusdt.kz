<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Живой курс USDT/KZT: Binance → CoinGecko → последний известный → fallback.
 */
final class RateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_binance_and_applies_markup(): void
    {
        Http::fake([
            'api.binance.com/*' => Http::response(['symbol' => 'USDTKZT', 'price' => '500.00000000']),
        ]);

        $rate = app(RateService::class)->current();

        $this->assertSame('binance', $rate['source']);
        $this->assertFalse($rate['stale']);
        $this->assertEqualsWithDelta(500.0, $rate['rate'], 0.0001);
        // markup_buy = markup_sell = 1%
        $this->assertEqualsWithDelta(505.0, $rate['buy'], 0.0001);
        $this->assertEqualsWithDelta(495.0, $rate['sell'], 0.0001);
        $this->assertNotNull($rate['updated_at']);
    }

    public function test_falls_back_to_coingecko_when_binance_fails(): void
    {
        Http::fake([
            'api.binance.com/*' => Http::response('error', 500),
            'api.coingecko.com/*' => Http::response(['tether' => ['kzt' => 498.5]]),
        ]);

        $rate = app(RateService::class)->current();

        $this->assertSame('coingecko', $rate['source']);
        $this->assertEqualsWithDelta(498.5, $rate['rate'], 0.0001);
    }

    public function test_uses_config_fallback_when_everything_fails(): void
    {
        Http::fake([
            'api.binance.com/*' => Http::response('error', 500),
            'api.coingecko.com/*' => Http::response('error', 500),
        ]);

        $rate = app(RateService::class)->current();

        $this->assertSame('fallback', $rate['source']);
        $this->assertTrue($rate['stale']);
        $this->assertEqualsWithDelta(500.0, $rate['rate'], 0.0001); // RATE_FALLBACK
        $this->assertNull($rate['updated_at']);
    }

    public function test_keeps_last_known_rate_during_api_outage(): void
    {
        $apisDown = false;

        Http::fake([
            'api.binance.com/*' => function () use (&$apisDown) {
                return $apisDown
                    ? Http::response('error', 500)
                    : Http::response(['symbol' => 'USDTKZT', 'price' => '512.00000000']);
            },
            'api.coingecko.com/*' => Http::response('error', 500),
        ]);

        $service = app(RateService::class);

        $fresh = $service->current();
        $this->assertSame('binance', $fresh['source']);

        // API падает, свежий кэш истекает — должен вернуться последний известный курс.
        $apisDown = true;
        cache()->forget('rates:usdt_kzt:fresh');

        $stale = $service->current();

        $this->assertTrue($stale['stale']);
        $this->assertSame('binance', $stale['source']);
        $this->assertEqualsWithDelta(512.0, $stale['rate'], 0.0001);
    }

    public function test_buy_and_sell_rates_are_formatted_strings(): void
    {
        Http::fake([
            'api.binance.com/*' => Http::response(['price' => '500']),
        ]);

        $service = app(RateService::class);

        $this->assertSame('505.00000000', $service->buyRate());
        $this->assertSame('495.00000000', $service->sellRate());
    }
}
