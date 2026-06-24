<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manual subscription management with configurable plan types and commissions.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly UserNotificationService $notifier,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    /**
     * Grant or extend a subscription. Extending an active subscription
     * adds months to its current expiry instead of resetting it.
     */
    public function grant(User $user, int $months, User $admin, SubscriptionPlan $plan, ?string $comment = null): Subscription
    {
        if (! $plan->is_subscription || ! $plan->is_active) {
            throw new InvalidArgumentException('Выбранный тариф недоступен для подписки.');
        }

        $subscription = DB::transaction(function () use ($user, $months, $admin, $plan, $comment): Subscription {
            $active = $user->subscriptions()->active()->lockForUpdate()->latest('expires_at')->first();

            if ($active !== null) {
                $active->update([
                    'expires_at' => $active->expires_at->addMonths($months),
                    'subscription_plan_id' => $plan->id,
                    'comment' => $comment ?? $active->comment,
                ]);
                $subscription = $active->fresh(['plan']);
            } else {
                $subscription = Subscription::query()->create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => Subscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => now()->addMonths($months),
                    'granted_by' => $admin->id,
                    'comment' => $comment,
                ]);
                $subscription->load('plan');
            }

            $this->auditLogService->log(
                action: 'subscription.granted',
                userId: $admin->id,
                entityType: 'subscription',
                entityId: $subscription->id,
                payload: [
                    'target_user_id' => $user->id,
                    'months' => $months,
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'fee_percent' => $plan->fee_percent,
                    'expires_at' => $subscription->expires_at->toIso8601String(),
                    'comment' => $comment,
                ],
                request: request(),
            );

            return $subscription;
        });

        $this->subscriptionPlanService->clearUserCache($user->id);

        $this->notifier->notifyUser(
            $user,
            "⭐ Вам активирована подписка «{$plan->name}» до {$subscription->expires_at->format('d.m.Y')}.\n\n"
            .'Комиссия обмена: '.$this->subscriptionPlanService->telegramFeeLabel($plan->fee_percent).'.',
        );

        return $subscription;
    }

    public function cancel(Subscription $subscription, User $admin, ?string $reason = null): void
    {
        DB::transaction(function () use ($subscription, $admin, $reason): void {
            $subscription->update(['status' => Subscription::STATUS_CANCELLED]);

            $this->auditLogService->log(
                action: 'subscription.cancelled',
                userId: $admin->id,
                entityType: 'subscription',
                entityId: $subscription->id,
                payload: ['reason' => $reason],
                request: request(),
            );
        });

        $this->subscriptionPlanService->clearUserCache((int) $subscription->user_id);
    }

    /** Mark overdue active subscriptions as expired. Returns the number updated. */
    public function expireOverdue(): int
    {
        $expiredIds = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->pluck('user_id')
            ->all();

        $count = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->update(['status' => Subscription::STATUS_EXPIRED]);

        foreach ($expiredIds as $userId) {
            $this->subscriptionPlanService->clearUserCache((int) $userId);
        }

        return $count;
    }
}
