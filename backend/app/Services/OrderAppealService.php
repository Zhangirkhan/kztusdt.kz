<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeOrder;
use App\Models\OrderAppeal;
use App\Models\OrderAppealAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderAppealService
{
    public const CLIENT_BUY_REASONS = [
        'paid_not_confirmed',
        'wrong_amount',
        'payment_issue',
        'other',
    ];

    public const CLIENT_SELL_REASONS = [
        'kzt_not_received',
        'wrong_amount',
        'other',
    ];

    public const EXCHANGE_REASONS = [
        'client_not_paid',
        'false_paid_mark',
        'client_not_confirming',
        'other',
    ];

    public function __construct(
        private readonly ExchangeOrderService $exchangeOrderService,
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
    ) {}

    public function confirmationDeadlineFor(ExchangeOrder $order): ?Carbon
    {
        $confirmationMinutes = (int) (config('exchange.confirmation_term_minutes') ?: 20);

        if ($confirmationMinutes <= 0) {
            return null;
        }

        if ($order->isBuy() && $order->payment_marked_at !== null) {
            return $order->payment_marked_at->copy()->addMinutes($confirmationMinutes);
        }

        if ($order->isSell() && $order->kzt_sent_at !== null) {
            return $order->kzt_sent_at->copy()->addMinutes($confirmationMinutes);
        }

        return null;
    }

    public function appealDeadlineFor(ExchangeOrder $order): ?Carbon
    {
        if ($order->isFinal() || $order->status === ExchangeOrder::STATUS_DISPUTE) {
            return null;
        }

        $paymentDeadline = $this->exchangeOrderService->paymentDeadlineFor($order);
        $confirmationDeadline = $this->confirmationDeadlineFor($order);

        if ($order->isBuy()) {
            if ($order->status === ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT) {
                return $paymentDeadline;
            }

            if (in_array($order->status, [
                ExchangeOrder::STATUS_PAYMENT_PROOF_UPLOADED,
                ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
            ], true)) {
                return $confirmationDeadline ?? $paymentDeadline;
            }
        } else {
            if ($order->status === ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION) {
                return $paymentDeadline;
            }

            if ($order->status === ExchangeOrder::STATUS_KZT_SENT) {
                return $confirmationDeadline ?? $paymentDeadline;
            }
        }

        return null;
    }

    public function isPastAppealDeadline(ExchangeOrder $order): bool
    {
        $deadline = $this->appealDeadlineFor($order);

        if ($deadline === null) {
            return false;
        }

        return now()->greaterThan($deadline);
    }

    public function canOpenAppeal(ExchangeOrder $order, string $side): bool
    {
        if ($order->isFinal()) {
            return false;
        }

        if ($order->openAppeal()->exists()) {
            return false;
        }

        if (! $this->isPastAppealDeadline($order)) {
            return false;
        }

        return match ($side) {
            OrderAppeal::SIDE_CLIENT => $this->isAppealableClientStatus($order),
            OrderAppeal::SIDE_EXCHANGE => $this->isAppealableExchangeStatus($order),
            default => false,
        };
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function openAppeal(
        ExchangeOrder $order,
        User $actor,
        string $side,
        string $reason,
        ?string $description,
        array $attachments = [],
    ): OrderAppeal {
        $this->assertValidReason($order, $side, $reason);

        if (count($attachments) > 5) {
            throw new RuntimeException('Можно загрузить не более 5 файлов.');
        }

        if (! $this->canOpenAppeal($order, $side)) {
            throw new RuntimeException('Апелляцию по этой сделке сейчас открыть нельзя.');
        }

        return DB::transaction(function () use ($order, $actor, $side, $reason, $description, $attachments): OrderAppeal {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->openAppeal()->exists()) {
                throw new RuntimeException('По этой сделке уже открыта апелляция.');
            }

            if (! $this->canOpenAppeal($order, $side)) {
                throw new RuntimeException('Апелляцию по этой сделке сейчас открыть нельзя.');
            }

            $appeal = OrderAppeal::query()->create([
                'exchange_order_id' => $order->id,
                'opened_by_user_id' => $actor->id,
                'tenant_id' => $order->tenant_id,
                'side' => $side,
                'reason' => $reason,
                'description' => $description !== null && $description !== '' ? $description : null,
                'status' => OrderAppeal::STATUS_OPEN,
            ]);

            foreach ($attachments as $file) {
                $path = $file->store("appeal-attachments/{$order->id}", 'local');

                OrderAppealAttachment::query()->create([
                    'order_appeal_id' => $appeal->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size' => $file->getSize() ?: 0,
                ]);
            }

            $order->update(['status' => ExchangeOrder::STATUS_DISPUTE]);

            $this->auditLogService->log(
                action: 'order.appeal_opened',
                userId: $actor->id,
                entityType: 'order_appeal',
                entityId: $appeal->id,
                payload: [
                    'exchange_order_id' => $order->id,
                    'side' => $side,
                    'reason' => $reason,
                ],
                request: request(),
            );

            if ($side === OrderAppeal::SIDE_CLIENT && $order->user_id !== null) {
                $this->notifier->notifyKey(
                    $order->user,
                    'order_appeal_opened',
                    ['id' => $order->id],
                    ['url' => "/exchange/orders/{$order->id}"],
                );
            }

            return $appeal->load('attachments');
        });
    }

    /**
     * @return list<string>
     */
    public function allowedReasons(ExchangeOrder $order, string $side): array
    {
        if ($side === OrderAppeal::SIDE_EXCHANGE) {
            return self::EXCHANGE_REASONS;
        }

        return $order->isBuy() ? self::CLIENT_BUY_REASONS : self::CLIENT_SELL_REASONS;
    }

    private function assertValidReason(ExchangeOrder $order, string $side, string $reason): void
    {
        if (! in_array($reason, $this->allowedReasons($order, $side), true)) {
            throw new RuntimeException('Укажите корректную причину апелляции.');
        }
    }

    private function isAppealableClientStatus(ExchangeOrder $order): bool
    {
        if ($order->isBuy()) {
            return in_array($order->status, [
                ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
                ExchangeOrder::STATUS_PAYMENT_PROOF_UPLOADED,
                ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
            ], true);
        }

        return in_array($order->status, [
            ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
            ExchangeOrder::STATUS_KZT_SENT,
        ], true);
    }

    private function isAppealableExchangeStatus(ExchangeOrder $order): bool
    {
        return $this->isAppealableClientStatus($order);
    }
}
