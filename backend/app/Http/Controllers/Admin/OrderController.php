<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmBuyPaymentRequest;
use App\Http\Requests\Admin\ConfirmSellPayoutRequest;
use App\Http\Requests\Admin\RejectOrderRequest;
use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Services\ExchangeListingService;
use App\Services\ExchangeOrderService;
use App\Services\OrderAppealService;
use App\Support\AppealPresenter;
use App\Support\PaymentProofPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OrderController extends Controller
{
    public function __construct(
        private readonly ExchangeOrderService $exchangeOrderService,
        private readonly ExchangeListingService $listingService,
        private readonly OrderAppealService $orderAppealService,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status', 'active')->toString();
        $direction = $request->string('direction', 'all')->toString();

        $base = $this->scopedQuery($request);

        $orders = (clone $base)
            ->with(['user:id,name,phone', 'fiatPaymentRequest'])
            ->when($status === 'active', fn (Builder $q) => $q->whereNotIn('status', [
                ExchangeOrder::STATUS_COMPLETED,
                ExchangeOrder::STATUS_CANCELLED,
                ExchangeOrder::STATUS_FAILED,
            ]))
            ->when(! in_array($status, ['active', 'all'], true), fn (Builder $q) => $q->where('status', $status))
            ->when($direction !== 'all', fn (Builder $q) => $q->where('direction', $direction))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filterStatus' => $status,
            'filterDirection' => $direction,
            'stats' => [
                'pending' => (clone $base)->whereIn('status', [
                    ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
                    ExchangeOrder::STATUS_PAYMENT_PROOF_UPLOADED,
                    ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
                    ExchangeOrder::STATUS_KZT_SENT,
                ])->count(),
                'completed' => (clone $base)->where('status', ExchangeOrder::STATUS_COMPLETED)->count(),
                'cancelled' => (clone $base)->whereIn('status', [
                    ExchangeOrder::STATUS_CANCELLED,
                    ExchangeOrder::STATUS_FAILED,
                ])->count(),
            ],
        ]);
    }

    public function show(Request $request, ExchangeOrder $order): Response
    {
        $this->authorizeTenant($request, $order);

        $order->load(['user:id,name,phone,kyc_status', 'fiatPaymentRequest', 'confirmedBy:id,name', 'openAppeal.attachments']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
            'paymentRequest' => $order->fiatPaymentRequest,
            'paymentProof' => PaymentProofPresenter::payload(
                $order->fiatPaymentRequest,
                route('admin.orders.proof', $order),
            ),
            'timers' => $this->orderTimerPayload($order),
            'activeAppeal' => AppealPresenter::appealPayload($order->openAppeal, true),
            'canAppeal' => $this->orderAppealService->canOpenAppeal($order, OrderAppeal::SIDE_EXCHANGE),
            'appealReasons' => $this->orderAppealService->allowedReasons($order, OrderAppeal::SIDE_EXCHANGE),
        ]);
    }

    public function proof(Request $request, ExchangeOrder $order): StreamedResponse
    {
        $this->authorizeTenant($request, $order);

        $path = $order->fiatPaymentRequest?->proof_file_path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $order->fiatPaymentRequest->proof_original_name ?? 'payment-proof',
            ['Content-Type' => $order->fiatPaymentRequest->proof_mime_type ?? 'application/octet-stream'],
        );
    }

    public function confirmPayment(ConfirmBuyPaymentRequest $request, ExchangeOrder $order): RedirectResponse
    {
        $this->authorizeTenant($request, $order);

        try {
            $this->exchangeOrderService->confirmBuyPayment(
                $order,
                $request->user(),
                $request->validated('comment'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Оплата подтверждена, USDT зачислены.');
    }

    public function markKztSent(ConfirmSellPayoutRequest $request, ExchangeOrder $order): RedirectResponse
    {
        $this->authorizeTenant($request, $order);

        try {
            $this->exchangeOrderService->confirmSellPayout($order, $request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'KZT отправлены. Ожидаем подтверждения клиента.');
    }

    public function reject(RejectOrderRequest $request, ExchangeOrder $order): RedirectResponse
    {
        $this->authorizeTenant($request, $order);

        try {
            $this->exchangeOrderService->rejectOrder($order, $request->user(), $request->validated('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Заявка отклонена.');
    }

    public function storeAppeal(Request $request, ExchangeOrder $order): RedirectResponse
    {
        $this->authorizeTenant($request, $order);

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                Rule::in($this->orderAppealService->allowedReasons($order, OrderAppeal::SIDE_EXCHANGE)),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        try {
            $this->orderAppealService->openAppeal(
                $order,
                $request->user(),
                OrderAppeal::SIDE_EXCHANGE,
                (string) $validated['reason'],
                $validated['description'] ?? null,
                $request->file('attachments', []),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', 'Апелляция отправлена.');
    }

    /**
     * @return array<string, mixed>
     */
    private function orderTimerPayload(ExchangeOrder $order): array
    {
        $termMinutes = $this->listingService->paymentTermMinutes($order->payment_term) ?? 0;
        $confirmationMinutes = (int) (config('exchange.confirmation_term_minutes') ?: 20);

        $paymentDeadline = $termMinutes > 0 && $order->created_at !== null
            ? $order->created_at->copy()->addMinutes($termMinutes)->toIso8601String()
            : null;

        $confirmationDeadline = $order->payment_marked_at !== null
            ? $order->payment_marked_at->copy()->addMinutes($confirmationMinutes)->toIso8601String()
            : null;

        if ($order->isSell() && $order->kzt_sent_at !== null) {
            $confirmationDeadline = $order->kzt_sent_at->copy()->addMinutes($confirmationMinutes)->toIso8601String();
        }

        return [
            'payment_term_minutes' => $termMinutes > 0 ? $termMinutes : null,
            'confirmation_term_minutes' => $confirmationMinutes,
            'payment_deadline' => $paymentDeadline,
            'confirmation_deadline' => $confirmationDeadline,
        ];
    }

    /**
     * exchange_admin only sees orders of their own tenant; global staff sees everything.
     */
    private function scopedQuery(Request $request): Builder
    {
        $query = ExchangeOrder::query();
        $user = $request->user();

        if (! $user->isStaff() && $user->hasRole('exchange_admin')) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    private function authorizeTenant(Request $request, ExchangeOrder $order): void
    {
        $user = $request->user();

        if (! $user->isStaff() && $user->hasRole('exchange_admin')) {
            abort_unless($order->tenant_id === $user->tenant_id, 403);
        }
    }
}
