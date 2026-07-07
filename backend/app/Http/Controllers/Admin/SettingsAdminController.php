<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RateService;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsAdminController extends Controller
{
    public function __construct(
        private readonly RateService $rateService,
    ) {}

    public function index(): Response
    {
        $rate = $this->rateService->cached();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'fees' => [
                    'default' => (float) config('exchange.fee_default'),
                    'subscription' => (float) config('exchange.fee_subscription'),
                ],
                'limits' => [
                    'min_buy_kzt' => (float) config('exchange.min_buy_kzt'),
                    'max_buy_kzt' => (float) config('exchange.max_buy_kzt'),
                    'min_sell_usdt' => (float) config('exchange.min_sell_usdt'),
                    'max_sell_usdt' => (float) config('exchange.max_sell_usdt'),
                ],
                'rate' => [
                    'buy' => $rate['buy'],
                    'sell' => $rate['sell'],
                    'markup_buy' => (float) config('exchange.rate.markup_buy'),
                    'markup_sell' => (float) config('exchange.rate.markup_sell'),
                    'fallback' => (float) config('exchange.rate.fallback'),
                    'stale' => $rate['stale'],
                    'updated_at' => $rate['updated_at'],
                ],
                'requisites' => config('exchange.requisites'),
                'features' => [
                    'withdrawals_enabled' => (bool) config('withdrawal.enabled'),
                    'sweep_enabled' => (bool) config('sweep.enabled'),
                    'kyc_provider' => (string) config('kyc.provider'),
                ],
            ],
            'note' => 'Изменение курсов и лимитов выполняется через переменные окружения (.env) и раздел «Подписки» для тарифов.',
        ]);
    }
}
