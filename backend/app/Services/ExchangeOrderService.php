<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeOrder;
use App\Support\NumberPresenter;
use App\Models\FiatPaymentRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stage 6/7 — buy/sell USDT for KZT with manual fiat settlement by an admin.
 * All balance mutations go through LedgerService (double-entry, row locks).
 */
final class ExchangeOrderService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly RateService $rateService,
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * Create a buy order. Exactly one of $kztAmount / $usdtAmount is provided:
     * the other side is derived from the current buy rate and the user's fee.
     */
    public function createBuyOrder(User $user, ?string $kztAmount, ?string $usdtAmount): ExchangeOrder
    {
        $rate = $this->rateService->quoteForOrder()['buy'];
        $feePercent = number_format($user->feePercent(), 4, '.', '');

        if ($kztAmount !== null) {
            $fiat = number_format((float) $kztAmount, 2, '.', '');
            $gross = bcdiv($fiat, $rate, 18);
        } elseif ($usdtAmount !== null) {
            // User entered the net USDT they want to receive.
            $net = number_format((float) $usdtAmount, 8, '.', '');
            $gross = bcdiv($net, bcsub('1', bcdiv($feePercent, '100', 18), 18), 18);
            $fiat = number_format((float) bcmul($gross, $rate, 8), 2, '.', '');
        } else {
            throw new RuntimeException('Укажите сумму KZT или USDT.');
        }

        $this->assertWithinLimits('buy', $fiat, null);

        $fee = bcdiv(bcmul($gross, $feePercent, 18), '100', 18);
        $netCredit = bcsub($gross, $fee, 18);

        if (bccomp($netCredit, '0', 18) <= 0) {
            throw new RuntimeException('Сумма слишком мала.');
        }

        $requisites = (array) config('exchange.requisites');

        $order = DB::transaction(function () use ($user, $rate, $fiat, $netCredit, $fee, $feePercent, $requisites): ExchangeOrder {
            $order = ExchangeOrder::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? Tenant::defaultTenantId(),
                'direction' => ExchangeOrder::DIRECTION_BUY,
                'status' => ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
                'fiat_currency' => (string) config('exchange.default_fiat'),
                'crypto_asset' => (string) config('exchange.default_crypto'),
                'network' => (string) config('exchange.default_network'),
                'rate' => $rate,
                'fiat_amount' => $fiat,
                'crypto_amount' => $netCredit,
                'fee_percent' => $feePercent,
                'fee_amount' => $fee,
            ]);

            FiatPaymentRequest::query()->create([
                'exchange_order_id' => $order->id,
                'user_id' => $user->id,
                'tenant_id' => $order->tenant_id,
                'direction' => FiatPaymentRequest::DIRECTION_USER_TO_EXCHANGE,
                'amount' => $fiat,
                'bank_name' => $requisites['bank_name'] ?? null,
                'recipient_name' => $requisites['recipient_name'] ?? null,
                'recipient_account' => $requisites['recipient_account'] ?? null,
                'status' => FiatPaymentRequest::STATUS_PENDING,
            ]);

            $this->auditLogService->log(
                action: 'order.buy.created',
                userId: $user->id,
                entityType: 'exchange_order',
                entityId: $order->id,
                payload: ['fiat' => $fiat, 'usdt' => $netCredit, 'rate' => $rate],
                request: request(),
            );

            return $order;
        });

        $this->notifier->notifyKey(
            $user,
            'order_buy_created',
            [
                'id' => $order->id,
                'fiat' => $this->kzt($fiat),
                'usdt' => $this->usdt($netCredit),
                'rate' => $this->kzt($rate),
            ],
        );

        return $order;
    }

    /**
     * Create a sell order: lock the gross USDT immediately.
     */
    public function createSellOrder(User $user, string $usdtAmount, array $bankDetails): ExchangeOrder
    {
        $rate = $this->rateService->quoteForOrder()['sell'];
        $feePercent = number_format($user->feePercent(), 4, '.', '');

        $gross = number_format((float) $usdtAmount, 8, '.', '');
        $this->assertWithinLimits('sell', null, $gross);

        $fee = bcdiv(bcmul($gross, $feePercent, 18), '100', 18);
        $net = bcsub($gross, $fee, 18);
        $fiat = number_format((float) bcmul($net, $rate, 8), 2, '.', '');

        if (bccomp($fiat, '0', 2) <= 0) {
            throw new RuntimeException('Сумма слишком мала.');
        }

        $order = DB::transaction(function () use ($user, $rate, $fiat, $gross, $fee, $feePercent, $bankDetails): ExchangeOrder {
            $order = ExchangeOrder::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? Tenant::defaultTenantId(),
                'direction' => ExchangeOrder::DIRECTION_SELL,
                'status' => ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
                'fiat_currency' => (string) config('exchange.default_fiat'),
                'crypto_asset' => (string) config('exchange.default_crypto'),
                'network' => (string) config('exchange.default_network'),
                'rate' => $rate,
                'fiat_amount' => $fiat,
                'crypto_amount' => $gross,
                'fee_percent' => $feePercent,
                'fee_amount' => $fee,
            ]);

            // Hold the user's USDT until the admin pays out KZT (throws if insufficient).
            $this->ledgerService->lock(
                $user->id,
                $order->crypto_asset,
                $gross,
                'exchange_order',
                $order->id,
                "Sell order #{$order->id} hold",
            );

            FiatPaymentRequest::query()->create([
                'exchange_order_id' => $order->id,
                'user_id' => $user->id,
                'tenant_id' => $order->tenant_id,
                'direction' => FiatPaymentRequest::DIRECTION_EXCHANGE_TO_USER,
                'amount' => $fiat,
                'bank_name' => $bankDetails['bank_name'],
                'recipient_name' => $bankDetails['recipient_name'],
                'recipient_account' => $bankDetails['recipient_account'],
                'status' => FiatPaymentRequest::STATUS_PENDING,
            ]);

            $this->auditLogService->log(
                action: 'order.sell.created',
                userId: $user->id,
                entityType: 'exchange_order',
                entityId: $order->id,
                payload: ['usdt' => $gross, 'fiat' => $fiat, 'rate' => $rate],
                request: request(),
            );

            return $order;
        });

        $this->notifier->notifyKey(
            $user,
            'order_sell_created',
            [
                'id' => $order->id,
                'usdt' => $this->usdt($gross),
                'fiat' => $this->kzt($fiat),
                'rate' => $this->kzt($rate),
            ],
        );

        return $order;
    }

    /**
     * Client uploads a screenshot of the KZT transfer (buy flow).
     */
    public function uploadProof(ExchangeOrder $order, UploadedFile $file): void
    {
        if (! $order->isBuy()) {
            throw new RuntimeException('Скрин оплаты загружается только для заявок на покупку.');
        }

        if (! in_array($order->status, [
            ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
            ExchangeOrder::STATUS_PAYMENT_PROOF_UPLOADED,
        ], true)) {
            throw new RuntimeException('Заявка не ожидает загрузку подтверждения оплаты.');
        }

        DB::transaction(function () use ($order, $file): void {
            $path = $file->store("payment-proofs/{$order->user_id}", 'local');

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'proof_file_path' => $path,
                'proof_original_name' => $file->getClientOriginalName(),
                'proof_mime_type' => $file->getMimeType(),
                'status' => FiatPaymentRequest::STATUS_PROOF_UPLOADED,
            ]);

            $order->update(['status' => ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION]);

            $this->auditLogService->log(
                action: 'order.proof_uploaded',
                userId: $order->user_id,
                entityType: 'exchange_order',
                entityId: $order->id,
                request: request(),
            );
        });

        $this->notifier->notifyKey(
            $order->user,
            'order_proof_uploaded',
            ['id' => $order->id],
        );
    }

    /**
     * Admin confirms the KZT arrived: credit USDT (net) via the ledger, complete the order.
     */
    public function confirmBuyPayment(ExchangeOrder $order, User $admin, ?string $comment = null): void
    {
        DB::transaction(function () use ($order, $admin, $comment): void {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isBuy() || ! in_array($order->status, ExchangeOrder::BUY_CONFIRMABLE_STATUSES, true)) {
                throw new RuntimeException('Заявка не может быть подтверждена в текущем статусе.');
            }

            $gross = bcadd((string) $order->crypto_amount, (string) $order->fee_amount, 18);

            $this->ledgerService->creditBuyOrder(
                $order->user_id,
                $order->crypto_asset,
                $gross,
                (string) $order->fee_amount,
                'exchange_order',
                $order->id,
                "Buy order #{$order->id} settlement",
            );

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'status' => FiatPaymentRequest::STATUS_CONFIRMED,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'comment' => $comment,
            ]);

            $order->update([
                'status' => ExchangeOrder::STATUS_COMPLETED,
                'confirmed_by' => $admin->id,
                'kzt_received_at' => now(),
                'completed_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'order.buy.confirmed',
                userId: $admin->id,
                entityType: 'exchange_order',
                entityId: $order->id,
                payload: ['comment' => $comment, 'usdt' => (string) $order->crypto_amount],
                request: request(),
            );
        });

        $order->refresh();

        $this->notifier->notifyKey(
            $order->user,
            'order_buy_completed',
            ['id' => $order->id, 'usdt' => $this->usdt((string) $order->crypto_amount)],
        );
    }

    /**
     * Admin confirms KZT was sent to the client (sell flow): burn locked USDT, complete.
     */
    public function confirmSellPayout(ExchangeOrder $order, User $admin, array $data): void
    {
        DB::transaction(function () use ($order, $admin, $data): void {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isSell() || $order->status !== ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION) {
                throw new RuntimeException('Заявка не может быть подтверждена в текущем статусе.');
            }

            $this->ledgerService->settleSellOrder(
                $order->user_id,
                $order->crypto_asset,
                (string) $order->crypto_amount,
                (string) $order->fee_amount,
                'exchange_order',
                $order->id,
                "Sell order #{$order->id} settlement",
            );

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'status' => FiatPaymentRequest::STATUS_CONFIRMED,
                'payment_reference' => $data['payment_reference'] ?? null,
                'bank_name' => $data['bank_name'] ?? $order->fiatPaymentRequest->bank_name,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'comment' => $data['comment'] ?? null,
            ]);

            $order->update([
                'status' => ExchangeOrder::STATUS_COMPLETED,
                'confirmed_by' => $admin->id,
                'kzt_sent_at' => now(),
                'completed_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'order.sell.paid_out',
                userId: $admin->id,
                entityType: 'exchange_order',
                entityId: $order->id,
                payload: [
                    'reference' => $data['payment_reference'] ?? null,
                    'fiat' => (string) $order->fiat_amount,
                ],
                request: request(),
            );
        });

        $order->refresh();

        $this->notifier->notifyKey(
            $order->user,
            'order_sell_completed',
            ['id' => $order->id, 'fiat' => $this->kzt((string) $order->fiat_amount)],
        );
    }

    /**
     * Admin rejects an order (any direction). Sell holds are released.
     */
    public function rejectOrder(ExchangeOrder $order, User $admin, string $reason): void
    {
        $this->cancelInternal($order, $reason, $admin, 'order.rejected');

        $this->notifier->notifyKey(
            $order->user,
            'order_rejected',
            ['id' => $order->id, 'reason' => $reason],
        );
    }

    /**
     * Client cancels their own order while it is still cancellable.
     */
    public function cancelByClient(ExchangeOrder $order): void
    {
        $this->cancelInternal($order, 'Отменена клиентом', $order->user, 'order.cancelled_by_client');

        $this->notifier->notifyKey(
            $order->user,
            'order_cancelled',
            ['id' => $order->id],
        );
    }

    private function cancelInternal(ExchangeOrder $order, string $reason, User $actor, string $auditAction): void
    {
        DB::transaction(function () use ($order, $reason, $actor, $auditAction): void {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            $cancellable = $order->isBuy()
                ? ExchangeOrder::BUY_CONFIRMABLE_STATUSES
                : [ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION, ExchangeOrder::STATUS_CREATED];

            if (! in_array($order->status, $cancellable, true)) {
                throw new RuntimeException('Заявку нельзя отменить в текущем статусе.');
            }

            if ($order->isSell()) {
                $this->ledgerService->unlock(
                    $order->user_id,
                    $order->crypto_asset,
                    (string) $order->crypto_amount,
                    'exchange_order',
                    $order->id,
                    "Sell order #{$order->id} hold released",
                );
            }

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'status' => FiatPaymentRequest::STATUS_CANCELLED,
                'comment' => $reason,
            ]);

            $order->update([
                'status' => ExchangeOrder::STATUS_CANCELLED,
                'reject_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $this->auditLogService->log(
                action: $auditAction,
                userId: $actor->id,
                entityType: 'exchange_order',
                entityId: $order->id,
                payload: ['reason' => $reason],
                request: request(),
            );
        });
    }

    private function assertWithinLimits(string $direction, ?string $kzt, ?string $usdt): void
    {
        if ($direction === 'buy' && $kzt !== null) {
            $min = number_format((float) config('exchange.min_buy_kzt'), 2, '.', '');
            $max = number_format((float) config('exchange.max_buy_kzt'), 2, '.', '');

            if (bccomp($kzt, $min, 2) < 0 || bccomp($kzt, $max, 2) > 0) {
                throw new RuntimeException("Сумма покупки должна быть от {$this->kzt($min)} до {$this->kzt($max)} ₸.");
            }
        }

        if ($direction === 'sell' && $usdt !== null) {
            $min = number_format((float) config('exchange.min_sell_usdt'), 8, '.', '');
            $max = number_format((float) config('exchange.max_sell_usdt'), 8, '.', '');

            if (bccomp($usdt, $min, 8) < 0 || bccomp($usdt, $max, 8) > 0) {
                throw new RuntimeException("Сумма продажи должна быть от {$this->usdt($min)} до {$this->usdt($max)} USDT.");
            }
        }
    }

    private function kzt(string $amount): string
    {
        return NumberPresenter::kzt($amount);
    }

    private function usdt(string $amount): string
    {
        return NumberPresenter::usdt($amount, 2);
    }
}
