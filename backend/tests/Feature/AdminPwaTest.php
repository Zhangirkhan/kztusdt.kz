<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\AdminUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\Concerns\InteractsWithAdminHost;
use Tests\TestCase;

final class AdminPwaTest extends TestCase
{
    use ExchangeTestHelpers;
    use InteractsWithAdminHost;
    use RefreshDatabase;

    public function test_admin_manifest_is_available_on_subdomain(): void
    {
        $this->withServerVariables($this->adminServerVariables())
            ->get('https://'.$this->adminHost().'/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'Admin kztusdt')
            ->assertJsonPath('short_name', 'Admin kztusdt')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('start_url', '/admin/login')
            ->assertJsonPath('icons.0.src', '/icons/admin/icon-192.png');
    }

    public function test_admin_pages_link_admin_manifest(): void
    {
        $this->getAsAdmin('/admin/login')
            ->assertOk()
            ->assertSee('href="/manifest.webmanifest"', false);
    }

    public function test_client_pages_keep_main_manifest(): void
    {
        $this->get('/ru/')
            ->assertOk()
            ->assertSee('href="/manifest.webmanifest"', false);
    }

    public function test_client_manifest_uses_locale_scope(): void
    {
        $response = $this->get('/manifest.webmanifest')
            ->assertOk();

        $startUrl = (string) $response->json('start_url');

        $response
            ->assertJsonPath('name', 'KZTUSDT')
            ->assertJsonPath('short_name', 'KZTUSDT')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('id', '/')
            ->assertJsonPath('icons.0.src', '/icons/icon-192.png');

        $this->assertMatchesRegularExpression('#^/(ru|kk|en)/$#', $startUrl);
    }

    public function test_client_domain_redirects_admin_login_to_subdomain(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)
            ->get('/admin/login')
            ->assertRedirect(AdminUrl::to('login'));
    }

    public function test_authenticated_staff_is_redirected_from_admin_login(): void
    {
        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }
}
