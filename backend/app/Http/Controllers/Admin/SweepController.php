<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sweep;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class SweepController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'active');

        $query = Sweep::query()->with('user:id,phone')->latest('id');

        if ($status === 'active') {
            $query->whereIn('status', [
                Sweep::STATUS_WAITING_GAS,
                Sweep::STATUS_GAS_SENT,
                Sweep::STATUS_SWEEPING,
            ]);
        } elseif ($status === 'attention') {
            $query->whereIn('status', [Sweep::STATUS_MANUAL_REVIEW, Sweep::STATUS_FAILED]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        return Inertia::render('Admin/Sweeps/Index', [
            'sweeps' => $query->paginate(30)->withQueryString(),
            'filterStatus' => $status,
            'enabled' => (bool) config('sweep.enabled'),
            'stats' => [
                'waiting_gas' => Sweep::query()->where('status', Sweep::STATUS_WAITING_GAS)->count(),
                'in_progress' => Sweep::query()->whereIn('status', [Sweep::STATUS_GAS_SENT, Sweep::STATUS_SWEEPING])->count(),
                'swept' => Sweep::query()->where('status', Sweep::STATUS_SWEPT)->count(),
                'attention' => Sweep::query()->whereIn('status', [Sweep::STATUS_MANUAL_REVIEW, Sweep::STATUS_FAILED])->count(),
            ],
        ]);
    }

    /** Reset a stuck sweep so the next pass retries it. */
    public function retry(Request $request, Sweep $sweep): RedirectResponse
    {
        if (in_array($sweep->status, [Sweep::STATUS_MANUAL_REVIEW, Sweep::STATUS_FAILED], true)) {
            $sweep->update([
                'status' => Sweep::STATUS_WAITING_GAS,
                'attempts' => 0,
                'last_error' => null,
            ]);
        }

        return back()->with('success', "Sweep #{$sweep->id} поставлен в очередь повторно");
    }
}
