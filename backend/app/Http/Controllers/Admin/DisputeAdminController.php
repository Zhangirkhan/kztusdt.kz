<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeOrder;
use Inertia\Inertia;
use Inertia\Response;

final class DisputeAdminController extends Controller
{
    public function index(): Response
    {
        $disputes = ExchangeOrder::query()
            ->with('user:id,name,phone')
            ->whereIn('status', ['dispute', 'manual_review'])
            ->latest('id')
            ->limit(50)
            ->get(['id', 'user_id', 'direction', 'status', 'fiat_amount', 'crypto_amount', 'created_at'])
            ->map(fn (ExchangeOrder $order): array => [
                'id' => $order->id,
                'user' => $order->user?->phone ?? '—',
                'direction' => $order->direction,
                'status' => $order->status,
                'fiat_amount' => $order->fiat_amount,
                'crypto_amount' => $order->crypto_amount,
                'created_at' => $order->created_at?->toIso8601String(),
                'href' => route('admin.orders.show', $order->id),
            ]);

        return Inertia::render('Admin/Disputes/Index', [
            'disputes' => $disputes,
            'stats' => [
                'open' => $disputes->count(),
            ],
        ]);
    }
}
