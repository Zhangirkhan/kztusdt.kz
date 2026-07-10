<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeListing;
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
        private readonly ExchangeListingService $listingService,
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
    ) {}

    /**
     * Create a buy order. Exactly one of $kztAmount / $usdtAmount is provided:
     * the other side is derived from the current buy rate and the user's fee.
     */
    public function createBuyOrder(
        User $user,
        ?string $kztAmount,
        ?string $usdtAmount,
        ?ExchangeListing $listing = null,
        ?string $paymentBankCode = null,
    ): ExchangeOrder {
        $rate = $listing !== null
            ? $this->listingService->rateForListing($listing)
            : $this->rateService->quoteForOrder()['buy'];
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

        $this->assertWithinLimits('buy', $fiat, null, $listing);

        if ($listing !== null) {
            $this->assertListingUsdtVolume($listing, $gross);
            $this->assertListingPaymentBank($listing, $paymentBankCode);
        }

        $fee = bcdiv(bcmul($gross, $feePercent, 18), '100', 18);
        $netCredit = bcsub($gross, $fee, 18);

        if (bccomp($netCredit, '0', 18) <= 0) {
            throw new RuntimeException('Сумма слишком мала.');
        }

        $requisites = (array) config('exchange.requisites');

        $order = DB::transaction(function () use ($user, $rate, $fiat, $netCredit, $fee, $feePercent, $requisites, $listing, $gross, $paymentBankCode): ExchangeOrder {
            if ($listing !== null) {
                $listing = ExchangeListing::query()->lockForUpdate()->findOrFail($listing->id);
                $this->listingService->reserveVolume($listing, $gross);
            }

            $order = ExchangeOrder::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? Tenant::defaultTenantId(),
                'exchange_listing_id' => $listing?->id,
                'direction' => ExchangeOrder::DIRECTION_BUY,
                'status' => ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT,
                'fiat_currency' => (string) config('exchange.default_fiat'),
                'crypto_asset' => (string) config('exchange.default_crypto'),
                'network' => (string) config('exchange.default_network'),
                'rate' => $rate,
                'payment_term' => $listing?->payment_term,
                'payment_bank_code' => $paymentBankCode,
                'listing_conditions' => $listing?->conditions_text,
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
            $this->orderPushData($order),
        );

        return $order;
    }

    /**
     * Create a sell order: lock the gross USDT immediately.
     */
    public function createSellOrder(User $user, string $usdtAmount, array $bankDetails, ?ExchangeListing $listing = null): ExchangeOrder
    {
        $rate = $listing !== null
            ? $this->listingService->rateForListing($listing)
            : $this->rateService->quoteForOrder()['sell'];
        $feePercent = number_format($user->feePercent(), 4, '.', '');

        $gross = number_format((float) $usdtAmount, 8, '.', '');
        $this->assertWithinLimits('sell', null, $gross, $listing);

        $fee = bcdiv(bcmul($gross, $feePercent, 18), '100', 18);
        $net = bcsub($gross, $fee, 18);
        $fiat = number_format((float) bcmul($net, $rate, 8), 2, '.', '');

        if ($listing !== null) {
            $this->assertListingSellUsdtLimits(
                $listing,
                $user,
                $gross,
                $rate,
                $feePercent,
                (string) config('exchange.default_crypto'),
            );
            $this->assertListingUsdtVolume($listing, $gross);
        }

        if (bccomp($fiat, '0', 2) <= 0) {
            throw new RuntimeException('Сумма слишком мала.');
        }

        $order = DB::transaction(function () use ($user, $rate, $fiat, $gross, $fee, $feePercent, $bankDetails, $listing): ExchangeOrder {
            if ($listing !== null) {
                $listing = ExchangeListing::query()->lockForUpdate()->findOrFail($listing->id);
                $this->listingService->reserveVolume($listing, $gross);
            }

            $order = ExchangeOrder::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? Tenant::defaultTenantId(),
                'exchange_listing_id' => $listing?->id,
                'direction' => ExchangeOrder::DIRECTION_SELL,
                'status' => ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
                'fiat_currency' => (string) config('exchange.default_fiat'),
                'crypto_asset' => (string) config('exchange.default_crypto'),
                'network' => (string) config('exchange.default_network'),
                'rate' => $rate,
                'payment_term' => $listing?->payment_term,
                'listing_conditions' => $listing?->conditions_text,
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
            $this->orderPushData($order),
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
            ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
        ], true)) {
            throw new RuntimeException('Заявка не ожидает загрузку подтверждения оплаты.');
        }

        if (
            $order->status === ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION
            && $order->fiatPaymentRequest?->proof_file_path !== null
        ) {
            throw new RuntimeException('Скрин оплаты уже загружен.');
        }

        DB::transaction(function () use ($order, $file): void {
            $path = $file->store("payment-proofs/{$order->user_id}", 'local');

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'proof_file_path' => $path,
                'proof_original_name' => $file->getClientOriginalName(),
                'proof_mime_type' => $file->getMimeType(),
                'status' => FiatPaymentRequest::STATUS_PROOF_UPLOADED,
            ]);

            $order->update([
                'status' => ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
                'payment_marked_at' => $order->payment_marked_at ?? now(),
            ]);

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
            $this->orderPushData($order),
        );
    }

    /**
     * Client marks KZT transfer as sent (buy flow) without uploading proof on the order page.
     */
    public function markPaidByClient(ExchangeOrder $order): void
    {
        if (! $order->isBuy()) {
            throw new RuntimeException('Отметка оплаты доступна только для заявок на покупку.');
        }

        if ($order->status !== ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT) {
            throw new RuntimeException('Заявка не ожидает подтверждения оплаты.');
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION,
                'payment_marked_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'order.payment_marked',
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
            $this->orderPushData($order),
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
            $this->orderPushData($order),
        );
    }

    /**
     * Admin confirms KZT was sent to the client (sell flow). USDT stays locked until the client confirms receipt.
     */
    public function confirmSellPayout(ExchangeOrder $order, User $admin, array $data): void
    {
        DB::transaction(function () use ($order, $admin, $data): void {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $order->isSell() || $order->status !== ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION) {
                throw new RuntimeException('Заявка не может быть подтверждена в текущем статусе.');
            }

            $order->fiatPaymentRequest()->lockForUpdate()->firstOrFail()->update([
                'status' => FiatPaymentRequest::STATUS_CONFIRMED,
                'payment_reference' => $data['payment_reference'] ?? null,
                'bank_name' => $data['bank_name'] ?? $order->fiatPaymentRequest->bank_name,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'comment' => $data['comment'] ?? null,
            ]);

            $order->update([
                'status' => ExchangeOrder::STATUS_KZT_SENT,
                'confirmed_by' => $admin->id,
                'kzt_sent_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'order.sell.kzt_sent',
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
            'order_sell_kzt_sent',
            ['id' => $order->id, 'fiat' => $this->kzt((string) $order->fiat_amount)],
            $this->orderPushData($order),
        );
    }

    /**
     * Client confirms KZT payout was received (sell flow): burn locked USDT, complete the order.
     */
    public function confirmSellReceiptByClient(ExchangeOrder $order): void
    {
        if (! $order->isSell()) {
            throw new RuntimeException('Подтверждение получения доступно только для заявок на продажу.');
        }

        if ($order->status !== ExchangeOrder::STATUS_KZT_SENT) {
            throw new RuntimeException('Заявка не ожидает подтверждения получения KZT.');
        }

        DB::transaction(function () use ($order): void {
            $order = ExchangeOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== ExchangeOrder::STATUS_KZT_SENT) {
                throw new RuntimeException('Заявка не ожидает подтверждения получения KZT.');
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

            $order->update([
                'status' => ExchangeOrder::STATUS_COMPLETED,
                'kzt_received_at' => now(),
                'completed_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'order.sell.received_by_client',
                userId: $order->user_id,
                entityType: 'exchange_order',
                entityId: $order->id,
                request: request(),
            );
        });

        $order->refresh();

        $this->notifier->notifyKey(
            $order->user,
            'order_sell_completed',
            ['id' => $order->id, 'fiat' => $this->kzt((string) $order->fiat_amount)],
            $this->orderPushData($order),
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
            $this->orderPushData($order),
        );
    }

    /**
     * Client cancels their own order while it is still cancellable.
     */
    public function cancelByClient(ExchangeOrder $order, ?string $reason = null): void
    {
        $reason = trim((string) $reason);
        $finalReason = $reason !== '' ? $reason : 'Отменена клиентом';

        $this->cancelInternal($order, $finalReason, $order->user, 'order.cancelled_by_client');

        $this->notifier->notifyKey(
            $order->user,
            'order_cancelled',
            ['id' => $order->id],
            ['url' => '/exchange'],
        );
    }

    /**
     * Cancel orders that exceeded the listing payment term without completion.
     */
    public function expireOverdue(): int
    {
        $orders = ExchangeOrder::query()
            ->where(function ($query): void {
                $query->where(function ($buy): void {
                    $buy->where('direction', ExchangeOrder::DIRECTION_BUY)
                        ->where('status', ExchangeOrder::STATUS_AWAITING_KZT_PAYMENT);
                })->orWhere(function ($sell): void {
                    $sell->where('direction', ExchangeOrder::DIRECTION_SELL)
                        ->where('status', ExchangeOrder::STATUS_PENDING_ADMIN_CONFIRMATION);
                });
            })
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            if (! $this->isPastPaymentDeadline($order)) {
                continue;
            }

            try {
                $this->cancelByTimeout($order);
                $count++;
            } catch (RuntimeException) {
                // Order status changed concurrently — skip.
            }
        }

        return $count;
    }

    public function paymentDeadlineFor(ExchangeOrder $order): ?\Illuminate\Support\Carbon
    {
        $minutes = $this->listingService->paymentTermMinutes($order->payment_term);

        if ($minutes === null || $minutes <= 0 || $order->created_at === null) {
            return null;
        }

        return $order->created_at->copy()->addMinutes($minutes);
    }

    public function isPastPaymentDeadline(ExchangeOrder $order): bool
    {
        $deadline = $this->paymentDeadlineFor($order);

        if ($deadline === null) {
            return false;
        }

        return now()->greaterThan($deadline);
    }

    public function cancelByTimeout(ExchangeOrder $order): void
    {
        $reason = 'Истекло время на завершение сделки';

        $this->cancelInternal($order, $reason, $order->user, 'order.cancelled_by_timeout');

        $this->notifier->notifyKey(
            $order->user,
            'order_cancelled',
            ['id' => $order->id],
            ['url' => '/exchange'],
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

            if ($order->exchange_listing_id !== null) {
                $listing = ExchangeListing::query()->lockForUpdate()->find($order->exchange_listing_id);

                if ($listing !== null) {
                    $gross = $order->isBuy()
                        ? bcadd((string) $order->crypto_amount, (string) $order->fee_amount, 18)
                        : (string) $order->crypto_amount;
                    $this->listingService->releaseVolume($listing, $gross);
                }
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

    private function assertWithinLimits(string $direction, ?string $kzt, ?string $usdt, ?ExchangeListing $listing = null): void
    {
        if ($direction === 'buy' && $kzt !== null) {
            $min = $listing !== null
                ? number_format((float) $listing->min_limit_kzt, 2, '.', '')
                : number_format((float) config('exchange.min_buy_kzt'), 2, '.', '');
            $max = $listing !== null
                ? number_format((float) $listing->max_limit_kzt, 2, '.', '')
                : number_format((float) config('exchange.max_buy_kzt'), 2, '.', '');

            if (bccomp($kzt, $min, 2) < 0 || bccomp($kzt, $max, 2) > 0) {
                throw new RuntimeException("Сумма покупки должна быть от {$this->kzt($min)} до {$this->kzt($max)} ₸.");
            }
        }

        if ($direction === 'sell' && $usdt !== null && $listing === null) {
            $min = number_format((float) config('exchange.min_sell_usdt'), 8, '.', '');
            $max = number_format((float) config('exchange.max_sell_usdt'), 8, '.', '');

            if (bccomp($usdt, $min, 8) < 0 || bccomp($usdt, $max, 8) > 0) {
                throw new RuntimeException("Сумма продажи должна быть от {$this->usdt($min)} до {$this->usdt($max)} USDT.");
            }
        }
    }

    private function assertListingSellUsdtLimits(
        ExchangeListing $listing,
        User $user,
        string $grossUsdt,
        string $rate,
        string $feePercent,
        string $asset,
    ): void {
        $bounds = $this->sellUsdtBoundsForListing($listing, $user, $rate, $feePercent, $asset);
        $gross = number_format((float) $grossUsdt, 2, '.', '');

        if (bccomp($gross, $bounds['min'], 2) < 0 || bccomp($gross, $bounds['max'], 2) > 0) {
            throw new RuntimeException(
                "Сумма продажи должна быть от {$this->usdt($bounds['min'])} до {$this->usdt($bounds['max'])} USDT.",
            );
        }
    }

    /**
     * @return array{min: string, max: string}
     */
    private function sellUsdtBoundsForListing(
        ExchangeListing $listing,
        User $user,
        string $rate,
        string $feePercent,
        string $asset,
    ): array {
        $configMin = number_format((float) config('exchange.min_sell_usdt'), 8, '.', '');
        $configMax = number_format((float) config('exchange.max_sell_usdt'), 8, '.', '');
        $feeFactor = bcsub('1', bcdiv($feePercent, '100', 18), 18);
        $divisor = bcmul($rate, $feeFactor, 18);

        if (bccomp($divisor, '0', 18) <= 0) {
            throw new RuntimeException('Сумма слишком мала.');
        }

        $minFiat = number_format((float) $listing->min_limit_kzt, 2, '.', '');
        $maxFiat = number_format((float) $listing->max_limit_kzt, 2, '.', '');
        $listingMinGross = bcdiv($minFiat, $divisor, 8);
        $listingMaxGross = bcdiv($maxFiat, $divisor, 8);
        $remaining = number_format((float) $listing->remaining_usdt, 8, '.', '');
        $available = $this->ledgerService->availableBalance($user->id, $asset);

        $min = bccomp($listingMinGross, $configMin, 8) > 0 ? $listingMinGross : $configMin;
        $max = bccomp($listingMaxGross, $configMax, 8) < 0 ? $listingMaxGross : $configMax;

        if (bccomp($max, $remaining, 8) > 0) {
            $max = $remaining;
        }

        if (bccomp($max, $available, 8) > 0) {
            $max = $available;
        }

        return [
            'min' => number_format((float) $min, 2, '.', ''),
            'max' => number_format((float) $max, 2, '.', ''),
        ];
    }

    private function assertListingUsdtVolume(ExchangeListing $listing, string $grossUsdt): void
    {
        $remaining = number_format((float) $listing->remaining_usdt, 8, '.', '');
        $amount = number_format((float) $grossUsdt, 8, '.', '');

        if (bccomp($amount, $remaining, 8) > 0) {
            throw new RuntimeException('Недостаточный остаток по объявлению.');
        }
    }

    private function assertListingPaymentBank(ExchangeListing $listing, ?string $paymentBankCode): void
    {
        $allowed = array_values(array_filter(
            (array) ($listing->payment_methods ?? []),
            static fn (mixed $code): bool => is_string($code) && $code !== '',
        ));

        if ($allowed === []) {
            return;
        }

        if ($paymentBankCode === null || $paymentBankCode === '') {
            throw new RuntimeException('Выберите банк для оплаты.');
        }

        if (! in_array($paymentBankCode, $allowed, true)) {
            throw new RuntimeException('Выбранный банк недоступен для этого объявления.');
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

    /**
     * @return array<string, string>
     */
    private function orderPushData(ExchangeOrder $order): array
    {
        return ['url' => '/exchange/orders/'.$order->id];
    }
}
