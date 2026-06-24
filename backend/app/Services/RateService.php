<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AppLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Live USDT/KZT rate with caching, fallback and configurable exchanger markup.
 *
 * Primary source — Binance public ticker; fallback — CoinGecko (tether→KZT).
 * The last successfully fetched rate is stored forever so a temporary API
 * outage degrades to "stale rate + updated_at timestamp", never to a failure.
 */
final class RateService
{
    private const CACHE_FRESH = 'rates:usdt_kzt:fresh';
    private const CACHE_LAST = 'rates:usdt_kzt:last';

    /**
     * Read cached rate only — no external HTTP from web requests.
     *
     * @return array{rate: float, buy: float, sell: float, updated_at: string|null, stale: bool, source: string}
     */
    public function cached(): array
    {
        $fresh = Cache::get(self::CACHE_FRESH);
        $data = is_array($fresh) ? $fresh : Cache::get(self::CACHE_LAST);
        $stale = ! is_array($fresh);

        if (! is_array($data)) {
            $data = [
                'rate' => (float) config('exchange.rate.fallback', 510.50),
                'updated_at' => null,
                'source' => 'fallback',
            ];
            $stale = true;
        }

        return $this->formatRate($data, $stale);
    }

    /**
     * Force-fetch and refresh cache (CLI / scheduler).
     *
     * @return array{rate: float, buy: float, sell: float, updated_at: string|null, stale: bool, source: string}
     */
    public function refresh(): array
    {
        $fetched = $this->fetch();

        if ($fetched !== null) {
            Cache::put(self::CACHE_FRESH, $fetched, (int) config('exchange.rate.cache_ttl', 120));
            Cache::forever(self::CACHE_LAST, $fetched);

            return $this->formatRate($fetched, false);
        }

        return $this->cached();
    }

    /**
     * @return array{rate: float, buy: float, sell: float, updated_at: string|null, stale: bool, source: string}
     */
    public function current(): array
    {
        $fresh = Cache::get(self::CACHE_FRESH);

        if (! is_array($fresh)) {
            return $this->refresh();
        }

        return $this->formatRate($fresh, false);
    }

    public function buyRate(): string
    {
        return number_format($this->current()['buy'], 8, '.', '');
    }

    public function sellRate(): string
    {
        return number_format($this->current()['sell'], 8, '.', '');
    }

    /**
     * Quote used when creating an exchange order. Prefers the warm cache (kept
     * fresh by the scheduler), falling back to a single refresh; it never prices
     * an order off the hard-coded fallback rate — if no real rate is known it
     * fails loudly so the user retries rather than trading at a stale guess.
     *
     * @return array{buy: string, sell: string, rate: string, source: string, stale: bool}
     */
    public function quoteForOrder(): array
    {
        $quote = $this->current();

        if (($quote['source'] ?? 'fallback') === 'fallback') {
            throw new RuntimeException('Курс временно недоступен. Повторите попытку через минуту.');
        }

        return [
            'buy' => number_format($quote['buy'], 8, '.', ''),
            'sell' => number_format($quote['sell'], 8, '.', ''),
            'rate' => number_format($quote['rate'], 8, '.', ''),
            'source' => (string) $quote['source'],
            'stale' => (bool) $quote['stale'],
        ];
    }

    /**
     * @return array{rate: float, updated_at: string, source: string}|null
     */
    private function fetch(): ?array
    {
        $rate = $this->fetchFromBinance() ?? $this->fetchFromCoinGecko();

        if ($rate === null) {
            return null;
        }

        return [
            'rate' => $rate['rate'],
            'updated_at' => now()->toIso8601String(),
            'source' => $rate['source'],
        ];
    }

    /**
     * @return array{rate: float, source: string}|null
     */
    private function fetchFromBinance(): ?array
    {
        try {
            $symbol = (string) config('exchange.rate.symbol', 'USDTKZT');

            $response = Http::timeout(5)->get('https://api.binance.com/api/v3/ticker/price', [
                'symbol' => $symbol,
            ]);

            $price = (float) ($response->json('price') ?? 0);

            if ($response->successful() && $price > 0) {
                return ['rate' => $price, 'source' => 'binance'];
            }
        } catch (Throwable $e) {
            AppLog::warning('rate.fetch_failed', ['source' => 'binance', 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @return array{rate: float, source: string}|null
     */
    private function fetchFromCoinGecko(): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://api.coingecko.com/api/v3/simple/price', [
                'ids' => 'tether',
                'vs_currencies' => 'kzt',
            ]);

            $price = (float) ($response->json('tether.kzt') ?? 0);

            if ($response->successful() && $price > 0) {
                return ['rate' => $price, 'source' => 'coingecko'];
            }
        } catch (Throwable $e) {
            AppLog::warning('rate.fetch_failed', ['source' => 'coingecko', 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @param  array{rate: float, updated_at?: string|null, source?: string}  $data
     * @return array{rate: float, buy: float, sell: float, updated_at: string|null, stale: bool, source: string}
     */
    private function formatRate(array $data, bool $stale): array
    {
        $base = (float) $data['rate'];
        $markupBuy = (float) config('exchange.rate.markup_buy', 0);
        $markupSell = (float) config('exchange.rate.markup_sell', 0);

        return [
            'rate' => round($base, 4),
            'buy' => round($base * (1 + $markupBuy / 100), 4),
            'sell' => round($base * (1 - $markupSell / 100), 4),
            'updated_at' => $data['updated_at'] ?? null,
            'stale' => $stale,
            'source' => (string) ($data['source'] ?? 'unknown'),
        ];
    }
}
