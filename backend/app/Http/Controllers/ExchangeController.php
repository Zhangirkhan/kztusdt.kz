<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LedgerService;
use App\Services\RateService;
use App\Services\WalletService;
use App\Support\CompanyPresenter;
use App\Support\NetworkRegistry;
use App\Support\NumberPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class ExchangeController extends Controller
{
    public function __construct(
        private readonly RateService $rateService,
        private readonly LedgerService $ledgerService,
        private readonly WalletService $walletService,
    ) {}

    public function home(Request $request): Response
    {
        $user = $request->user();
        $rate = $this->rateService->cached();

        return Inertia::render('Home', [
            'companyHero' => CompanyPresenter::hero(),
            'userStatus' => [
                'phone_verified' => (bool) $user?->phone_verified,
                'kyc_status' => $user?->kyc_status ?? 'none',
                'can_use_wallet' => (bool) $user?->canUseWallet(),
                'fee_percent' => $user?->feePercent() ?? config('exchange.fee_default'),
                ...($user ? $user->kycMeta() : [
                    'provider' => 'manual',
                    'needs_verification' => true,
                    'inline_sumsub' => false,
                ]),
            ],
            'rates' => [
                'usdt_kzt' => $rate['rate'],
                'buy' => $rate['buy'],
                'sell' => $rate['sell'],
                'stale' => $rate['stale'],
                'updated_at' => $rate['updated_at'],
            ],
        ]);
    }

    public function wallet(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user && $user->canUseWallet(), 403);

        $asset = (string) config('wallet.asset');

        try {
            $this->walletService->ensureWalletsForUser($user);
        } catch (RuntimeException) {
            // Master seed not configured yet — fall back to whatever already exists.
        }

        $addresses = $user->walletAddresses()
            ->where('is_active', true)
            ->get(['network', 'asset', 'address'])
            ->keyBy('network');

        $networks = collect(NetworkRegistry::enabledCodes())
            ->map(function (string $code) use ($addresses): array {
                $wallet = $addresses->get($code);

                return [
                    'code' => $code,
                    'label' => NetworkRegistry::label($code),
                    'asset' => NetworkRegistry::asset($code),
                    'address' => $wallet?->address,
                    'pending' => $wallet === null,
                    'confirmationsRequired' => NetworkRegistry::confirmations($code),
                    'explorerTx' => NetworkRegistry::explorerTx($code),
                ];
            })
            ->values();

        $selected = $request->string('network')->toString();

        if (! NetworkRegistry::isEnabled($selected)) {
            $selected = (string) ($networks->first()['code'] ?? config('wallet.network'));
        }

        $balances = $this->ledgerService->balancesFor($user->id, $asset);

        $deposits = $user->deposits()
            ->where('asset', $asset)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'network', 'amount', 'status', 'confirmations', 'tx_hash', 'created_at'])
            ->map(function ($deposit): array {
                return [
                    'id' => $deposit->id,
                    'network' => $deposit->network,
                    'amount' => $deposit->amount,
                    'status' => $deposit->status,
                    'confirmations' => $deposit->confirmations,
                    'tx_hash' => $deposit->tx_hash,
                    'explorer_tx' => NetworkRegistry::exists($deposit->network)
                        ? NetworkRegistry::explorerTx($deposit->network)
                        : '',
                    'created_at' => $deposit->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Wallet', [
            'balance' => [
                'usdt' => NumberPresenter::withThousands((float) $balances['available'], 2),
            ],
            'asset' => $asset,
            'networks' => $networks,
            'selectedNetwork' => $selected,
            'deposits' => $deposits,
        ]);
    }

    public function exchange(Request $request): Response
    {
        $user = $request->user();
        $asset = (string) config('exchange.default_crypto');
        $balances = $this->ledgerService->balancesFor($user->id, $asset);

        $orders = $user->exchangeOrders()
            ->latest('id')
            ->limit(20)
            ->get([
                'id', 'direction', 'status', 'fiat_amount', 'crypto_amount',
                'rate', 'fee_amount', 'created_at',
            ]);

        return Inertia::render('Exchange', [
            'rates' => $this->rateService->cached(),
            'feePercent' => $user->feePercent(),
            'canTrade' => $user->canUseWallet(),
            'balance' => $balances,
            'limits' => [
                'min_buy_kzt' => (float) config('exchange.min_buy_kzt'),
                'max_buy_kzt' => (float) config('exchange.max_buy_kzt'),
                'min_sell_usdt' => (float) config('exchange.min_sell_usdt'),
                'max_sell_usdt' => (float) config('exchange.max_sell_usdt'),
            ],
            'orders' => $orders,
        ]);
    }
}
