<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class AdminSubscriptionPlanTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_super_admin_can_view_plans_on_subscriptions_page(): void
    {
        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Subscriptions/Index')
                ->has('plans', 2)
                ->where('plans.0.code', 'standard')
                ->where('plans.1.code', 'economy'));
    }

    public function test_super_admin_can_create_subscription_plan(): void
    {
        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->post('/admin/subscriptions/plans', [
                'code' => 'premium',
                'name' => 'Премиум',
                'fee_percent' => 0.15,
                'timing' => 'До 6 часов',
                'description' => 'Средняя комиссия',
                'is_subscription' => true,
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.subscriptions.index'));

        $this->assertDatabaseHas('subscription_plans', [
            'code' => 'premium',
            'fee_percent' => 0.15,
            'is_subscription' => true,
        ]);
    }

    public function test_super_admin_can_update_plan_commission(): void
    {
        $admin = $this->createStaff('super_admin');
        $plan = SubscriptionPlan::query()->where('code', 'economy')->firstOrFail();

        $this->actingAsAdmin($admin)
            ->patch("/admin/subscriptions/plans/{$plan->id}", [
                'fee_percent' => 0.08,
                'name' => 'Эконом',
            ])
            ->assertRedirect(route('admin.subscriptions.index'));

        $plan->refresh();

        $this->assertSame(0.08, $plan->fee_percent);
        $this->assertSame('Эконом', $plan->name);
    }

    public function test_grant_subscription_requires_plan(): void
    {
        $admin = $this->createStaff('super_admin');
        $client = $this->createClient();
        $plan = SubscriptionPlan::query()->where('code', 'economy')->firstOrFail();

        $this->actingAsAdmin($admin)
            ->post('/admin/subscriptions', [
                'user_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'months' => 2,
                'comment' => 'test grant',
            ])
            ->assertRedirect(route('admin.subscriptions.index'));

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $client->refresh();

        $this->assertSame(0.05, $client->feePercent());
    }

    public function test_updated_plan_commission_applies_to_subscribed_user(): void
    {
        $admin = $this->createStaff('super_admin');
        $client = $this->createClient();
        $plan = SubscriptionPlan::query()->where('code', 'economy')->firstOrFail();

        $this->actingAsAdmin($admin)->post('/admin/subscriptions', [
            'user_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'months' => 1,
        ]);

        $this->actingAsAdmin($admin)->patch("/admin/subscriptions/plans/{$plan->id}", [
            'fee_percent' => 0.12,
        ]);

        $client->refresh();

        $this->assertSame(0.12, $client->feePercent());
    }
}
