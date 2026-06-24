<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantSubscriptionRequest;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $subscriptions = Subscription::query()
            ->with(['user:id,name,phone,email', 'grantedBy:id,name', 'plan:id,code,name,fee_percent'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $foundUsers = $search !== ''
            ? User::query()
                ->where(function ($query) use ($search): void {
                    $query->where('phone', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'phone', 'email'])
            : collect();

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'search' => $search,
            'foundUsers' => $foundUsers,
            'plans' => $this->subscriptionPlanService->adminPayload(),
            'subscriptionPlans' => $this->subscriptionPlanService->activeSubscriptionPlans()
                ->map(fn (SubscriptionPlan $plan): array => $this->subscriptionPlanService->payload($plan))
                ->values()
                ->all(),
        ]);
    }

    public function storePlan(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $this->subscriptionPlanService->create($request->validated());

        return redirect()
            ->route('admin.subscriptions.index')
            ->with('success', 'Тариф создан.');
    }

    public function updatePlan(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        try {
            $this->subscriptionPlanService->update($plan, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('admin.subscriptions.index')
                ->withErrors(['plan' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.subscriptions.index')
            ->with('success', 'Тариф обновлён.');
    }

    public function store(GrantSubscriptionRequest $request): RedirectResponse
    {
        $user = User::query()->findOrFail((int) $request->validated('user_id'));
        $plan = SubscriptionPlan::query()->findOrFail((int) $request->validated('subscription_plan_id'));

        if (! $plan->is_subscription || ! $plan->is_active) {
            return redirect()
                ->route('admin.subscriptions.index')
                ->withErrors(['subscription_plan_id' => 'Выбранный тариф недоступен для подписки.']);
        }

        $this->subscriptionService->grant(
            $user,
            (int) $request->validated('months'),
            $request->user(),
            $plan,
            $request->validated('comment'),
        );

        return redirect()->route('admin.subscriptions.index')->with('success', 'Подписка выдана/продлена.');
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptionService->cancel($subscription, $request->user());

        return redirect()->route('admin.subscriptions.index')->with('success', 'Подписка отменена.');
    }
}
