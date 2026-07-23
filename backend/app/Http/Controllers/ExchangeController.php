<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Services\ExchangeListingService;
use App\Services\LedgerService;
use App\Services\RateService;
use App\Services\UserBankCardService;
use App\Services\WalletService;
use App\Support\CompanyPresenter;
use App\Support\NetworkRegistry;
use App\Support\NumberPresenter;
use App\Support\WalletAccess;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class ExchangeController extends Controller
{
    public function __construct(
        private readonly RateService $rateService,
        private readonly LedgerService $ledgerService,
        private readonly WalletService $walletService,
        private readonly UserBankCardService $bankCardService,
        private readonly ExchangeListingService $listingService,
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
                    'iin_mismatch' => false,
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

    public function homeRedirect(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->canUseWallet()) {
            return redirect()->route('wallet');
        }

        return redirect()->route('kyc');
    }

    public function wallet(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($deny = WalletAccess::denyResponse($user)) {
            return $deny;
        }

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
                    'address_format' => NetworkRegistry::addressFormat($code),
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

        $withdrawals = $user->withdrawals()
            ->latest('id')
            ->limit(20)
            ->get([
                'id', 'network', 'to_address', 'amount', 'fee_amount', 'network_fee', 'total_debit',
                'status', 'tx_hash', 'created_at',
            ])
            ->map(fn (Withdrawal $withdrawal): array => [
                'id' => $withdrawal->id,
                'network' => $withdrawal->network,
                'to_address' => $withdrawal->to_address,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'tx_hash' => $withdrawal->tx_hash,
                'explorer_tx' => NetworkRegistry::exists($withdrawal->network)
                    ? NetworkRegistry::explorerTx($withdrawal->network)
                    : '',
                'created_at' => $withdrawal->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Wallet', [
            'balance' => [
                'usdt' => NumberPresenter::withThousands((float) $balances['available'], 2),
                'available' => $balances['available'],
                'locked' => $balances['locked'],
            ],
            'asset' => $asset,
            'networks' => $networks,
            'selectedNetwork' => $selected,
            'deposits' => $deposits,
            'withdraw' => [
                'feePercent' => $user->feePercent(),
                'networkFee' => (string) config('withdrawal.network_fee_usdt'),
                'minAmount' => (float) config('withdrawal.min_amount'),
                'autoLimit' => (float) config('withdrawal.auto_limit'),
                'withdrawalsEnabled' => (bool) config('withdrawal.enabled'),
                'dueDiligenceThreshold' => app(\App\Services\DueDiligenceService::class)->threshold(),
                'dueDiligenceSubmitted' => $user->dueDiligenceProfile()->exists(),
                'withdrawals' => $withdrawals,
            ],
            'initialTab' => $request->string('tab')->toString() === 'withdraw' ? 'withdraw' : 'deposit',
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
                'rate', 'fee_amount', 'payment_term', 'created_at',
            ]);

        $buyListings = $this->listingService->activeForClient('buy')->values()->all();
        $sellListings = $this->listingService->activeForClient('sell')->values()->all();

        $selectedListing = null;
        $listingId = $request->integer('listing');

        if ($listingId > 0) {
            $selectedListing = collect($buyListings)->firstWhere('id', $listingId)
                ?? collect($sellListings)->firstWhere('id', $listingId);
        }

        $clientDirection = $request->string('direction')->toString();
        if (! in_array($clientDirection, ['buy', 'sell'], true)) {
            $clientDirection = $selectedListing['client_direction'] ?? 'buy';
        }

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
            'cards' => $this->bankCardService->cardsPayload($user),
            'buyListings' => $buyListings,
            'sellListings' => $sellListings,
            'selectedListing' => $selectedListing,
            'initialDirection' => $clientDirection,
            'paymentTermLabels' => config('exchange_listings.payment_terms'),
            'companyRequisites' => [
                'bank_name' => (string) config('exchange.requisites.bank_name'),
                'recipient_name' => (string) config('exchange.requisites.recipient_name'),
                'recipient_account' => (string) config('exchange.requisites.recipient_account'),
                'bin' => (string) config('exchange.requisites.bin'),
                'kbe' => (string) config('exchange.requisites.kbe'),
            ],
        ]);
    }
}
