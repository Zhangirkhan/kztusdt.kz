<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProfileService
{
    public function __construct(
        private readonly PhoneAuthService $phoneAuthService,
        private readonly AuditLogService $auditLogService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    /**
     * @param  array{name: string, email?: string|null, phone: string}  $data
     * @return array{user: User, phone_changed: bool}
     */
    public function update(User $user, array $data): array
    {
        $normalizedPhone = $this->phoneAuthService->normalizePhone($data['phone']);
        $phoneChanged = $user->phone !== $normalizedPhone;
        $email = isset($data['email']) ? trim((string) $data['email']) : null;

        $updated = DB::transaction(function () use ($user, $data, $normalizedPhone, $phoneChanged, $email): User {
            $user->name = trim($data['name']);

            if ($email !== null && $email !== '') {
                $user->email = strtolower($email);
            }

            $user->phone = $normalizedPhone;

            if ($phoneChanged) {
                $user->phone_verified = false;
                $user->phone_verified_at = null;

                $user->telegramAccount?->update([
                    'phone' => $normalizedPhone,
                    'is_verified' => false,
                ]);
            }

            $user->save();

            $this->auditLogService->log(
                action: 'profile.updated',
                userId: $user->id,
                entityType: 'user',
                entityId: $user->id,
                payload: [
                    'phone_changed' => $phoneChanged,
                ],
            );

            return $user->fresh(['telegramAccount', 'kycProfile']);
        });

        return [
            'user' => $updated,
            'phone_changed' => $phoneChanged,
        ];
    }

    public function displayEmail(User $user): string
    {
        $email = (string) $user->email;

        if (str_ends_with($email, '@exchange.local')) {
            return '';
        }

        return $email;
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(User $user): array
    {
        $user->loadMissing(['telegramAccount', 'kycProfile']);

        $hasReducedFee = $user->hasReducedFee();
        $activeSubscription = $user->subscriptions()
            ->active()
            ->with('plan')
            ->latest('expires_at')
            ->first();

        $defaultPlan = $this->subscriptionPlanService->defaultPlan();
        $subscriptionPlans = $this->subscriptionPlanService->activeSubscriptionPlans();
        $currentPlan = $this->subscriptionPlanService->currentPlanFor($user);
        $displaySubscriptionPlan = $activeSubscription?->plan instanceof SubscriptionPlan
            ? $activeSubscription->plan
            : $subscriptionPlans->first();

        return [
            'name' => $user->name,
            'email' => $this->displayEmail($user),
            'phone' => $user->phone ?? '+7',
            'phone_verified' => (bool) $user->phone_verified,
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'kyc_status' => (string) $user->kyc_status,
            'telegram_username' => $user->telegramAccount?->telegram_username,
            'fee_percent' => $user->feePercent(),
            'has_subscription' => $hasReducedFee,
            'current_tariff' => $currentPlan->code,
            'tariffs' => [
                'standard' => $this->subscriptionPlanService->payload($defaultPlan),
                'subscription' => $displaySubscriptionPlan instanceof SubscriptionPlan
                    ? $this->subscriptionPlanService->payload($displaySubscriptionPlan)
                    : $this->subscriptionPlanService->payload($defaultPlan),
            ],
            'subscription_plans' => $subscriptionPlans
                ->map(fn (SubscriptionPlan $plan): array => $this->subscriptionPlanService->payload($plan))
                ->values()
                ->all(),
            'subscription' => $activeSubscription instanceof Subscription ? [
                'expires_at' => $activeSubscription->expires_at->toIso8601String(),
                'plan' => $activeSubscription->plan instanceof SubscriptionPlan
                    ? $this->subscriptionPlanService->payload($activeSubscription->plan)
                    : null,
            ] : null,
            'support_email' => config('company.support_email'),
            'kyc_first_name' => $user->kycProfile?->first_name,
            'kyc_last_name' => $user->kycProfile?->last_name,
            'locale' => $user->locale,
        ];
    }

}
