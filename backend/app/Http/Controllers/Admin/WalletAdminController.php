<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\WalletAddress;
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use App\Support\NetworkRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WalletAdminController extends Controller
{
    public function __construct(
        private readonly SystemWalletService $systemWalletService,
        private readonly LedgerService $ledgerService,
    ) {}

    public function index(Request $request): Response
    {
        $network = $request->string('network')->toString();

        if (! NetworkRegistry::isEnabled($network)) {
            $network = (string) (NetworkRegistry::enabledCodes()[0] ?? config('wallet.network'));
        }

        $asset = NetworkRegistry::asset($network);
        $search = trim($request->string('q')->toString());

        $wallets = WalletAddress::query()
            ->with('user:id,name,phone,kyc_status')
            ->where('network', $network)
            ->where('asset', $asset)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('address', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('phone', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(20, ['*'], 'wallets_page')
            ->withQueryString()
            ->through(function (WalletAddress $wallet) use ($asset): array {
                $balances = $this->ledgerService->balancesFor($wallet->user_id, $asset);

                return [
                    'id' => $wallet->id,
                    'address' => $wallet->address,
                    'network' => $wallet->network,
                    'asset' => $wallet->asset,
                    'derivation_path' => $wallet->derivation_path,
                    'is_active' => $wallet->is_active,
                    'created_at' => $wallet->created_at?->toIso8601String(),
                    'user' => $wallet->user ? [
                        'id' => $wallet->user->id,
                        'name' => $wallet->user->name,
                        'phone' => $wallet->user->phone,
                        'kyc_status' => $wallet->user->kyc_status,
                    ] : null,
                    'balance' => [
                        'available' => $balances['available'],
                        'locked' => $balances['locked'],
                    ],
                ];
            });

        $depositStatus = $request->string('deposit_status', 'all')->toString();

        $deposits = Deposit::query()
            ->with('user:id,name,phone')
            ->where('asset', $asset)
            ->where('network', $network)
            ->when($depositStatus !== 'all', fn ($query) => $query->where('status', $depositStatus))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('tx_hash', 'ilike', "%{$search}%")
                        ->orWhere('to_address', 'ilike', "%{$search}%")
                        ->orWhere('from_address', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('phone', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(25, ['*'], 'deposits_page')
            ->withQueryString();

        $sweepEnabled = $network === 'TRC20'
            ? (bool) config('tron.sweep_enabled')
            : (bool) config('sweep.enabled');

        return Inertia::render('Admin/Wallets/Index', [
            'systemWallets' => $this->systemWalletService->wallets($network),
            'wallets' => $wallets,
            'deposits' => $deposits,
            'filters' => [
                'q' => $search,
                'deposit_status' => $depositStatus,
                'network' => $network,
            ],
            'availableNetworks' => collect(NetworkRegistry::enabledCodes())
                ->map(fn (string $code): array => ['code' => $code, 'label' => NetworkRegistry::label($code)])
                ->values(),
            'meta' => [
                'asset' => $asset,
                'network' => $network,
                'confirmations_required' => NetworkRegistry::confirmations($network),
                'sweep_enabled' => $sweepEnabled,
                'withdrawals_enabled' => (bool) config('withdrawal.enabled'),
                'explorer_tx' => NetworkRegistry::explorerTx($network),
                'explorer_address' => (string) (NetworkRegistry::get($network)['explorer_address'] ?? ''),
            ],
            'stats' => [
                'wallets_total' => WalletAddress::query()
                    ->where('network', $network)
                    ->where('asset', $asset)
                    ->count(),
                'deposits_total' => Deposit::query()
                    ->where('asset', $asset)
                    ->where('network', $network)
                    ->count(),
                'deposits_credited' => Deposit::query()
                    ->where('asset', $asset)
                    ->where('network', $network)
                    ->where('status', 'credited')
                    ->count(),
            ],
        ]);
    }
}
