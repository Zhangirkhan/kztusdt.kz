<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeListing;
use App\Models\ExchangeOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BankCatalog;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

final class ExchangeListingService
{
    public function __construct(
        private readonly RateService $rateService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activeForClient(string $clientDirection): Collection
    {
        $direction = $clientDirection === ExchangeOrder::DIRECTION_BUY
            ? ExchangeListing::DIRECTION_SELL_USDT
            : ExchangeListing::DIRECTION_BUY_USDT;

        return ExchangeListing::query()
            ->where('is_active', true)
            ->where('direction', $direction)
            ->where('remaining_usdt', '>', 0)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExchangeListing $listing): array => $this->clientPayload($listing));
    }

    /**
     * @return array<string, mixed>
     */
    public function clientPayload(ExchangeListing $listing): array
    {
        $rate = $this->rateForListing($listing);

        return [
            'id' => $listing->id,
            'client_direction' => $listing->clientDirection(),
            'title' => $listing->titleLabel(),
            'price_type' => $listing->price_type,
            'rate' => $rate,
            'market_rate' => $this->marketRateForListing($listing),
            'margin_percent' => $listing->margin_percent !== null
                ? (float) $listing->margin_percent
                : null,
            'payment_term' => $listing->payment_term,
            'payment_term_label' => $this->paymentTermLabel($listing->payment_term),
            'min_limit_kzt' => (float) $listing->min_limit_kzt,
            'max_limit_kzt' => (float) $listing->max_limit_kzt,
            'remaining_usdt' => (float) $listing->remaining_usdt,
            'payment_methods' => collect($listing->payment_methods ?? [])
                ->map(fn (string $code): array => [
                    'code' => $code,
                    'name' => BankCatalog::nameForCode($code),
                ])
                ->values()
                ->all(),
            'conditions_text' => $listing->conditions_text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminPayload(ExchangeListing $listing): array
    {
        return [
            'id' => $listing->id,
            'direction' => $listing->direction,
            'title' => $listing->titleLabel(),
            'price_type' => $listing->price_type,
            'fixed_rate' => $listing->fixed_rate !== null ? (float) $listing->fixed_rate : null,
            'margin_percent' => $listing->margin_percent !== null ? (float) $listing->margin_percent : null,
            'display_rate' => (float) $this->rateForListing($listing),
            'total_usdt' => (float) $listing->total_usdt,
            'remaining_usdt' => (float) $listing->remaining_usdt,
            'min_limit_kzt' => (float) $listing->min_limit_kzt,
            'max_limit_kzt' => (float) $listing->max_limit_kzt,
            'payment_methods' => collect($listing->payment_methods ?? [])
                ->map(fn (string $code): array => [
                    'code' => $code,
                    'name' => BankCatalog::nameForCode($code),
                ])
                ->values()
                ->all(),
            'payment_term' => $listing->payment_term,
            'payment_term_label' => $this->paymentTermLabel($listing->payment_term),
            'conditions_text' => $listing->conditions_text,
            'is_active' => $listing->is_active,
            'sort_order' => $listing->sort_order,
            'published_at' => $listing->published_at?->toIso8601String(),
            'created_at' => $listing->created_at?->toIso8601String(),
            'updated_at' => $listing->updated_at?->toIso8601String(),
            'subtitle' => $this->adminSubtitle($listing),
        ];
    }

    public function marketRateForListing(ExchangeListing $listing): float
    {
        $quote = $this->rateService->cached();

        if ($listing->direction === ExchangeListing::DIRECTION_SELL_USDT) {
            return (float) $quote['buy'];
        }

        return (float) $quote['sell'];
    }

    public function rateForListing(ExchangeListing $listing): string
    {
        if ($listing->price_type === ExchangeListing::PRICE_FIXED) {
            if ($listing->fixed_rate === null) {
                throw new RuntimeException('У объявления не задана фиксированная цена.');
            }

            return number_format((float) $listing->fixed_rate, 8, '.', '');
        }

        $quote = $this->rateService->quoteForOrder();
        $margin = number_format((float) ($listing->margin_percent ?? 0), 4, '.', '');
        $factor = bcdiv($margin, '100', 18);

        if ($listing->direction === ExchangeListing::DIRECTION_SELL_USDT) {
            $base = $quote['buy'];

            return number_format((float) bcmul($base, bcadd('1', $factor, 18), 8), 8, '.', '');
        }

        $base = $quote['sell'];

        return number_format((float) bcmul($base, bcsub('1', $factor, 18), 8), 8, '.', '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, bool $publish = false): ExchangeListing
    {
        $this->validateData($data);

        $listing = ExchangeListing::query()->create([
            'tenant_id' => $actor->tenant_id ?? Tenant::defaultTenantId(),
            'created_by' => $actor->id,
            'direction' => $data['direction'],
            'price_type' => $data['price_type'],
            'fixed_rate' => $data['price_type'] === ExchangeListing::PRICE_FIXED ? $data['fixed_rate'] : null,
            'margin_percent' => $data['price_type'] === ExchangeListing::PRICE_FLOATING ? $data['margin_percent'] : null,
            'total_usdt' => $data['total_usdt'],
            'remaining_usdt' => $data['total_usdt'],
            'min_limit_kzt' => $data['min_limit_kzt'],
            'max_limit_kzt' => $data['max_limit_kzt'],
            'payment_methods' => $data['payment_methods'],
            'payment_term' => $data['payment_term'],
            'conditions_text' => $data['conditions_text'] ?? null,
            'is_active' => $publish,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'published_at' => $publish ? now() : null,
        ]);

        return $listing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExchangeListing $listing, array $data, ?bool $publish = null): ExchangeListing
    {
        $this->validateData($data, $listing);

        $totalUsdt = number_format((float) $data['total_usdt'], 8, '.', '');
        $remaining = number_format((float) $listing->remaining_usdt, 8, '.', '');
        $sold = bcsub(number_format((float) $listing->total_usdt, 8, '.', ''), $remaining, 8);
        $newRemaining = bcsub($totalUsdt, $sold, 8);

        if (bccomp($newRemaining, '0', 8) < 0) {
            throw new InvalidArgumentException('Общая сумма не может быть меньше уже проданного объёма.');
        }

        $wasActive = $listing->is_active;
        $isActive = $publish ?? $wasActive;

        $listing->fill([
            'direction' => $data['direction'],
            'price_type' => $data['price_type'],
            'fixed_rate' => $data['price_type'] === ExchangeListing::PRICE_FIXED ? $data['fixed_rate'] : null,
            'margin_percent' => $data['price_type'] === ExchangeListing::PRICE_FLOATING ? $data['margin_percent'] : null,
            'total_usdt' => $totalUsdt,
            'remaining_usdt' => $newRemaining,
            'min_limit_kzt' => $data['min_limit_kzt'],
            'max_limit_kzt' => $data['max_limit_kzt'],
            'payment_methods' => $data['payment_methods'],
            'payment_term' => $data['payment_term'],
            'conditions_text' => $data['conditions_text'] ?? null,
            'is_active' => $isActive,
            'sort_order' => (int) ($data['sort_order'] ?? $listing->sort_order),
        ]);

        if ($publish === true && ! $wasActive) {
            $listing->published_at = now();
        }

        $listing->save();

        return $listing->fresh();
    }

    public function toggleActive(ExchangeListing $listing, bool $active): ExchangeListing
    {
        $listing->is_active = $active;

        if ($active && $listing->published_at === null) {
            $listing->published_at = now();
        }

        $listing->save();

        return $listing;
    }

    public function delete(ExchangeListing $listing): void
    {
        if ($listing->orders()->whereNotIn('status', [
            ExchangeOrder::STATUS_COMPLETED,
            ExchangeOrder::STATUS_CANCELLED,
            ExchangeOrder::STATUS_FAILED,
        ])->exists()) {
            throw new InvalidArgumentException('Нельзя удалить объявление с активными заявками.');
        }

        $listing->delete();
    }

    public function findActiveForOrder(int $listingId, string $clientDirection): ExchangeListing
    {
        $direction = $clientDirection === ExchangeOrder::DIRECTION_BUY
            ? ExchangeListing::DIRECTION_SELL_USDT
            : ExchangeListing::DIRECTION_BUY_USDT;

        $listing = ExchangeListing::query()
            ->where('id', $listingId)
            ->where('is_active', true)
            ->where('direction', $direction)
            ->where('remaining_usdt', '>', 0)
            ->first();

        if ($listing === null) {
            throw new RuntimeException('Объявление недоступно.');
        }

        return $listing;
    }

    public function reserveVolume(ExchangeListing $listing, string $usdtAmount): void
    {
        $remaining = number_format((float) $listing->remaining_usdt, 8, '.', '');
        $amount = number_format((float) $usdtAmount, 8, '.', '');

        if (bccomp($amount, $remaining, 8) > 0) {
            throw new RuntimeException('Недостаточный остаток по объявлению.');
        }

        $listing->remaining_usdt = bcsub($remaining, $amount, 8);
        $listing->save();
    }

    public function releaseVolume(ExchangeListing $listing, string $usdtAmount): void
    {
        $listing->remaining_usdt = bcadd(
            number_format((float) $listing->remaining_usdt, 8, '.', ''),
            number_format((float) $usdtAmount, 8, '.', ''),
            8,
        );
        $listing->save();
    }

    /**
     * @return array{min: float, max: float}
     */
    public function allowedFixedRateRange(): array
    {
        $market = (float) $this->rateService->cached()['rate'];
        $deviation = (float) config('exchange_listings.fixed_rate_deviation_percent', 10) / 100;

        return [
            'min' => round($market * (1 - $deviation), 2),
            'max' => round($market * (1 + $deviation), 2),
        ];
    }

  /**
     * @return array<int, array{code: string, name: string}>
     */
    public function bankOptions(): array
    {
        return BankCatalog::options();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function paymentTermOptions(): array
    {
        return collect((array) config('exchange_listings.payment_terms', []))
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public function paymentTermLabel(string $term): string
    {
        return (string) (config('exchange_listings.payment_terms')[$term] ?? $term);
    }

    public function paymentTermMinutes(?string $term): ?int
    {
        if ($term === null || $term === '') {
            return null;
        }

        $fromConfig = config("exchange.payment_term_minutes.{$term}");

        if (is_numeric($fromConfig) && (int) $fromConfig > 0) {
            return (int) $fromConfig;
        }

        if (preg_match('/^(\d+)_min$/', $term, $matches) === 1) {
            return (int) $matches[1];
        }

        return match ($term) {
            't_plus_1' => 24 * 60,
            't_plus_2' => 48 * 60,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data, ?ExchangeListing $existing = null): void
    {
        if ((float) $data['min_limit_kzt'] > (float) $data['max_limit_kzt']) {
            throw new InvalidArgumentException('Минимальный лимит не может превышать максимальный.');
        }

        $methods = $data['payment_methods'] ?? [];
        $maxMethods = (int) config('exchange_listings.max_payment_methods', 5);

        if (count($methods) < 1 || count($methods) > $maxMethods) {
            throw new InvalidArgumentException("Выберите от 1 до {$maxMethods} способов оплаты.");
        }

        $allowedBanks = array_keys((array) config('banks.catalog', []));

        foreach ($methods as $code) {
            if (! in_array($code, $allowedBanks, true)) {
                throw new InvalidArgumentException('Недопустимый банк в способах оплаты.');
            }
        }

        $allowedTerms = array_keys((array) config('exchange_listings.payment_terms', []));

        if (! in_array($data['payment_term'], $allowedTerms, true)) {
            throw new InvalidArgumentException('Недопустимый срок оплаты.');
        }

        if ($data['price_type'] === ExchangeListing::PRICE_FIXED) {
            $rate = (float) $data['fixed_rate'];
            $range = $this->allowedFixedRateRange();

            if ($rate < $range['min'] || $rate > $range['max']) {
                throw new InvalidArgumentException(
                    sprintf('Цена должна быть в диапазоне %.2f – %.2f KZT.', $range['min'], $range['max']),
                );
            }
        }

        if ($data['price_type'] === ExchangeListing::PRICE_FLOATING) {
            $margin = (float) ($data['margin_percent'] ?? 0);

            if ($margin < -50 || $margin > 100) {
                throw new InvalidArgumentException('Маржа должна быть от -50% до 100%.');
            }
        }

        if ($existing !== null) {
            return;
        }

        if ((float) $data['total_usdt'] <= 0) {
            throw new InvalidArgumentException('Общая сумма USDT должна быть больше нуля.');
        }
    }

    private function adminSubtitle(ExchangeListing $listing): string
    {
        $priceLabel = $listing->price_type === ExchangeListing::PRICE_FIXED
            ? 'Фиксированная цена'
            : sprintf('Плавающая цена · %s%%', rtrim(rtrim(number_format((float) ($listing->margin_percent ?? 0), 2, '.', ''), '0'), '.'));

        return sprintf('%s · %s', $priceLabel, $this->paymentTermLabel($listing->payment_term));
    }
}
