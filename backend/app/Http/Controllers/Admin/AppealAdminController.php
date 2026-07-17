<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Models\OrderAppealAttachment;
use App\Support\AppealPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AppealAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status', 'open')->toString();
        $side = $request->string('side', 'all')->toString();

        $base = $this->scopedQuery($request);

        $appeals = (clone $base)
            ->with(['exchangeOrder.user:id,name,phone'])
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->when($side !== 'all', fn (Builder $q) => $q->where('side', $side))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $appeals->getCollection()->transform(function (OrderAppeal $appeal): OrderAppeal {
            return $appeal;
        });

        return Inertia::render('Admin/Appeals/Index', [
            'appeals' => $appeals->through(fn (OrderAppeal $appeal): array => AppealPresenter::indexRows(collect([$appeal]))[0]),
            'filterStatus' => $status,
            'filterSide' => $side,
            'stats' => [
                'open' => (clone $base)->where('status', OrderAppeal::STATUS_OPEN)->count(),
                'total' => (clone $base)->count(),
            ],
        ]);
    }

    public function show(Request $request, OrderAppeal $appeal): Response
    {
        $this->authorizeTenant($request, $appeal);

        $appeal->load([
            'exchangeOrder.user:id,name,phone,kyc_status',
            'openedBy:id,name',
            'attachments',
        ]);

        $attachments = collect($appeal->attachments)->map(
            fn (OrderAppealAttachment $attachment): array => AppealPresenter::attachmentPayload(
                $appeal,
                $attachment,
                route('admin.appeals.attachments.show', [$appeal, $attachment]),
            ),
        )->all();

        return Inertia::render('Admin/Appeals/Show', [
            'appeal' => [
                'id' => $appeal->id,
                'side' => $appeal->side,
                'reason' => $appeal->reason,
                'description' => $appeal->description,
                'status' => $appeal->status,
                'created_at' => $appeal->created_at?->toIso8601String(),
                'opened_by' => $appeal->openedBy?->name ?? '—',
                'attachments' => $attachments,
            ],
            'order' => $appeal->exchangeOrder,
            'orderHref' => route('admin.orders.show', $appeal->exchange_order_id),
        ]);
    }

    public function attachment(Request $request, OrderAppeal $appeal, OrderAppealAttachment $attachment): StreamedResponse
    {
        $this->authorizeTenant($request, $appeal);
        abort_unless($attachment->order_appeal_id === $appeal->id, 404);
        abort_unless(AppealPresenter::fileExists($attachment), 404);

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }

    private function scopedQuery(Request $request): Builder
    {
        $query = OrderAppeal::query();
        $user = $request->user();

        if (! $user->isStaff() && $user->hasRole('exchange_admin')) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    private function authorizeTenant(Request $request, OrderAppeal $appeal): void
    {
        $user = $request->user();

        if (! $user->isStaff() && $user->hasRole('exchange_admin')) {
            abort_unless($appeal->tenant_id === $user->tenant_id, 403);
        }
    }
}
