<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateExchangeOrderRequest;
use App\Http\Requests\UploadPaymentProofRequest;
use App\Models\ExchangeOrder;
use App\Services\ExchangeOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class ExchangeOrderController extends Controller
{
    public function __construct(
        private readonly ExchangeOrderService $exchangeOrderService,
    ) {}

    public function store(CreateExchangeOrderRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $order = $request->validated('direction') === ExchangeOrder::DIRECTION_BUY
                ? $this->exchangeOrderService->createBuyOrder(
                    $user,
                    $request->validated('kzt_amount') !== null ? (string) $request->validated('kzt_amount') : null,
                    $request->validated('usdt_amount') !== null ? (string) $request->validated('usdt_amount') : null,
                )
                : $this->exchangeOrderService->createSellOrder(
                    $user,
                    (string) $request->validated('usdt_amount'),
                    [
                        'bank_name' => $request->validated('bank_name'),
                        'recipient_name' => $request->validated('recipient_name'),
                        'recipient_account' => $request->validated('recipient_account'),
                    ],
                );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange.orders.show', $order);
    }

    public function show(Request $request, ExchangeOrder $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('fiatPaymentRequest');

        return Inertia::render('Exchange/OrderShow', [
            'order' => $order,
            'paymentRequest' => $order->fiatPaymentRequest,
        ]);
    }

    public function uploadProof(UploadPaymentProofRequest $request, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->exchangeOrderService->uploadProof($order, $request->file('proof'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange.orders.show', $order)->with('success', 'Скрин оплаты загружен.');
    }

    public function cancel(Request $request, ExchangeOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $this->exchangeOrderService->cancelByClient($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }

        return redirect()->route('exchange')->with('success', 'Заявка отменена.');
    }
}
