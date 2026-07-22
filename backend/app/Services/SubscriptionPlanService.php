<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\NumberPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class SubscriptionPlanService
{
    public function defaultPlan(): SubscriptionPlan
    {
        $planId = Cache::remember('subscription_plans:default', 300, function (): int {
            $plan = SubscriptionPlan::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();

            if ($plan instanceof SubscriptionPlan) {
                return $plan->id;
            }

            throw new RuntimeException('Default subscription plan is not configured.');
        });

        $plan = SubscriptionPlan::query()->find($planId);

        if ($plan instanceof SubscriptionPlan) {
            return $plan;
        }

        \App\Support\AppLog::http('subscription.default_plan_cache_miss', [
            'plan_id' => $planId,
        ], 'warning');

        Cache::forget('subscription_plans:default');

        throw new RuntimeException('Default subscription plan is not configured.');
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function activeSubscriptionPlans(): Collection
    {
        /** @var list<int> $planIds */
        $planIds = Cache::remember('subscription_plans:subscription', 300, fn (): array => SubscriptionPlan::query()
            ->where('is_subscription', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all());

        if ($planIds === []) {
            return new Collection;
        }

        $plansById = SubscriptionPlan::query()
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy('id');

        $ordered = [];

        foreach ($planIds as $planId) {
            $plan = $plansById->get($planId);

            if ($plan instanceof SubscriptionPlan) {
                $ordered[] = $plan;
            }
        }

        return new Collection($ordered);
    }

    public function primarySubscriptionPlan(): SubscriptionPlan
    {
        $plan = $this->activeSubscriptionPlans()->first();

        if ($plan instanceof SubscriptionPlan) {
            return $plan;
        }

        throw new RuntimeException('No active subscription plan is configured.');
    }

    public function feePercentFor(User $user): float
    {
        if ($user->has_subscription) {
            $base = $this->primarySubscriptionPlan()->fee_percent;
        } else {
            $active = $user->subscriptions()
                ->active()
                ->with('plan')
                ->latest('expires_at')
                ->first();

            if ($active instanceof Subscription && $active->plan instanceof SubscriptionPlan) {
                $base = $active->plan->fee_percent;
            } else {
                $base = $this->defaultPlan()->fee_percent;
            }
        }

        $discount = app(ReferralService::class)->activeFeeDiscount($user);

        return max(0, $base - $discount);
    }

    public function currentPlanFor(User $user): SubscriptionPlan
    {
        if ($user->hasReducedFee()) {
            $active = $user->subscriptions()
                ->active()
                ->with('plan')
                ->latest('expires_at')
                ->first();

            if ($active?->plan instanceof SubscriptionPlan) {
                return $active->plan;
            }

            return $this->primarySubscriptionPlan();
        }

        return $this->defaultPlan();
    }

    /**
     * @return array{code: string, name: string, fee_percent: float, timing: string, description: string}
     */
    public function payload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'fee_percent' => $plan->fee_percent,
            'timing' => (string) ($plan->timing ?? ''),
            'description' => (string) ($plan->description ?? ''),
            'is_default' => $plan->is_default,
            'is_subscription' => $plan->is_subscription,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminPayload(): array
    {
        return SubscriptionPlan::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => $this->payload($plan))
            ->all();
    }

    /**
     * @param  array{code: string, name: string, fee_percent: float|string, timing?: string|null, description?: string|null, is_subscription?: bool, is_active?: bool, sort_order?: int}  $data
     */
    public function create(array $data): SubscriptionPlan
    {
        $plan = DB::transaction(function () use ($data): SubscriptionPlan {
            if (($data['is_default'] ?? false) === true) {
                SubscriptionPlan::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return SubscriptionPlan::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'fee_percent' => $data['fee_percent'],
                'timing' => $data['timing'] ?? null,
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_subscription' => (bool) ($data['is_subscription'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        });

        $this->flushCache();

        return $plan;
    }

    /**
     * @param  array{name?: string, fee_percent?: float|string, timing?: string|null, description?: string|null, is_default?: bool, is_subscription?: bool, is_active?: bool, sort_order?: int}  $data
     */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        $updated = DB::transaction(function () use ($plan, $data): SubscriptionPlan {
            if (($data['is_default'] ?? false) === true && ! $plan->is_default) {
                SubscriptionPlan::query()->where('is_default', true)->update(['is_default' => false]);
            }

            if ($plan->is_default && array_key_exists('is_default', $data) && $data['is_default'] === false) {
                throw new InvalidArgumentException('Нельзя снять флаг базового тарифа. Сначала назначьте другой тариф базовым.');
            }

            if ($plan->is_default && array_key_exists('is_subscription', $data) && $data['is_subscription'] === true) {
                throw new InvalidArgumentException('Базовый тариф не может быть подпиской.');
            }

            $plan->fill(array_intersect_key($data, array_flip($plan->getFillable())));
            $plan->save();

            return $plan->fresh();
        });

        $this->flushCache();

        return $updated;
    }

    public function clearUserCache(int $userId): void
    {
        Cache::forget("user:{$userId}:active_subscription");
    }

    public function flushCache(): void
    {
        Cache::forget('subscription_plans:default');
        Cache::forget('subscription_plans:subscription');
    }

    public function feeLabel(float $feePercent): string
    {
        return NumberPresenter::percent($feePercent).'%';
    }
}
