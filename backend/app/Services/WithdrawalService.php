<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\WithdrawalRetryLaterException;
use App\Models\ManualApproval;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Tron\TronAddressService;
use App\Services\Withdrawals\WithdrawalBroadcasterRegistry;
use App\Support\AppLog;
use App\Support\NetworkRegistry;
use App\Support\NumberPresenter;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Stage 8 — USDT BEP20 withdrawals from the hot wallet.
 *
 * Flow: pending_review → approved → sending → sent → completed.
 * All withdrawals require security-officer approval in admin (no auto-approve).
 * The client confirms the request in-app; PWA push notifications keep them
 * informed of each status change.
 * Real broadcasting only happens when WITHDRAWALS_ENABLED=true; until then
 * approved withdrawals just wait in the queue.
 */
final class WithdrawalService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly EvmAddressValidator $addressValidator,
        private readonly TronAddressService $tronAddressValidator,
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
        private readonly WithdrawalBroadcasterRegistry $broadcasters,
    ) {}

    public function create(User $user, string $toAddress, string $amount, ?string $network = null): Withdrawal
    {
        $network ??= (string) config('wallet.network');

        if (! NetworkRegistry::isEnabled($network)) {
            throw new RuntimeException('Выбранная сеть недоступна.');
        }

        $this->assertValidAddress($network, $toAddress);

        $asset = NetworkRegistry::asset($network);

        $amount = number_format((float) $amount, 8, '.', '');
        $min = number_format((float) config('withdrawal.min_amount', 1), 8, '.', '');

        if (bccomp($amount, $min, 8) < 0) {
            throw new RuntimeException("Минимальная сумма вывода — {$min} {$asset}.");
        }

        $feePercent = number_format($user->feePercent(), 4, '.', '');
        $fee = bcdiv(bcmul($amount, $feePercent, 18), '100', 18);
        $networkFee = (string) config('withdrawal.network_fee_usdt', '0.5');
        $total = bcadd(bcadd($amount, $fee, 18), $networkFee, 18);

        $withdrawal = DB::transaction(function () use ($user, $toAddress, $amount, $fee, $networkFee, $total, $network, $asset): Withdrawal {
            $withdrawal = Withdrawal::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id ?? Tenant::defaultTenantId(),
                'network' => $network,
                'asset' => $asset,
                'to_address' => $toAddress,
                'amount' => $amount,
                'fee_amount' => $fee,
                'network_fee' => $networkFee,
                'total_debit' => $total,
                'status' => Withdrawal::STATUS_PENDING_REVIEW,
                'requires_manual_approval' => true,
            ]);

            ManualApproval::query()->create([
                'entity_type' => 'withdrawal',
                'entity_id' => $withdrawal->id,
                'required_role' => 'security_officer',
                'status' => 'pending',
                'requested_by' => $user->id,
            ]);

            // Hold the full debit until the withdrawal completes or is cancelled.
            $this->ledgerService->lock(
                $user->id,
                $withdrawal->asset,
                $total,
                'withdrawal',
                $withdrawal->id,
                "Withdrawal #{$withdrawal->id} hold",
            );

            $this->auditLogService->log(
                action: 'withdrawal.created',
                userId: $user->id,
                entityType: 'withdrawal',
                entityId: $withdrawal->id,
                payload: ['to' => $toAddress, 'amount' => $amount, 'total' => $total],
                request: request(),
            );

            return $withdrawal;
        });

        $this->sendCreatedNotification($withdrawal);

        return $withdrawal;
    }

    private function assertValidAddress(string $network, string $address): void
    {
        $valid = NetworkRegistry::addressFormat($network) === 'tron'
            ? $this->tronAddressValidator->isValid($address)
            : $this->addressValidator->isValid($address);

        if (! $valid) {
            throw new RuntimeException(
                NetworkRegistry::addressFormat($network) === 'tron'
                    ? 'Некорректный TRON-адрес (Base58Check / контрольная сумма).'
                    : 'Некорректный адрес (формат EVM / контрольная сумма EIP-55).',
            );
        }
    }

    private function sendCreatedNotification(Withdrawal $withdrawal): void
    {
        $this->notifier->notifyUser(
            $withdrawal->user,
            "📋 Заявка на вывод №{$withdrawal->id} создана.\n\n"
            ."Сумма: {$this->usdt((string) $withdrawal->amount)} USDT\n"
            ."Комиссия сервиса: {$this->usdt((string) $withdrawal->fee_amount)} USDT\n"
            ."Комиссия сети: {$this->usdt((string) $withdrawal->network_fee)} USDT\n"
            ."Итого к списанию: {$this->usdt((string) $withdrawal->total_debit)} USDT\n\n"
            ."Адрес ({$withdrawal->network}):\n<code>{$withdrawal->to_address}</code>\n\n"
            .'Заявка передана на проверку службе безопасности.',
        );
    }

    public function approve(Withdrawal $withdrawal, User $admin, ?string $comment = null): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $comment): void {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if ($withdrawal->status !== Withdrawal::STATUS_PENDING_REVIEW) {
                throw new RuntimeException('Заявка не ожидает ручного апрува.');
            }

            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ]);

            ManualApproval::query()
                ->where('entity_type', 'withdrawal')
                ->where('entity_id', $withdrawal->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                    'comment' => $comment,
                ]);

            $this->auditLogService->log(
                action: 'withdrawal.approved',
                userId: $admin->id,
                entityType: 'withdrawal',
                entityId: $withdrawal->id,
                payload: ['comment' => $comment],
                request: request(),
            );
        });

        $withdrawal->refresh();

        $this->notifier->notifyUser(
            $withdrawal->user,
            "✅ Вывод №{$withdrawal->id} одобрен службой безопасности и поставлен в очередь на отправку.",
        );
    }

    public function retryFailed(Withdrawal $withdrawal, User $admin): void
    {
        DB::transaction(function () use ($withdrawal, $admin): void {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if ($withdrawal->status !== Withdrawal::STATUS_FAILED) {
                throw new RuntimeException('Повтор доступен только для заявок со статусом «ошибка».');
            }

            $this->reconcileNetworkFeeIfNeeded($withdrawal);

            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'attempts' => 0,
                'last_error' => null,
            ]);

            $this->auditLogService->log(
                action: 'withdrawal.retry',
                userId: $admin->id,
                entityType: 'withdrawal',
                entityId: $withdrawal->id,
                payload: [],
                request: request(),
            );
        });

        $withdrawal->refresh();

        $this->notifier->notifyUser(
            $withdrawal->user,
            "🔁 Вывод №{$withdrawal->id} снова поставлен в очередь на отправку.",
        );
    }

    public function reject(Withdrawal $withdrawal, User $admin, string $reason): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $reason): void {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! in_array($withdrawal->status, [
                Withdrawal::STATUS_PENDING_REVIEW,
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_AWAITING_TELEGRAM_CONFIRMATION,
            ], true)) {
                throw new RuntimeException('Заявку нельзя отклонить в текущем статусе.');
            }

            $this->ledgerService->unlock(
                $withdrawal->user_id,
                $withdrawal->asset,
                (string) $withdrawal->total_debit,
                'withdrawal',
                $withdrawal->id,
                "Withdrawal #{$withdrawal->id} rejected",
            );

            $withdrawal->update([
                'status' => Withdrawal::STATUS_REJECTED,
                'reject_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            ManualApproval::query()
                ->where('entity_type', 'withdrawal')
                ->where('entity_id', $withdrawal->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_by' => $admin->id,
                    'rejected_at' => now(),
                    'comment' => $reason,
                ]);

            $this->auditLogService->log(
                action: 'withdrawal.rejected',
                userId: $admin->id,
                entityType: 'withdrawal',
                entityId: $withdrawal->id,
                payload: ['reason' => $reason],
                request: request(),
            );
        });

        $withdrawal->refresh();

        $this->notifier->notifyUser(
            $withdrawal->user,
            "❌ Вывод №{$withdrawal->id} отклонён.\n\nПричина: {$reason}\nСредства разблокированы.",
        );
    }

    public function cancelByClient(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal): void {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! in_array($withdrawal->status, [
                Withdrawal::STATUS_CREATED,
                Withdrawal::STATUS_AWAITING_TELEGRAM_CONFIRMATION,
                Withdrawal::STATUS_PENDING_REVIEW,
            ], true)) {
                throw new RuntimeException('Заявку нельзя отменить в текущем статусе.');
            }

            $this->releaseAndCancel($withdrawal, 'Отменена клиентом', $withdrawal->user_id);
        });

        $withdrawal->refresh();

        $this->notifier->notifyUser(
            $withdrawal->user,
            "🚫 Вывод №{$withdrawal->id} отменён. Средства разблокированы.",
        );
    }

    /** Must be called inside a transaction with the row already locked. */
    private function releaseAndCancel(Withdrawal $withdrawal, string $reason, ?int $actorId): void
    {
        $this->ledgerService->unlock(
            $withdrawal->user_id,
            $withdrawal->asset,
            (string) $withdrawal->total_debit,
            'withdrawal',
            $withdrawal->id,
            "Withdrawal #{$withdrawal->id} cancelled",
        );

        $withdrawal->update([
            'status' => Withdrawal::STATUS_CANCELLED,
            'reject_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        ManualApproval::query()
            ->where('entity_type', 'withdrawal')
            ->where('entity_id', $withdrawal->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'comment' => $reason]);

        $this->auditLogService->log(
            action: 'withdrawal.cancelled',
            userId: $actorId,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: ['reason' => $reason],
        );
    }

    /**
     * One processing pass: broadcast approved withdrawals, confirm sent ones.
     *
     * @return array{enabled: bool, broadcast: int, completed: int, failed: int}
     */
    public function processQueue(): array
    {
        if (! (bool) config('withdrawal.enabled')) {
            return ['enabled' => false, 'broadcast' => 0, 'completed' => 0, 'failed' => 0];
        }

        // Recover rows whose process died between "claimed" and the broadcast result.
        $this->reconcileStuckSending();

        $broadcast = 0;
        $completed = 0;
        $failed = 0;

        $approved = Withdrawal::query()
            ->where('status', Withdrawal::STATUS_APPROVED)
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($approved as $withdrawal) {
            try {
                if ($this->broadcastOne($withdrawal)) {
                    $broadcast++;
                }
            } catch (WithdrawalRetryLaterException $e) {
                $withdrawal->refresh();
                $withdrawal->update([
                    'status' => Withdrawal::STATUS_APPROVED,
                    'last_error' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                $failed++;
                $humanized = $this->broadcasters->for($withdrawal->network)->humanizeError($e->getMessage());
                $this->markBroadcastFailure($withdrawal, $humanized);
            }
        }

        $sent = Withdrawal::query()
            ->where('status', Withdrawal::STATUS_SENT)
            ->orderBy('id')
            ->get();

        foreach ($sent as $withdrawal) {
            try {
                if ($this->confirmOne($withdrawal)) {
                    $completed++;
                }
            } catch (Throwable $e) {
                AppLog::warning('withdrawal.confirm_error', [
                    'withdrawal_id' => $withdrawal->id,
                    'network' => $withdrawal->network,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['enabled' => true, 'broadcast' => $broadcast, 'completed' => $completed, 'failed' => $failed];
    }

    private function broadcastOne(Withdrawal $withdrawal): bool
    {
        $broadcaster = $this->broadcasters->for($withdrawal->network);

        // 1. Resolve the signer and ensure it has gas BEFORE claiming the row.
        //    Anything that throws here is pre-broadcast (no on-chain tx yet) and is
        //    therefore safe to retry — the row is still "approved".
        $prepared = $broadcaster->prepare($withdrawal);

        // 2. Claim the row so a parallel run never double-sends.
        $claimed = DB::transaction(function () use ($withdrawal): bool {
            $row = Withdrawal::query()->lockForUpdate()->find($withdrawal->id);

            if ($row === null || $row->status !== Withdrawal::STATUS_APPROVED) {
                return false;
            }

            $row->update(['status' => Withdrawal::STATUS_SENDING]);

            return true;
        });

        if (! $claimed) {
            return false;
        }

        // 3. Broadcast. Past this point a failure is AMBIGUOUS — the tx may already
        //    be on-chain — so we route the row to needs_reconcile and NEVER revert
        //    to "approved" (which would risk a double-send on the next pass).
        try {
            $hash = $broadcaster->send($prepared);
        } catch (Throwable $e) {
            $this->markSendingInterrupted($withdrawal, $broadcaster->humanizeError($e->getMessage()));

            return false;
        }

        $withdrawal->update([
            'status' => Withdrawal::STATUS_SENT,
            'tx_hash' => $hash,
            'broadcast_at' => now(),
            'attempts' => $withdrawal->attempts + 1,
        ]);

        $this->auditLogService->log(
            action: 'withdrawal.broadcast',
            userId: null,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: [
                'tx' => $hash,
                'to' => $withdrawal->to_address,
                'amount' => (string) $withdrawal->amount,
                'source' => $prepared->signerSource,
                'from' => $prepared->signerAddress,
            ],
        );

        $this->notifier->notifyUser(
            $withdrawal->user,
            "📤 Вывод №{$withdrawal->id} отправлен в сеть.\n\nTx: <code>{$hash}</code>\nОжидаем подтверждения блокчейна.",
        );

        return true;
    }

    /**
     * Move rows stuck in "sending" with no tx hash (process died mid-broadcast)
     * to needs_reconcile after a grace period, so funds never silently hang.
     */
    private function reconcileStuckSending(): void
    {
        $graceSeconds = (int) config('withdrawal.sending_grace_seconds', 180);

        $stuck = Withdrawal::query()
            ->where('status', Withdrawal::STATUS_SENDING)
            ->whereNull('tx_hash')
            ->where('updated_at', '<', now()->subSeconds($graceSeconds))
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($stuck as $withdrawal) {
            $this->markSendingInterrupted(
                $withdrawal,
                'Процесс отправки не завершился (зависшая транзакция в статусе sending).',
            );
        }
    }

    private function confirmOne(Withdrawal $withdrawal): bool
    {
        $confirmation = $this->broadcasters->for($withdrawal->network)->confirm($withdrawal);

        if ($confirmation->isSuccess()) {
            $this->settleCompleted($withdrawal);

            return true;
        }

        if ($confirmation->isReverted()) {
            $this->markReverted($withdrawal, (string) $confirmation->reason, $confirmation->payload);
        }

        return false; // pending → check again next pass
    }

    private function settleCompleted(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal): void {
            $row = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if ($row->status !== Withdrawal::STATUS_SENT) {
                return;
            }

            $feeTotal = bcadd((string) $row->fee_amount, (string) $row->network_fee, 18);

            $this->ledgerService->settleWithdrawal(
                $row->user_id,
                $row->asset,
                (string) $row->total_debit,
                (string) $row->amount,
                $feeTotal,
                'withdrawal',
                $row->id,
                "Withdrawal #{$row->id} settled ({$row->tx_hash})",
            );

            $row->update([
                'status' => Withdrawal::STATUS_COMPLETED,
                'confirmed_at' => now(),
                'completed_at' => now(),
            ]);

            $this->auditLogService->log(
                action: 'withdrawal.completed',
                userId: null,
                entityType: 'withdrawal',
                entityId: $withdrawal->id,
                payload: ['tx' => $withdrawal->tx_hash],
            );
        });

        $this->notifier->notifyUser(
            $withdrawal->user,
            "✅ Вывод №{$withdrawal->id} выполнен!\n\n"
            ."{$this->usdt((string) $withdrawal->amount)} {$withdrawal->asset} отправлено на\n<code>{$withdrawal->to_address}</code>\n\n"
            .$this->explorerTx($withdrawal),
        );
    }

    private function markReverted(Withdrawal $withdrawal, string $reason, array $payload = []): void
    {
        // Tx reverted on-chain: funds stay locked, needs a human decision.
        $withdrawal->update([
            'status' => Withdrawal::STATUS_FAILED,
            'last_error' => $reason,
        ]);

        $this->auditLogService->log(
            action: 'withdrawal.failed',
            userId: null,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: array_merge(['tx' => $withdrawal->tx_hash], $payload),
        );
    }

    private function explorerTx(Withdrawal $withdrawal): string
    {
        $base = NetworkRegistry::exists($withdrawal->network)
            ? NetworkRegistry::explorerTx($withdrawal->network)
            : 'https://bscscan.com/tx/';

        return $base.$withdrawal->tx_hash;
    }

    private function reconcileNetworkFeeIfNeeded(Withdrawal $withdrawal): void
    {
        $configuredFee = number_format((float) config('withdrawal.network_fee_usdt', 0.01), 8, '.', '');

        if (bccomp((string) $withdrawal->network_fee, $configuredFee, 8) <= 0) {
            return;
        }

        $oldTotal = (string) $withdrawal->total_debit;
        $newTotal = bcadd(
            bcadd((string) $withdrawal->amount, (string) $withdrawal->fee_amount, 18),
            $configuredFee,
            18,
        );
        $refund = bcsub($oldTotal, $newTotal, 18);

        if (bccomp($refund, '0', 18) > 0) {
            $this->ledgerService->unlock(
                $withdrawal->user_id,
                $withdrawal->asset,
                $refund,
                'withdrawal',
                $withdrawal->id,
                "Withdrawal #{$withdrawal->id} network fee correction",
            );
        }

        $withdrawal->update([
            'network_fee' => $configuredFee,
            'total_debit' => $newTotal,
        ]);
    }

    /**
     * Pre-broadcast failure (signer resolution / funds / gas): no on-chain tx was
     * sent, so it is safe to retry. The row is still "approved" here.
     */
    private function markBroadcastFailure(Withdrawal $withdrawal, string $error): void
    {
        $withdrawal->refresh();

        $attempts = (int) $withdrawal->attempts + 1;
        $maxAttempts = (int) config('withdrawal.max_attempts', 3);
        $status = $attempts >= $maxAttempts
            ? Withdrawal::STATUS_FAILED
            : Withdrawal::STATUS_APPROVED; // retry on the next pass

        $withdrawal->update([
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $error,
        ]);

        AppLog::warning('withdrawal.broadcast_failed', [
            'withdrawal_id' => $withdrawal->id,
            'network' => $withdrawal->network,
            'attempts' => $attempts,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * Broadcast was interrupted AFTER the row was claimed: the tx may or may not be
     * on-chain. Funds stay locked and a human must reconcile before any retry — we
     * must never revert to "approved" here (that would risk a double-send).
     */
    private function markSendingInterrupted(Withdrawal $withdrawal, string $error): void
    {
        $withdrawal->update([
            'status' => Withdrawal::STATUS_NEEDS_RECONCILE,
            'attempts' => (int) $withdrawal->attempts + 1,
            'last_error' => 'Отправка прервана — нужна сверка по блокчейну перед повтором: '.$error,
        ]);

        $this->auditLogService->log(
            action: 'withdrawal.broadcast_interrupted',
            userId: null,
            entityType: 'withdrawal',
            entityId: $withdrawal->id,
            payload: ['error' => $error],
        );

        AppLog::warning('withdrawal.broadcast_interrupted', [
            'withdrawal_id' => $withdrawal->id,
            'network' => $withdrawal->network,
            'error' => $error,
        ]);

        $this->notifier->notifyUser(
            $withdrawal->user,
            "⚠️ Вывод №{$withdrawal->id}: отправка прервана, проверяем статус в сети. "
            .'Средства остаются заблокированными до выяснения.',
        );
    }

    private function usdt(string $amount): string
    {
        return NumberPresenter::withThousands($amount, 2);
    }
}
