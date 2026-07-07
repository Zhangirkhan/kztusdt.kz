<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveWithdrawalRequest;
use App\Http\Requests\Admin\RejectWithdrawalRequest;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class WithdrawalAdminController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status', 'review')->toString();

        $withdrawals = Withdrawal::query()
            ->with(['user:id,name,phone', 'approvedBy:id,name'])
            ->when($status === 'review', fn (Builder $q) => $q->where('status', Withdrawal::STATUS_PENDING_REVIEW))
            ->when($status === 'active', fn (Builder $q) => $q->whereIn('status', [
                Withdrawal::STATUS_AWAITING_TELEGRAM_CONFIRMATION,
                Withdrawal::STATUS_PENDING_REVIEW,
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_SENDING,
                Withdrawal::STATUS_SENT,
            ]))
            ->when($status === 'failed', fn (Builder $q) => $q->whereIn('status', [
                Withdrawal::STATUS_FAILED,
                Withdrawal::STATUS_NEEDS_RECONCILE,
            ]))
            ->when(! in_array($status, ['review', 'active', 'all', 'failed'], true), fn (Builder $q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Withdrawals/Index', [
            'withdrawals' => $withdrawals,
            'filterStatus' => $status,
            'enabled' => (bool) config('withdrawal.enabled'),
            'autoLimit' => (float) config('withdrawal.auto_limit'),
            'stats' => [
                'review' => Withdrawal::query()->where('status', Withdrawal::STATUS_PENDING_REVIEW)->count(),
                'active' => Withdrawal::query()->whereIn('status', [
                    Withdrawal::STATUS_AWAITING_TELEGRAM_CONFIRMATION,
                    Withdrawal::STATUS_PENDING_REVIEW,
                    Withdrawal::STATUS_APPROVED,
                    Withdrawal::STATUS_SENDING,
                    Withdrawal::STATUS_SENT,
                ])->count(),
                'queued' => Withdrawal::query()->whereIn('status', [
                    Withdrawal::STATUS_APPROVED,
                    Withdrawal::STATUS_SENDING,
                    Withdrawal::STATUS_SENT,
                ])->count(),
                'completed' => Withdrawal::query()->where('status', Withdrawal::STATUS_COMPLETED)->count(),
                'failed' => Withdrawal::query()->whereIn('status', [
                    Withdrawal::STATUS_FAILED,
                    Withdrawal::STATUS_NEEDS_RECONCILE,
                ])->count(),
                'all' => Withdrawal::query()->count(),
            ],
        ]);
    }

    public function approve(ApproveWithdrawalRequest $request, Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $this->withdrawalService->approve($withdrawal, $request->user(), $request->validated('comment'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.withdrawals.index')->with('success', 'Вывод одобрен.');
    }

    public function reject(RejectWithdrawalRequest $request, Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $this->withdrawalService->reject($withdrawal, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.withdrawals.index')->with('success', 'Вывод отклонён, средства разблокированы.');
    }

    public function retry(Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $this->withdrawalService->retryFailed($withdrawal, request()->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.withdrawals.index', ['status' => 'active'])
            ->with('success', 'Заявка снова в очереди на отправку.');
    }
}
