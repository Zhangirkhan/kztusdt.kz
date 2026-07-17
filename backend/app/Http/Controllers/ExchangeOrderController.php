<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateExchangeOrderRequest;
use App\Http\Requests\StoreOrderAppealRequest;
use App\Http\Requests\UploadPaymentProofRequest;
use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Services\ExchangeListingService;
use App\Services\ExchangeOrderService;
use App\Services\OrderAppealService;
use App\Services\UserBankCardService;
use App\Support\AppLog;
use App\Support\AppealPresenter;
use App\Support\PaymentProofPresenter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ExchangeOrderController extends Controller
{
    public function __construct(
        private readonly ExchangeOrderService $exchangeOrderService,
        private readonly UserBankCardService $bankCardService,
        private readonly ExchangeListingService $listingService,
        private readonly OrderAppealService $orderAppealService,
    ) {}

    public function store(CreateExchangeOrderRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $listing = null;
            $listingId = $request->validated('listing_id');

            if ($listingId !== null) {
                $listing = $this->listingService->findActiveForOrder(
                    (int) $listingId,
                    $request->validated('direction'),
                );
            }

            if ($request->validated('direction') === ExchangeOrder::DIRECTION_BUY) {
                $order = $this->exchangeOrderService->createBuyOrder(
                    $user,
                    $request->validated('kzt_amount') !== null ? (string) $request->validated('kzt_amount') : null,
                    $request->validated('usdt_amount') !== null ? (string) $request->validated('usdt_amount') : null,
                    $listing,
                    $request->validated('payment_bank_code'),
                );
            } else {
                $card = $this->bankCardService->findOwnedCard($user, (int) $request->validated('card_id'));
                $bankDetails = $this->bankCardService->payoutDetails($card, (string) $request->validated('payout_type'));

                $order = $this->exchangeOrderService->createSellOrder(
                    $user,
                    (string) $request->validated('usdt_amount'),
                    $bankDetails,
                    $listing,
                );
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            AppLog::exception($exception, [
                'user_id' => $user?->id,
                'path' => $request->path(),
                'direction' => $request->input('direction'),
            ]);

            return back()->withErrors(['form' => 'Ошибка сервера при создании заявки. Попробуйте ещё раз через минуту.']);
        }

        return redirect()->route('exchange.orders.show', [
            'locale' => $request->route('locale'),
            'order' => $order,
        ]);
    }

    public function show(Request $request, string $locale, ExchangeOrder $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['fiatPaymentRequest', 'openAppeal.attachments']);

        return Inertia::render('Exchange/OrderShow', [
            'order' => $order,
            'paymentRequest' => $order->fiatPaymentRequest,
            'companyRequisites' => [
                'bank_name' => (string) config('exchange.requisites.bank_name'),
                'recipient_name' => (string) config('exchange.requisites.recipient_name'),
                'recipient_account' => (string) config('exchange.requisites.recipient_account'),
                'bin' => (string) config('exchange.requisites.bin'),
                'kbe' => (string) config('exchange.requisites.kbe'),
                'bic' => (string) config('exchange.requisites.bic'),
            ],
            'timers' => $this->orderTimerPayload($order),
            'paymentProof' => PaymentProofPresenter::payload(
                $order->fiatPaymentRequest,
                route('exchange.orders.proof.show', ['locale' => $locale, 'order' => $order]),
            ),
            'activeAppeal' => AppealPresenter::appealPayload($order->openAppeal),
            'canAppeal' => $this->orderAppealService->canOpenAppeal($order, OrderAppeal::SIDE_CLIENT),
            'appealReasons' => $this->orderAppealService->allowedReasons($order, OrderAppeal::SIDE_CLIENT),
        ]);
    }

    public function downloadProof(Request $request, string $locale, ExchangeOrder $order): StreamedResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $paymentRequest = $order->fiatPaymentRequest;
        $path = $paymentRequest?->proof_file_path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $paymentRequest->proof_original_name ?? 'payment-proof',
            ['Content-Type' => $paymentRequest->proof_mime_type ?? 'application/octet-stream'],
        );
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

    public function uploadProof(UploadPaymentProofRequest $request, string $locale, ExchangeOrder $order): RedirectResponse|JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->exchangeOrderService->uploadProof($order, $request->file('proof'));
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Скрин оплаты загружен.']);
        }

        return redirect()->route('exchange.orders.show', [
            'locale' => $locale,
            'order' => $order,
        ])->with('success', 'Скрин оплаты загружен.');
    }

    public function cancel(Request $request, string $locale, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $validated = $request->validate([
                'reason' => ['nullable', 'string', 'max:120'],
            ]);

            $this->exchangeOrderService->cancelByClient($order, $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange', ['locale' => $locale])->with('success', 'Заявка отменена.');
    }

    public function markPaid(Request $request, string $locale, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->exchangeOrderService->markPaidByClient($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange.orders.show', [
            'locale' => $locale,
            'order' => $order,
        ])->with('success', 'Оплата отмечена. Отправьте чек в чат сделки.');
    }

    public function markReceived(Request $request, string $locale, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->exchangeOrderService->confirmSellReceiptByClient($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange.orders.show', [
            'locale' => $locale,
            'order' => $order,
        ])->with('success', 'Получение KZT подтверждено. Заявка завершена.');
    }

    public function storeAppeal(StoreOrderAppealRequest $request, string $locale, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->orderAppealService->openAppeal(
                $order,
                $request->user(),
                OrderAppeal::SIDE_CLIENT,
                (string) $request->validated('reason'),
                $request->validated('description'),
                $request->file('attachments', []),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange.orders.show', [
            'locale' => $locale,
            'order' => $order,
        ])->with('success', 'Апелляция отправлена. Служба поддержки рассмотрит обращение.');
    }
}
