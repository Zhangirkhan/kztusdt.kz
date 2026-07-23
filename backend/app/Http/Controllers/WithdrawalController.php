<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateWithdrawalRequest;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use App\Support\WalletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        if ($deny = WalletAccess::denyResponse($request->user())) {
            return $deny;
        }

        return redirect()->route('wallet', ['tab' => 'withdraw']);
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
            $message = $exception->getMessage();
            $field = str_contains($message, 'адрес') || str_contains($message, 'Адрес')
                ? 'to_address'
                : (str_contains($message, 'анкет')
                    ? 'form'
                    : (str_contains($message, 'сумм') || str_contains($message, 'Сумм') || str_contains($message, 'баланс') || str_contains($message, 'средств')
                        ? 'amount'
                        : 'form'));

            return back()->withErrors([$field => $message]);
        }

        return redirect()->route('wallet', ['tab' => 'withdraw'])
            ->with('success', 'Заявка создана и передана на проверку службе безопасности.');
    }

    public function cancel(Request $request, string $locale, Withdrawal $withdrawal): RedirectResponse
    {
        abort_unless($withdrawal->user_id === $request->user()->id, 403);

        try {
            $this->withdrawalService->cancelByClient($withdrawal);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('wallet', ['locale' => $locale, 'tab' => 'withdraw'])
            ->with('success', 'Заявка отменена, средства разблокированы.');
    }
}
