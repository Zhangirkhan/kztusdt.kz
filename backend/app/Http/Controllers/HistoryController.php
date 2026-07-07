<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\NetworkRegistry;
use App\Support\NumberPresenter;
use App\Support\WalletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class HistoryController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($deny = WalletAccess::denyResponse($user)) {
            return $deny;
        }

        $asset = (string) config('wallet.asset');
        $section = $request->string('section')->toString() ?: 'wallet';
        $filter = $request->string('filter')->toString() ?: 'all';
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $search = trim($request->string('q')->toString());

        $walletItems = collect();

        if ($section === 'wallet') {
            $deposits = $user->deposits()
                ->where('asset', $asset)
                ->latest('id')
                ->limit(50)
                ->get(['id', 'network', 'amount', 'status', 'tx_hash', 'created_at']);

            foreach ($deposits as $deposit) {
                if ($filter !== 'all' && $filter !== 'deposit') {
                    continue;
                }

                $walletItems->push([
                    'id' => 'deposit-'.$deposit->id,
                    'kind' => 'deposit',
                    'title' => 'Пополнение '.$deposit->network,
                    'subtitle' => $deposit->tx_hash ? substr($deposit->tx_hash, 0, 12).'…' : '',
                    'amount' => '+'.NumberPresenter::withThousands((float) $deposit->amount, 4).' '.$asset,
                    'status' => $this->mapDepositStatus($deposit->status),
                    'created_at' => $deposit->created_at?->toIso8601String(),
                    'href' => route('wallet'),
                ]);
            }

            $withdrawals = $user->withdrawals()
                ->latest('id')
                ->limit(50)
                ->get(['id', 'amount', 'asset', 'status', 'network', 'created_at']);

            foreach ($withdrawals as $withdrawal) {
                if ($filter !== 'all' && $filter !== 'withdraw') {
                    continue;
                }

                $walletItems->push([
                    'id' => 'withdraw-'.$withdrawal->id,
                    'kind' => 'withdraw',
                    'title' => 'Вывод '.$withdrawal->network,
                    'subtitle' => 'Заявка №'.$withdrawal->id,
                    'amount' => '-'.NumberPresenter::withThousands((float) $withdrawal->amount, 4).' '.$withdrawal->asset,
                    'status' => $this->mapWithdrawStatus($withdrawal->status),
                    'created_at' => $withdrawal->created_at?->toIso8601String(),
                    'href' => route('withdraw'),
                ]);
            }
        }

        $exchangeItems = collect();

        if ($section === 'exchange') {
            $orders = $user->exchangeOrders()
                ->latest('id')
                ->limit(50)
                ->get(['id', 'direction', 'status', 'fiat_amount', 'crypto_amount', 'created_at']);

            foreach ($orders as $order) {
                if ($filter !== 'all' && $filter !== ($order->direction === 'buy' ? 'buy' : 'sell')) {
                    continue;
                }

                $exchangeItems->push([
                    'id' => 'order-'.$order->id,
                    'kind' => $order->direction,
                    'title' => ($order->direction === 'buy' ? 'Покупка' : 'Продажа').' USDT · №'.$order->id,
                    'subtitle' => NumberPresenter::withThousands((float) $order->fiat_amount, 0).' ₸',
                    'amount' => NumberPresenter::withThousands((float) $order->crypto_amount, 2).' USDT',
                    'status' => $this->mapOrderStatus($order->status),
                    'created_at' => $order->created_at?->toIso8601String(),
                    'href' => route('exchange.orders.show', $order->id),
                ]);
            }
        }

        $items = ($section === 'exchange' ? $exchangeItems : $walletItems)
            ->when($search !== '', function ($collection) use ($search) {
                return $collection->filter(function (array $item) use ($search): bool {
                    $haystack = strtolower($item['title'].' '.$item['subtitle'].' '.$item['amount']);

                    return str_contains($haystack, strtolower($search));
                });
            })
            ->when($statusFilter !== 'all', fn ($collection) => $collection->filter(
                fn (array $item): bool => $item['status'] === $statusFilter
            ))
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('History/Index', [
            'section' => $section,
            'filter' => $filter,
            'status' => $statusFilter,
            'search' => $search,
            'items' => $items,
            'stats' => [
                'wallet' => $user->deposits()->count() + $user->withdrawals()->count(),
                'exchange' => $user->exchangeOrders()->count(),
            ],
            'asset' => $asset,
            'explorerNote' => NetworkRegistry::exists(config('wallet.network'))
                ? NetworkRegistry::explorerTx((string) config('wallet.network'))
                : '',
        ]);
    }

    private function mapDepositStatus(string $status): string
    {
        return match ($status) {
            'credited' => 'COMPLETED',
            'failed' => 'FAILED',
            default => 'PENDING',
        };
    }

    private function mapWithdrawStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'COMPLETED',
            'cancelled', 'rejected', 'failed' => 'FAILED',
            default => 'PENDING',
        };
    }

    private function mapOrderStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'COMPLETED',
            'cancelled', 'failed' => 'FAILED',
            default => 'PENDING',
        };
    }
}
