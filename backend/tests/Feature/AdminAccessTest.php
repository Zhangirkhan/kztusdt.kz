<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\AdminUrl;
use App\Support\LocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\Concerns\InteractsWithAdminHost;
use Tests\TestCase;

/**
 * Ролевая модель: 5 ролей, у каждой свой доступ к админ-разделам.
 */
final class AdminAccessTest extends TestCase
{
    use ExchangeTestHelpers;
    use InteractsWithAdminHost;
    use RefreshDatabase;

    public function test_client_domain_redirects_admin_paths_to_subdomain(): void
    {
        $this->get('/admin')
            ->assertRedirect(AdminUrl::to());

        $this->get('/admin/login')
            ->assertRedirect(AdminUrl::to('login'));

        $this->get('/admin/orders')
            ->assertRedirect(AdminUrl::to('orders'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getAsAdmin('/admin')->assertRedirect('/admin/login');
        $this->getAsAdmin('/admin/kyc')->assertRedirect('/admin/login');
        $this->getAsAdmin('/admin/orders')->assertRedirect('/admin/login');
    }

    public function test_client_has_no_admin_access(): void
    {
        $client = $this->createClient();

        foreach (['/admin', '/admin/kyc', '/admin/orders', '/admin/withdrawals', '/admin/wallets', '/admin/sweeps', '/admin/subscriptions'] as $uri) {
            $this->actingAsAdmin($client)->get($uri)->assertRedirect('/admin/login');
        }

        $this->actingAsAdmin($client)->get('/admin/account')->assertRedirect('/admin/login');
    }

    public function test_exchange_client_role_has_no_admin_access(): void
    {
        $client = $this->createStaff('exchange_client');

        $this->actingAsAdmin($client)->get('/admin')->assertRedirect('/admin/login');
        $this->actingAsAdmin($client)->get('/admin/orders')->assertRedirect('/admin/login');
    }

    public function test_security_officer_sees_kyc_withdrawals_and_orders(): void
    {
        $officer = $this->createStaff('security_officer');

        $this->actingAsAdmin($officer)->get('/admin')->assertRedirect(route('admin.kyc.index'));
        $this->actingAsAdmin($officer)->get('/admin/kyc')->assertOk();
        $this->actingAsAdmin($officer)->get('/admin/withdrawals')->assertOk();
        $this->actingAsAdmin($officer)->get('/admin/orders')->assertOk();
        $this->actingAsAdmin($officer)->get('/admin/account')->assertOk();

        foreach (['/admin/sweeps', '/admin/wallets', '/admin/subscriptions'] as $uri) {
            $this->actingAsAdmin($officer)->get($uri)->assertRedirect(route('admin.kyc.index'));
        }

        $this->actingAsAdmin($officer)
            ->post('/admin/subscriptions', [
                'user_id' => $this->createClient()->id,
                'subscription_plan_id' => 1,
                'months' => 1,
            ])
            ->assertForbidden();
    }

    public function test_security_officer_is_redirected_from_pwa_to_admin_subdomain(): void
    {
        $officer = $this->createStaff('security_officer');
        $locale = LocaleManager::default();

        foreach (['home', 'wallet', 'exchange', 'withdraw', 'profile', 'kyc'] as $page) {
            $this->actingAs($officer)
                ->get('/'.$locale.'/'.$page)
                ->assertRedirect(AdminUrl::to('kyc'));
        }
    }

    public function test_super_admin_can_still_use_pwa(): void
    {
        $admin = $this->createStaff('super_admin');
        $locale = LocaleManager::default();

        $this->actingAs($admin)->get('/'.$locale.'/home')->assertRedirect('/'.$locale.'/wallet');
        $this->actingAs($admin)->get('/'.$locale.'/wallet')->assertOk();
    }

    public function test_super_admin_access(): void
    {
        $admin = $this->createStaff('super_admin');

        foreach (['/admin', '/admin/kyc', '/admin/orders', '/admin/withdrawals', '/admin/wallets', '/admin/sweeps', '/admin/subscriptions'] as $uri) {
            $this->actingAsAdmin($admin)->get($uri)->assertOk();
        }
    }

    public function test_super_admin_manager_access(): void
    {
        $manager = $this->createStaff('super_admin_manager');

        $this->actingAsAdmin($manager)->get('/admin')->assertOk();
        $this->actingAsAdmin($manager)->get('/admin/kyc')->assertOk();
        $this->actingAsAdmin($manager)->get('/admin/subscriptions')->assertRedirect('/admin');
    }

    public function test_exchange_admin_sees_only_orders(): void
    {
        $exchangeAdmin = $this->createStaff('exchange_admin');

        $this->actingAsAdmin($exchangeAdmin)->get('/admin/orders')->assertOk();

        $this->actingAsAdmin($exchangeAdmin)->get('/admin')->assertRedirect(route('admin.orders.index'));
        $this->actingAsAdmin($exchangeAdmin)->get('/admin/kyc')->assertRedirect(route('admin.orders.index'));
        $this->actingAsAdmin($exchangeAdmin)->get('/admin/withdrawals')->assertRedirect(route('admin.orders.index'));
        $this->actingAsAdmin($exchangeAdmin)->get('/admin/wallets')->assertRedirect(route('admin.orders.index'));
        $this->actingAsAdmin($exchangeAdmin)->get('/admin/subscriptions')->assertRedirect(route('admin.orders.index'));
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

        $this->actingAs($user)->get('/wallet')->assertRedirect(route('kyc'));
        $this->actingAs($user)->get('/withdraw')->assertRedirect(route('kyc'));
    }
}
