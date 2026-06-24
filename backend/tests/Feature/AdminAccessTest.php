<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Ролевая модель: 5 ролей, у каждой свой доступ к админ-разделам.
 */
final class AdminAccessTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/kyc')->assertRedirect('/login');
        $this->get('/admin/orders')->assertRedirect('/login');
    }

    public function test_client_has_no_admin_access(): void
    {
        $client = $this->createClient();

        foreach (['/admin', '/admin/kyc', '/admin/orders', '/admin/withdrawals', '/admin/wallets', '/admin/sweeps', '/admin/subscriptions'] as $uri) {
            $this->actingAs($client)->get($uri)->assertForbidden();
        }

        $this->actingAs($client)->get('/admin/account')->assertForbidden();
    }

    public function test_exchange_client_role_has_no_admin_access(): void
    {
        $client = $this->createStaff('exchange_client');

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/admin/orders')->assertForbidden();
    }

    public function test_security_officer_sees_kyc_withdrawals_and_orders(): void
    {
        $officer = $this->createStaff('security_officer');

        $this->actingAs($officer)->get('/admin')->assertRedirect(route('admin.kyc.index'));
        $this->actingAs($officer)->get('/admin/kyc')->assertOk();
        $this->actingAs($officer)->get('/admin/withdrawals')->assertOk();
        $this->actingAs($officer)->get('/admin/orders')->assertOk();
        $this->actingAs($officer)->get('/admin/account')->assertOk();

        foreach (['/admin/sweeps', '/admin/wallets', '/admin/subscriptions'] as $uri) {
            $this->actingAs($officer)->get($uri)->assertForbidden();
        }

        $this->actingAs($officer)
            ->post('/admin/subscriptions', [
                'user_id' => $this->createClient()->id,
                'subscription_plan_id' => 1,
                'months' => 1,
            ])
            ->assertForbidden();
    }

    public function test_security_officer_is_redirected_from_pwa_to_admin_kyc(): void
    {
        $officer = $this->createStaff('security_officer');

        foreach (['/home', '/wallet', '/exchange', '/withdraw', '/profile', '/kyc'] as $uri) {
            $this->actingAs($officer)->get($uri)->assertRedirect(route('admin.kyc.index'));
        }
    }

    public function test_super_admin_can_still_use_pwa(): void
    {
        $admin = $this->createStaff('super_admin');

        $this->actingAs($admin)->get('/home')->assertOk();
    }

    public function test_super_admin_access(): void
    {
        $admin = $this->createStaff('super_admin');

        foreach (['/admin', '/admin/kyc', '/admin/orders', '/admin/withdrawals', '/admin/wallets', '/admin/sweeps', '/admin/subscriptions'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_super_admin_manager_access(): void
    {
        $manager = $this->createStaff('super_admin_manager');

        $this->actingAs($manager)->get('/admin')->assertOk();
        $this->actingAs($manager)->get('/admin/kyc')->assertOk();
        $this->actingAs($manager)->get('/admin/subscriptions')->assertForbidden();
    }

    public function test_exchange_admin_sees_only_orders(): void
    {
        $exchangeAdmin = $this->createStaff('exchange_admin');

        $this->actingAs($exchangeAdmin)->get('/admin/orders')->assertOk();

        $this->actingAs($exchangeAdmin)->get('/admin')->assertForbidden();
        $this->actingAs($exchangeAdmin)->get('/admin/kyc')->assertForbidden();
        $this->actingAs($exchangeAdmin)->get('/admin/withdrawals')->assertForbidden();
        $this->actingAs($exchangeAdmin)->get('/admin/wallets')->assertForbidden();
        $this->actingAs($exchangeAdmin)->get('/admin/subscriptions')->assertForbidden();
    }

    public function test_client_pages_require_authentication(): void
    {
        $this->get('/home')->assertRedirect('/auth/phone');
        $this->get('/wallet')->assertRedirect('/auth/phone');
        $this->get('/exchange')->assertRedirect('/auth/phone');
        $this->get('/withdraw')->assertRedirect('/auth/phone');
        $this->get('/kyc')->assertRedirect('/auth/phone');
    }

    public function test_wallet_and_withdraw_pages_require_approved_kyc(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->get('/wallet')->assertForbidden();
        $this->actingAs($user)->get('/withdraw')->assertForbidden();
    }
}
