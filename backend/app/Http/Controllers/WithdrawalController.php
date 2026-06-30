<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateWithdrawalRequest;
use App\Models\Withdrawal;
use App\Services\LedgerService;
use App\Services\WithdrawalService;
use App\Support\NetworkRegistry;
use App\Support\WalletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
        private readonly LedgerService $ledgerService,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($deny = WalletAccess::denyResponse($user)) {
            return $deny;
        }

        $asset = (string) config('wallet.asset');

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

        $networks = collect(NetworkRegistry::enabledCodes())
            ->map(fn (string $code): array => [
                'code' => $code,
                'label' => NetworkRegistry::label($code),
                'address_format' => NetworkRegistry::addressFormat($code),
            ])
            ->values();

        return Inertia::render('Withdraw', [
            'balance' => $this->ledgerService->balancesFor($user->id, $asset),
            'feePercent' => $user->feePercent(),
            'networkFee' => (string) config('withdrawal.network_fee_usdt'),
            'minAmount' => (float) config('withdrawal.min_amount'),
            'autoLimit' => (float) config('withdrawal.auto_limit'),
            'withdrawalsEnabled' => (bool) config('withdrawal.enabled'),
            'networks' => $networks,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function store(CreateWithdrawalRequest $request): RedirectResponse
    {
        try {
            $this->withdrawalService->create(
                $request->user(),
                (string) $request->validated('to_address'),
                (string) $request->validated('amount'),
                $request->resolvedNetwork(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('withdraw')
            ->with('success', 'Заявка создана и передана на проверку службе безопасности.');
    }

    public function cancel(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        abort_unless($withdrawal->user_id === $request->user()->id, 403);

        try {
            $this->withdrawalService->cancelByClient($withdrawal);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('withdraw')->with('success', 'Заявка отменена, средства разблокированы.');
    }
}
