<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExchangeOrder;
use App\Support\PaymentProofPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SupportChatPageController extends Controller
{
    public function show(Request $request): Response
    {
        $orderId = filter_var($request->query('order'), FILTER_VALIDATE_INT);

        abort_unless(is_int($orderId) && $orderId > 0, 404);

        $order = ExchangeOrder::query()->with('fiatPaymentRequest')->findOrFail($orderId);

        abort_unless($order->user_id === $request->user()->id, 403);

        $canUploadProof = $order->isBuy()
            && in_array($order->status, [
                ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
                ExchangeOrder::STATUS_PAYMENT_PROOF_UPLOADED,
                ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
            ], true);

        return Inertia::render('Support/Chat', [
            'orderId' => $order->id,
            'backUrl' => $this->sanitizeBackUrl($request->query('back'), $order->id),
            'canUploadProof' => $canUploadProof,
            'needsPaymentProof' => $canUploadProof
                && $order->fiatPaymentRequest?->proof_file_path === null,
            'paymentProof' => PaymentProofPresenter::payload(
                $order->fiatPaymentRequest,
                route('exchange.orders.proof.show', ['locale' => $request->route('locale'), 'order' => $order]),
            ),
        ]);
    }

    private function sanitizeBackUrl(mixed $back, int $orderId): string
    {
        $default = "/exchange/orders/{$orderId}";

        if (! is_string($back) || $back === '') {
            return $default;
        }

        if (! str_starts_with($back, '/') || str_starts_with($back, '//')) {
            return $default;
        }

        if (preg_match('#^/(admin|api|auth)(/|$)#', $back) === 1) {
            return $default;
        }

        return $back;
    }
}
