<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Support\NumberPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FinanceAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: 'withdrawals';
        $status = $request->string('status')->toString() ?: 'active';

        $withdrawals = Withdrawal::query()
            ->with('user:id,name,phone')
            ->when($status === 'active', fn ($q) => $q->whereIn('status', [
                'pending_review', 'approved', 'sending', 'sent',
            ]))
            ->when($status === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($status === 'failed', fn ($q) => $q->whereIn('status', ['failed', 'rejected', 'cancelled']))
            ->when(! in_array($status, ['active', 'completed', 'failed', 'all'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Withdrawal $w): array => [
                'id' => $w->id,
                'type' => 'withdrawal',
                'user' => $w->user?->phone ?? '—',
                'amount' => NumberPresenter::withThousands((float) $w->amount, 4).' '.$w->asset,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
                'href' => '/admin/withdrawals',
            ]);

        $deposits = Deposit::query()
            ->with('user:id,name,phone')
            ->when($status === 'active', fn ($q) => $q->whereIn('status', ['detected', 'confirmed']))
            ->when($status === 'completed', fn ($q) => $q->where('status', 'credited'))
            ->when($status === 'failed', fn ($q) => $q->where('status', 'failed'))
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Deposit $d): array => [
                'id' => $d->id,
                'type' => 'deposit',
                'user' => $d->user?->phone ?? '—',
                'amount' => '+'.NumberPresenter::withThousands((float) $d->amount, 4).' '.$d->asset,
                'status' => $d->status,
                'created_at' => $d->created_at?->toIso8601String(),
                'href' => '/admin/wallets',
            ]);

        $items = ($tab === 'deposits' ? $deposits : $withdrawals)->values();

        return Inertia::render('Admin/Finance/Index', [
            'tab' => $tab,
            'status' => $status,
            'items' => $items,
            'stats' => [
                'pending_withdrawals' => Withdrawal::query()->where('status', 'pending_review')->count(),
                'pending_deposits' => Deposit::query()->whereIn('status', ['detected', 'confirmed'])->count(),
                'completed_withdrawals' => Withdrawal::query()->where('status', 'completed')->count(),
                'credited_deposits' => Deposit::query()->where('status', 'credited')->count(),
            ],
        ]);
    }
}
